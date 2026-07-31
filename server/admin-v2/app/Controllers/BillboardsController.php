<?php
/**
 * Billboard adverts: simple (generated from a linked offer with validated tokens) and
 * advanced (literal copy + securely processed media). A simple billboard whose linked
 * offer becomes unavailable is not published. Personalisation runs automatically in the
 * app — there are no scoring controls here.
 *
 * Media, ordering and tap targets:
 *  - media_type/thumb_asset_id come from ImageUploader (PNG/JPEG/WebP/GIF, animated GIFs
 *    stored untouched with a still first-frame thumbnail).
 *  - display_order drives ordering (lowest first); priority stays as the tie-break.
 *  - target_action declares what a tap does; cta_label/cta_destination keep working for
 *    the shipped app.
 *  - enabled is an explicit on/off that is independent of status. The state an operator
 *    sees is DERIVED from the dates (BillboardService::effectiveState), so a scheduled
 *    advert goes live on its own.
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
    private function assetsTable(): string { return Database::table('billboard_assets'); }

    public function index(Request $request): void
    {
        $this->guard('billboards.manage');
        $rows = Database::fetchAll(
            'SELECT b.*,
                    a.stored_name AS image_name, a.kind AS image_kind, a.is_animated AS image_animated,
                    a.width AS image_width, a.height AS image_height,
                    COALESCE(t.stored_name, a.thumb_name, \'\') AS thumb_name
               FROM ' . $this->table() . ' b
               LEFT JOIN ' . $this->assetsTable() . ' a ON a.id = b.image_asset_id
               LEFT JOIN ' . $this->assetsTable() . ' t ON t.id = b.thumb_asset_id
              ORDER BY b.display_order ASC, b.priority ASC, b.id DESC'
        );

        // The state shown is recomputed on every render, never read from a stale column.
        $now = nairobi_now();
        foreach ($rows as $i => $row) {
            $rows[$i]['effective_state'] = BillboardService::effectiveState($row, $now);
        }

        $q = trim((string) $request->get('q', ''));
        $state = (string) $request->get('state', '');
        $filtered = [];
        foreach ($rows as $row) {
            if ($q !== '' && stripos($row['name'] . ' ' . $row['headline'] . ' ' . (string) $row['linked_offer_id'], $q) === false) {
                continue;
            }
            if ($state !== '' && $row['effective_state'] !== $state) {
                continue;
            }
            $filtered[] = $row;
        }

        $this->view('billboards/index', [
            'activeNav' => 'billboards', 'pageTitle' => 'Billboard adverts',
            'billboards' => $filtered, 'total' => count($rows), 'q' => $q, 'state' => $state,
        ]);
    }

    public function calendar(Request $request): void
    {
        $this->guard('billboards.manage');
        $rows = Database::fetchAll('SELECT * FROM ' . $this->table() . ' ORDER BY display_order ASC, priority ASC, id ASC');
        $now = nairobi_now();
        $scheduled = [];
        $alwaysOn = [];
        foreach ($rows as $row) {
            $row['effective_state'] = BillboardService::effectiveState($row, $now);
            // Simple billboards deliberately ignore their window (always-on), so they are
            // listed separately instead of pretending to have a schedule.
            if ($row['kind'] === 'advanced' && ($row['starts_at'] || $row['ends_at'])) {
                $scheduled[] = $row;
            } else {
                $alwaysOn[] = $row;
            }
        }
        usort($scheduled, static fn($a, $b) => strcmp((string) $a['starts_at'], (string) $b['starts_at']));

        $this->view('billboards/calendar', [
            'activeNav' => 'billboards', 'pageTitle' => 'Billboard schedule',
            'items' => $scheduled, 'alwaysOn' => $alwaysOn,
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
        $thumb = null;
        if ($row && !empty($row['image_asset_id'])) {
            $image = Database::fetch('SELECT * FROM ' . $this->assetsTable() . ' WHERE id = ?', [(int) $row['image_asset_id']]);
        }
        if ($row && !empty($row['thumb_asset_id'])) {
            $thumb = Database::fetch('SELECT * FROM ' . $this->assetsTable() . ' WHERE id = ?', [(int) $row['thumb_asset_id']]);
        }
        $this->view('billboards/form', [
            'activeNav' => 'billboards', 'pageTitle' => $isNew ? 'New billboard' : 'Edit billboard',
            'b' => $row, 'isNew' => $isNew, 'image' => $image, 'thumb' => $thumb,
            'tokens' => BillboardService::TOKENS,
            'categories' => $this->categoryOptions(),
            'maxImageMb' => ImageUploader::humanBytes(ImageUploader::MAX_BYTES_IMAGE),
            'maxGifMb' => ImageUploader::humanBytes(ImageUploader::MAX_BYTES_GIF),
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
        $before = $isNew ? null : Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
        if (!$isNew && !$before) {
            Flash::error('Billboard not found.');
            $this->redirect('/billboards');
        }

        $targetAction = (string) $request->post('target_action', 'none');
        if (!in_array($targetAction, BillboardService::TARGET_ACTIONS, true)) {
            $targetAction = 'none';
        }

        $input = [
            'name' => trim((string) $request->post('name', '')),
            'kind' => $kind,
            'priority' => (int) $request->post('priority', 5),
            'display_order' => max(0, (int) $request->post('display_order', 0)),
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
            'enabled' => $request->post('enabled') === '1' ? 1 : 0,
            'target_action' => $targetAction,
            'click_url' => trim((string) $request->post('click_url', '')),
            'internal_action' => trim((string) $request->post('internal_action', '')),
            'target_category' => trim((string) $request->post('target_category', '')),
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
        // Never store a tap target the chosen action does not use.
        if ($input['target_action'] !== 'url')      { $input['click_url'] = ''; }
        if ($input['target_action'] !== 'internal') { $input['internal_action'] = ''; }
        if ($input['target_action'] !== 'category') { $input['target_category'] = ''; }

        $v = Validator::make($input);
        $v->validate([
            'name' => 'required|max:120',
            'click_url' => 'maxlen:255',
            'internal_action' => 'maxlen:60',
            'alt_text' => 'maxlen:160',
        ]);

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
            if ($input['starts_at'] !== null && $input['ends_at'] !== null && $input['ends_at'] <= $input['starts_at']) {
                $v->add('ends_at', 'The end date must come after the start date.');
            }
        }

        // What happens when someone taps the advert.
        $categoryKeys = array_column($this->categoryOptions(), 'category_key');
        $offerExists = $input['linked_offer_id'] !== null && Database::fetch(
            "SELECT offer_id FROM " . Database::table('offers') . " WHERE offer_id = ?",
            [$input['linked_offer_id']]
        ) !== null;
        foreach (BillboardService::validateTarget($input, $categoryKeys, $offerExists) as $i => $message) {
            $v->add('target_action_' . $i, $message);
        }

        if ($v->fails()) {
            Flash::error('Fix: ' . implode(' ', array_values($v->firstErrors())));
            $this->redirect($isNew ? '/billboards/new' : '/billboards/' . $id . '/edit');
        }

        /* ---------------------------------------------------------------- media -- */
        $imageAssetId = $isNew ? null : ($before['image_asset_id'] ?? null);
        $thumbAssetId = $isNew ? null : ($before['thumb_asset_id'] ?? null);
        $uploaded = null;
        if ($kind === 'advanced' && $request->post('remove_image') === '1') {
            $imageAssetId = null;
            $thumbAssetId = null;
        }
        if ($kind === 'advanced' && !empty($_FILES['image'])) {
            $uploadDir = dirname(__DIR__, 2) . '/uploads';
            $res = ImageUploader::handle($_FILES['image'], $uploadDir);
            if (!$res['ok']) {
                Flash::error($res['error']);
                $this->redirect($isNew ? '/billboards/new' : '/billboards/' . $id . '/edit');
            }
            if ($res['assetId'] !== null) {
                $imageAssetId = $res['assetId'];
                $thumbAssetId = $res['thumbAssetId'];
                $uploaded = [
                    'asset_id' => $res['assetId'], 'thumb_asset_id' => $res['thumbAssetId'],
                    'media_type' => $res['mediaType'], 'animated' => $res['isAnimated'],
                    'frames' => $res['frameCount'],
                ];
            }
        }
        // A simple billboard never carries an image (the upload field is not shown), so drop
        // any linked asset — including when an advanced billboard is converted to simple.
        if ($kind === 'simple') {
            $imageAssetId = null;
            $thumbAssetId = null;
        }
        $mediaType = $this->mediaTypeFor($imageAssetId);

        $actor = Auth::user()['name'] ?? 'system';
        $values = [
            $input['name'], $kind, $input['status'], $input['priority'], $input['display_order'],
            $input['linked_offer_id'], $input['tag'], $input['headline'], $input['body'],
            $input['cta_label'], $input['cta_destination'],
            $imageAssetId, $thumbAssetId, $mediaType, $input['alt_text'],
            $input['audience_rule'], $input['frequency_cap'], $input['starts_at'], $input['ends_at'],
            $input['target_action'], $input['click_url'], $input['internal_action'], $input['target_category'],
            $input['enabled'], $actor,
        ];
        if ($isNew) {
            Database::run(
                'INSERT INTO ' . $this->table() . '
                    (name, kind, status, priority, display_order, linked_offer_id, tag, headline, body,
                     cta_label, cta_destination, image_asset_id, thumb_asset_id, media_type, alt_text,
                     audience_rule, frequency_cap, starts_at, ends_at,
                     target_action, click_url, internal_action, target_category, enabled,
                     row_version, created_at, updated_at, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)',
                $values
            );
            $id = (int) Database::pdo()->lastInsertId();
        } else {
            $values[] = $id;
            Database::run(
                'UPDATE ' . $this->table() . ' SET
                    name=?, kind=?, status=?, priority=?, display_order=?, linked_offer_id=?, tag=?, headline=?, body=?,
                    cta_label=?, cta_destination=?, image_asset_id=?, thumb_asset_id=?, media_type=?, alt_text=?,
                    audience_rule=?, frequency_cap=?, starts_at=?, ends_at=?,
                    target_action=?, click_url=?, internal_action=?, target_category=?, enabled=?,
                    row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = ?',
                $values
            );
        }
        $after = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
        $this->writeRevision($id, $isNew ? 'create' : 'update');
        if ($uploaded !== null) {
            Audit::log([
                'action' => 'billboard.upload', 'entity_type' => 'billboard',
                'entity_id' => (string) $id, 'after' => $uploaded,
            ]);
        }
        Audit::log([
            'action' => $isNew ? 'billboard.create' : 'billboard.update',
            'entity_type' => 'billboard', 'entity_id' => (string) $id,
            'before' => $before, 'after' => $after,
        ]);
        Flash::success('Billboard saved. Publish to apply.');
        $this->redirect('/billboards');
    }

    /**
     * Status changes and the explicit on/off switch share this one route: a request
     * carrying `enabled` flips the switch, anything else sets the status.
     */
    public function setStatus(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('billboards.manage');
        $id = (int) $id;
        $before = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
        if (!$before) { Flash::error('Billboard not found.'); $this->redirect('/billboards'); }

        $enabledRaw = $request->post('enabled');
        if ($enabledRaw !== null) {
            $enabled = $enabledRaw === '1' ? 1 : 0;
            Database::run('UPDATE ' . $this->table() . ' SET enabled=?, row_version=row_version+1, updated_at=UTC_TIMESTAMP() WHERE id=?', [$enabled, $id]);
            $after = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
            $this->writeRevision($id, $enabled === 1 ? 'enable' : 'disable');
            Audit::log([
                'action' => $enabled === 1 ? 'billboard.enable' : 'billboard.disable',
                'entity_type' => 'billboard', 'entity_id' => (string) $id,
                'before' => $before, 'after' => $after,
            ]);
            Flash::success($enabled === 1 ? 'Billboard switched on. Publish to apply.' : 'Billboard switched off. Publish to apply.');
            $this->redirect('/billboards');
        }

        $status = in_array($request->post('status'), self::STATUSES, true) ? (string) $request->post('status') : 'draft';
        Database::run('UPDATE ' . $this->table() . ' SET status=?, row_version=row_version+1, updated_at=UTC_TIMESTAMP() WHERE id=?', [$status, $id]);
        $after = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
        $this->writeRevision($id, $status);
        Audit::log([
            'action' => 'billboard.update', 'entity_type' => 'billboard', 'entity_id' => (string) $id,
            'before' => $before, 'after' => $after, 'reason' => 'status → ' . $status,
        ]);
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
        Audit::log([
            'action' => 'billboard.delete', 'entity_type' => 'billboard',
            'entity_id' => (string) (int) $id, 'before' => $row, 'after' => null,
        ]);
        Flash::success('Billboard advert deleted. Publish to apply in the app.');
        $this->redirect('/billboards');
    }

    /* helpers */

    /** Media the billboard draws, taken from the stored asset (never from the form). */
    private function mediaTypeFor($assetId): string
    {
        if (!$assetId) {
            return 'none';
        }
        $asset = Database::fetch('SELECT kind, is_animated FROM ' . $this->assetsTable() . ' WHERE id = ?', [(int) $assetId]);
        if (!$asset) {
            return 'none';
        }
        return ((int) $asset['is_animated'] === 1 || $asset['kind'] === 'gif') ? 'gif' : 'image';
    }

    /**
     * Category keys an advert may open. Read from the managed category table so a renamed
     * or switched-off category is respected; falls back to the categories active offers
     * actually use when that table is not present yet.
     *
     * @return array<int, array{category_key:string, label:string}>
     */
    private function categoryOptions(): array
    {
        try {
            if (Database::tableExists('offer_categories')) {
                $rows = Database::fetchAll(
                    'SELECT category_key, label FROM ' . Database::table('offer_categories') . '
                      WHERE enabled = 1 ORDER BY sort_order ASC, category_key ASC'
                );
                if ($rows) {
                    return $rows;
                }
            }
        } catch (\Throwable $e) {
            // fall through to the offer-derived list
        }
        try {
            $rows = Database::fetchAll(
                "SELECT DISTINCT category FROM " . Database::table('offers') . "
                  WHERE category <> '' ORDER BY category ASC"
            );
            return array_map(static fn($r) => ['category_key' => $r['category'], 'label' => $r['category']], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

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
            [$id, json_encode($row), substr($action, 0, 16), Auth::user()['name'] ?? 'system']
        );
    }
}
