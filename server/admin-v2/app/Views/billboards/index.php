<?php
use App\Services\BillboardService;

$states = [
    '' => 'Any state',
    'live' => 'Live now',
    'scheduled' => 'Scheduled',
    'ended' => 'Ended',
    'draft' => 'Draft',
    'disabled' => 'Off',
];
?>
<style>
  .bb-thumb { width: 64px; height: 40px; border-radius: 6px; object-fit: cover; border: 1px solid var(--divider); background: var(--grouped); display: block; }
  .bb-thumb--none { display: flex; align-items: center; justify-content: center; color: var(--text-2); }
  .bb-media { display: flex; align-items: center; gap: 8px; }
  .bb-order { font-variant-numeric: tabular-nums; }
  .bb-name { display: flex; flex-direction: column; gap: 2px; }
  .bb-switch-form { display: inline-flex; }
</style>

<div class="page-head">
  <div>
    <h1>Billboard adverts</h1>
    <div class="sub">Simple (offer-linked) or advanced (image or animated GIF) adverts. Publish changes to apply them.</div>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--secondary" href="<?= e(url('/billboards/calendar')) ?>"><?= icon('calendar', 18) ?> Schedule</a>
    <a class="btn btn--secondary" href="<?= e(url('/billboards/import')) ?>"><?= icon('upload', 18) ?> Import JSON</a>
    <a class="btn" href="<?= e(url('/billboards/new')) ?>"><?= icon('plus', 18) ?> New billboard</a>
  </div>
</div>

<div class="alert info">
  <?= icon('info', 18) ?>
  <div>
    <b>Order:</b> the lowest order number shows first; adverts with the same number fall back to priority.
    <b>State</b> is worked out from the dates in Nairobi time, so a scheduled advert goes live on its own —
    you do not need to switch anything on when its start time arrives.
  </div>
</div>

<form class="filters" method="get" action="<?= e(url('/billboards')) ?>">
  <div class="field">
    <label for="bb-q">Search</label>
    <input id="bb-q" type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Name, title or offer">
  </div>
  <div class="field">
    <label for="bb-state">State</label>
    <select id="bb-state" name="state">
      <?php foreach ($states as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= ($state ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn--secondary btn--sm" type="submit"><?= icon('filter', 16) ?> Apply</button>
  <?php if (($q ?? '') !== '' || ($state ?? '') !== ''): ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/billboards')) ?>"><?= icon('close', 16) ?> Clear</a>
  <?php endif; ?>
  <span class="muted small"><?= (int) count($billboards) ?> of <?= (int) ($total ?? count($billboards)) ?></span>
</form>

<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Advert</th><th>Media</th><th>Title</th><th class="nowrap">Order</th>
          <th>Window</th><th>State</th><th>On</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($billboards as $b): ?>
          <?php
            $state_ = (string) ($b['effective_state'] ?? 'draft');
            $thumbFile = (string) ($b['thumb_name'] ?? '');
            $imageFile = (string) ($b['image_name'] ?? '');
            $previewFile = $thumbFile !== '' ? $thumbFile : $imageFile;
            $animated = (int) ($b['image_animated'] ?? 0) === 1;
            $isOn = (int) ($b['enabled'] ?? 1) === 1;
          ?>
          <tr>
            <td>
              <div class="bb-name">
                <b><?= e($b['name']) ?></b>
                <span class="muted small"><?= e(ucfirst((string) $b['kind'])) ?><?= $b['linked_offer_id'] ? ' · ' : '' ?><?php if ($b['linked_offer_id']): ?><span class="mono"><?= e($b['linked_offer_id']) ?></span><?php endif; ?></span>
              </div>
            </td>
            <td>
              <div class="bb-media">
                <?php if ($previewFile !== ''): ?>
                  <img class="bb-thumb" src="<?= e(url('/uploads/' . $previewFile)) ?>" alt="<?= e($b['alt_text'] ?: 'Advert image') ?>" loading="lazy" width="64" height="40">
                <?php else: ?>
                  <span class="bb-thumb bb-thumb--none" title="No image"><?= icon('image', 16) ?></span>
                <?php endif; ?>
                <?php if ($animated): ?><span class="tag minutes">Animated</span><?php endif; ?>
              </div>
            </td>
            <td class="small"><?= e(mb_strimwidth((string) ($b['headline'] ?: '(generated from offer)'), 0, 40, '…')) ?></td>
            <td class="bb-order nowrap"><?= (int) $b['display_order'] ?> <span class="muted small">/ p<?= (int) $b['priority'] ?></span></td>
            <td class="small muted nowrap">
              <?php if ($b['kind'] !== 'advanced'): ?>
                Always on
              <?php else: ?>
                <?= $b['starts_at'] ? e(fmt_nairobi($b['starts_at'], 'd M H:i')) : '—' ?><?= $b['ends_at'] ? ' → ' . e(fmt_nairobi($b['ends_at'], 'd M H:i')) : '' ?>
              <?php endif; ?>
            </td>
            <td><span class="status <?= e(BillboardService::stateClass($state_)) ?>"><?= e(BillboardService::stateLabel($state_)) ?></span></td>
            <td>
              <form class="bb-switch-form" method="post" action="<?= e(url('/billboards/' . (int) $b['id'] . '/status')) ?>">
                <?= App\Core\Csrf::field() ?>
                <input type="hidden" name="enabled" value="<?= $isOn ? '0' : '1' ?>">
                <button class="btn btn--ghost btn--sm" title="<?= $isOn ? 'Switch this advert off' : 'Switch this advert on' ?>">
                  <?= icon('toggle', 16) ?> <?= $isOn ? 'On' : 'Off' ?>
                </button>
              </form>
            </td>
            <td>
              <div class="row-actions">
                <a class="btn btn--secondary btn--sm" href="<?= e(url('/billboards/' . (int) $b['id'] . '/edit')) ?>" title="Edit"><?= icon('edit', 14) ?></a>
                <?php if ($b['status'] !== 'active'): ?>
                  <form method="post" action="<?= e(url('/billboards/' . (int) $b['id'] . '/status')) ?>"><?= App\Core\Csrf::field() ?><input type="hidden" name="status" value="active"><button class="btn btn--ghost btn--sm" title="Set status to active"><?= icon('check', 14) ?></button></form>
                <?php else: ?>
                  <form method="post" action="<?= e(url('/billboards/' . (int) $b['id'] . '/status')) ?>"><?= App\Core\Csrf::field() ?><input type="hidden" name="status" value="paused"><button class="btn btn--ghost btn--sm" title="Pause"><?= icon('close', 14) ?></button></form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/billboards/' . (int) $b['id'] . '/delete')) ?>" data-confirm="Delete this billboard advert? This cannot be undone."><?= App\Core\Csrf::field() ?><button class="btn btn--danger btn--sm" title="Delete"><?= icon('trash', 14) ?></button></form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$billboards): ?>
          <tr><td colspan="8"><div class="empty"><?= icon('billboards', 32) ?><h3>No billboards</h3><p><?= ($q ?? '') !== '' || ($state ?? '') !== '' ? 'Nothing matches this filter.' : 'Create a simple or advanced advert.' ?></p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
