<?php
/**
 * Billboard adverts: simple (generated from a linked offer with validated tokens) and
 * advanced (literal copy + a securely processed image). A simple billboard whose linked
 * offer becomes unavailable is not published. Personalisation runs automatically in the
 * app — there are no scoring controls here.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;
use App\Services\BillboardService;
use App\Services\ImageUploader;

final class BillboardsController extends Controller
{
    private const STATUSES = ['draft', 'scheduled', 'active', 'paused', 'expired', 'archived'];

    private function table(): string { return Database::table('billboards'); }

    public function index(Request $request): void
    {
        $this->guard('billboards.manage');
        $this->view('billboards/index', [
            'activeNav' => 'billboards', 'pageTitle' => 'Billboard adverts',
            'billboards' => Database::fetchAll(
                'SELECT b.*, a.stored_name AS image_name FROM ' . $this->table() . ' b
                 LEFT JOIN ' . Database::table('billboard_assets') . ' a ON a.id = b.image_asset_id
                 ORDER BY b.priority ASC, b.id DESC'
            ),
        ]);
    }

    public function calendar(Request $request): void
    {
        $this->guard('billboards.manage');
        $this->view('billboards/calendar', [
            'activeNav' => 'billboards', 'pageTitle' => 'Billboard schedule',
            'items' => Database::fetchAll('SELECT * FROM ' . $this->table() . " WHERE starts_at IS NOT NULL ORDER BY starts_at ASC"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->guard('billboards.manage');
        $this->renderForm(null, true);
    }

    public function edit(Request $request, string $id): void
    {
        $this->guard('billboards.manage');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) { Flash::error('Billboard not found.'); $this->redirect('/billboards'); }
        $this->renderForm($row, false);
    }

    private function renderForm(?array $row, bool $isNew): void
    {
        $image = null;
        if ($row && $row['image_asset_id']) {
            $image = Database::fetch('SELECT * FROM ' . Database::table('billboard_assets') . ' WHERE id = ?', [(int) $row['image_asset_id']]);
        }
        $this->view('billboards/form', [
            'activeNav' => 'billboards', 'pageTitle' => $isNew ? 'New billboard' : 'Edit billboard',
            'b' => $row, 'isNew' => $isNew, 'image' => $image, 'tokens' => BillboardService::TOKENS,
            'offers' => Database::fetchAll("SELECT offer_id, name, price, validity, category FROM " . Database::table('offers') . " WHERE status='active' ORDER BY category, name"),
        ]);
    }

    public function save(Request $request): void
    {
        Csrf::check($request);
        $this->guard('billboards.manage');
        $isNew = $request->post('is_new') === '1';
        $id = (int) $request->post('id', 0);
        $kind = $request->post('kind') === 'advanced' ? 'advanced' : 'simple';

        $input = [
            'name' => trim((string) $request->post('name', '')),
            'kind' => $kind,
            'priority' => (int) $request->post('priority', 5),
            'linked_offer_id' => trim((string) $request->post('linked_offer_id', '')) ?: null,
            'tag' => trim((string) $request->post('tag', '')),
            'headline' => trim((string) $request->post('headline', '')),
            'body' => trim((string) $request->post('body', '')),
            'cta_label' => trim((string) $request->post('cta_label', 'Buy now')),
            'cta_destination' => trim((string) $request->post('cta_destination', '')),
            'alt_text' => trim((string) $request->post('alt_text', '')),
            'audience_rule' => trim((string) $request->post('audience_rule', 'all')),
            'frequency_cap' => (int) $request->post('frequency_cap', 0),
            'starts_at' => $this->toUtc($request->post('starts_at')),
            'ends_at' => $this->toUtc($request->post('ends_at')),
            'status' => in_array($request->post('status'), self::STATUSES, true) ? (string) $request->post('status') : 'draft',
        ];

        // The advanced-only fields (CTA destination, image, alt text) are removed from the
        // simple billboard form, so they arrive empty. Default them defensively so a simple
        // billboard always saves cleanly and never carries stale advanced data.
        if ($kind === 'simple') {
            $input['cta_destination'] = '';
            $input['alt_text'] = '';
            // Simple billboards are always-on — no schedule to avoid an accidental expiry
            // window hiding them from the app.
            $input['starts_at'] = null;
            $input['ends_at'] = null;
        }

        $v = Validator::make($input);
        $v->validate(['name' => 'required|max:120']);

        if ($kind === 'simple') {
            if (!$input['linked_offer_id']) {
                $v->add('linked_offer_id', 'A simple billboard needs a linked offer.');
            } else {
                $offer = Database::fetch("SELECT status FROM " . Database::table('offers') . " WHERE offer_id = ?", [$input['linked_offer_id']]);
                if (!$offer || $offer['status'] !== 'active') {
                    $v->add('linked_offer_id', 'The linked offer must be active.');
                }
            }
            foreach (['tag', 'headline', 'body'] as $f) {
                $bad = BillboardService::unsupportedTokens($input[$f]);
                if ($bad) {
                    $v->add($f, 'Unsupported token(s): ' . implode(', ', $bad));
                }
            }
        } else {
            if ($input['headline'] === '') {
                $v->add('headline', 'Advanced billboards need a headline.');
            }
            if (BillboardService::hasToken($input['headline']) || BillboardService::hasToken($input['body'])) {
                $v->add('headline', 'Advanced billboards must not contain {{tokens}}.');
            }
        }
        if ($v->fails()) {
            Flash::error('Fix: ' . implode(' ', array_values($v->firstErrors())));
            $this->redirect($isNew ? '/billboards/new' : '/billboards/' . $id . '/edit');
        }

        // Handle a securely processed image (advanced only).
        $imageAssetId = $isNew ? null : (Database::scalar('SELECT image_asset_id FROM ' . $this->table() . ' WHERE id = ?', [$id]) ?: null);
        if ($kind === 'advanced' && !empty($_FILES['image'])) {
            $uploadDir = dirname(__DIR__, 2) . '/uploads';
            $res = ImageUploader::handle($_FILES['image'], $uploadDir);
            if (!$res['ok']) {
                Flash::error($res['error']);
                $this->redirect($isNew ? '/billboards/new' : '/billboards/' . $id . '/edit');
            }
            if ($res['assetId'] !== null) {
                $imageAssetId = $res['assetId'];
            }
        }
        // A simple billboard never carries an image (the upload field is not shown), so drop
        // any linked asset — including when an advanced billboard is converted to simple.
        if ($kind === 'simple') {
            $imageAssetId = null;
        }

        $actor = Auth::user()['name'] ?? 'system';
        if ($isNew) {
            Database::run(
                'INSERT INTO ' . $this->table() . '
                    (name, kind, status, priority, linked_offer_id, tag, headline, body, cta_label, cta_destination,
                     image_asset_id, alt_text, audience_rule, frequency_cap, starts_at, ends_at,
                     row_version, created_at, updated_at, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)',
                [
                    $input['name'], $kind, $input['status'], $input['priority'], $input['linked_offer_id'], $input['tag'],
                    $input['headline'], $input['body'], $input['cta_label'], $input['cta_destination'], $imageAssetId,
                    $input['alt_text'], $input['audience_rule'], $input['frequency_cap'], $input['starts_at'], $input['ends_at'], $actor,
                ]
            );
            $id = (int) Database::pdo()->lastInsertId();
        } else {
            Database::run(
                'UPDATE ' . $this->table() . ' SET
                    name=?, kind=?, status=?, priority=?, linked_offer_id=?, tag=?, headline=?, body=?, cta_label=?, cta_destination=?,
                    image_asset_id=?, alt_text=?, audience_rule=?, frequency_cap=?, starts_at=?, ends_at=?,
                    row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = ?',
                [
                    $input['name'], $kind, $input['status'], $input['priority'], $input['linked_offer_id'], $input['tag'],
                    $input['headline'], $input['body'], $input['cta_label'], $input['cta_destination'], $imageAssetId,
                    $input['alt_text'], $input['audience_rule'], $input['frequency_cap'], $input['starts_at'], $input['ends_at'], $actor, $id,
                ]
            );
        }
        $this->writeRevision($id, $isNew ? 'create' : 'update');
        Audit::log(['action' => $isNew ? 'billboard.create' : 'billboard.update', 'entity_type' => 'billboard', 'entity_id' => $id]);
        Flash::success('Billboard saved. Publish to apply.');
        $this->redirect('/billboards');
    }

    public function setStatus(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('billboards.manage');
        $status = in_array($request->post('status'), self::STATUSES, true) ? (string) $request->post('status') : 'draft';
        Database::run('UPDATE ' . $this->table() . ' SET status=?, row_version=row_version+1, updated_at=UTC_TIMESTAMP() WHERE id=?', [$status, (int) $id]);
        $this->writeRevision((int) $id, $status);
        Audit::log(['action' => 'billboard.' . $status, 'entity_type' => 'billboard', 'entity_id' => (int) $id]);
        Flash::success('Billboard set to ' . $status . '. Publish to apply.');
        $this->redirect('/billboards');
    }

    public function delete(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('billboards.manage');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) { $this->redirect('/billboards'); }
        // Any billboard can be deleted (owner request). Publish afterwards so the app
        // stops showing it; a deleted advert simply drops out of the next snapshot.
        Database::run('DELETE FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        Audit::log(['action' => 'billboard.delete', 'entity_type' => 'billboard', 'entity_id' => (int) $id, 'before' => $row]);
        Flash::success('Billboard advert deleted. Publish to apply in the app.');
        $this->redirect('/billboards');
    }

    /* helpers */
    private function toUtc($local): ?string
    {
        $local = trim((string) $local);
        if ($local === '') { return null; }
        try {
            return (new \DateTimeImmutable($local, new \DateTimeZone('Africa/Nairobi')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) { return null; }
    }

    private function writeRevision(int $id, string $action): void
    {
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
        Database::run(
            'INSERT INTO ' . Database::table('billboard_revisions') . ' (billboard_id, snapshot_json, action, actor_name, created_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$id, json_encode($row), $action, Auth::user()['name'] ?? 'system']
        );
    }
}
