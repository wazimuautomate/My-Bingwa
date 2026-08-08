<?php
use App\Repositories\OfferRepository;
$qs = http_build_query(array_filter($filters));
?>
<div class="page-head">
  <div>
    <h1>Offers</h1>
    <div class="sub">The catalogue the app syncs. Publish changes to apply them.</div>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--secondary" href="<?= e(url('/offers/export' . ($qs ? '?' . $qs : ''))) ?>"><?= icon('download', 18) ?> Export CSV</a>
    <?php if (can('offers.create')): ?><a class="btn" href="<?= e(url('/offers/new')) ?>"><?= icon('plus', 18) ?> Add offer</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <form class="filters" method="get" action="<?= e(url('/offers')) ?>">
    <div class="field"><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search id or name"></div>
    <div class="field"><select name="category"><option value="">All categories</option>
      <?php foreach (OfferRepository::CATEGORIES as $c): ?><option value="<?= $c ?>" <?= $filters['category'] === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><select name="status"><option value="">Any status</option>
      <?php foreach (['active', 'draft', 'archived'] as $s): ?><option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select></div>
    <button class="btn btn--secondary btn--sm" type="submit"><?= icon('filter', 16) ?> Filter</button>
    <?php if ($qs): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('/offers')) ?>">Clear</a><?php endif; ?>
  </form>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>ID</th><th>Category</th><th>Name</th><th>Price</th><th>Validity</th><th>Rule</th><th>Sells</th><th>Status</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($offers as $o): ?>
          <tr>
            <td class="mono"><?= e($o['offer_id']) ?></td>
            <td><span class="tag <?= strtolower($o['category']) ?>"><?= e($o['category']) ?></span></td>
            <td><?= e($o['name']) ?><?= $o['commercial_tag'] ? ' <span class="tag minutes">' . e($o['commercial_tag']) . '</span>' : '' ?></td>
            <td class="nowrap"><?= e(ksh($o['price'])) ?></td>
            <td class="muted"><?= e($o['validity']) ?></td>
            <td class="muted small"><?= e(OfferRepository::RULES[$o['daily_rule']] ?? $o['daily_rule']) ?></td>
            <?php
              // The time-of-day window Safaricom sells this offer in. Blank ends
              // mean "all day", which is what the app shows too.
              $wFrom = OfferRepository::hhmm($o['available_from'] ?? null);
              $wTo   = OfferRepository::hhmm($o['available_to'] ?? null);
            ?>
            <td class="muted small nowrap"><?= ($wFrom !== '' && $wTo !== '') ? e($wFrom . ' – ' . $wTo) : 'All day' ?></td>
            <td><span class="status <?= e($o['status']) ?>"><?= ucfirst($o['status']) ?></span></td>
            <td>
              <div class="dropdown">
                <button class="btn btn--ghost btn--sm" data-dropdown="offer-menu-<?= e($o['offer_id']) ?>" aria-haspopup="true" aria-label="Offer actions"><?= icon('more', 16) ?></button>
                <div class="dropdown-menu dropdown-menu--fixed" id="offer-menu-<?= e($o['offer_id']) ?>">
                  <button type="button" data-modal-open="#offer-modal-<?= e($o['offer_id']) ?>"><?= icon('eye', 18) ?> View</button>
                  <?php if (can('offers.edit')): ?><a href="<?= e(url('/offers/' . $o['offer_id'] . '/edit')) ?>"><?= icon('edit', 18) ?> Edit</a><?php endif; ?>
                  <?php if (can('offers.create')): ?>
                    <form method="post" action="<?= e(url('/offers/' . $o['offer_id'] . '/duplicate')) ?>" data-confirm="Create a draft copy of this offer with a new ID?">
                      <?= App\Core\Csrf::field() ?><button type="submit"><?= icon('copy', 18) ?> Duplicate</button>
                    </form>
                  <?php endif; ?>
                  <?php if (can('offers.archive') && $o['status'] !== 'archived'): ?>
                    <form method="post" action="<?= e(url('/offers/' . $o['offer_id'] . '/archive')) ?>" data-confirm="Archive this offer? It stays in history but leaves the app on next publish.">
                      <?= App\Core\Csrf::field() ?><button type="submit"><?= icon('archive', 18) ?> Archive</button>
                    </form>
                  <?php elseif (can('offers.archive') && $o['status'] === 'archived'): ?>
                    <form method="post" action="<?= e(url('/offers/' . $o['offer_id'] . '/restore')) ?>">
                      <?= App\Core\Csrf::field() ?><button type="submit"><?= icon('restore', 18) ?> Restore</button>
                    </form>
                  <?php endif; ?>
                  <?php if (can('offers.delete') && $o['status'] === 'draft'): ?>
                    <div class="divider"></div>
                    <form method="post" action="<?= e(url('/offers/' . $o['offer_id'] . '/delete')) ?>" data-confirm="Permanently delete this draft offer? This cannot be undone.">
                      <?= App\Core\Csrf::field() ?><button type="submit" class="danger"><?= icon('trash', 18) ?> Delete</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$offers): ?>
          <tr><td colspan="9"><div class="empty"><?= icon('offers', 32) ?><h3>No offers match</h3><p>Adjust the filters or add a new offer.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php foreach ($offers as $o): ?>
    <div class="modal-backdrop" data-modal id="offer-modal-<?= e($o['offer_id']) ?>" role="dialog" aria-modal="true" aria-label="Offer <?= e($o['offer_id']) ?> details">
      <div class="modal modal--lg">
        <div class="card__head"><h3><?= e($o['name']) ?></h3><span class="spacer"></span><span class="status <?= e($o['status']) ?>"><?= ucfirst($o['status']) ?></span></div>
        <div class="stack" style="gap:8px">
          <div class="between"><span class="muted small">Offer ID</span><span class="mono small"><?= e($o['offer_id']) ?></span></div>
          <div class="between"><span class="muted small">Category</span><span class="tag <?= strtolower($o['category']) ?>"><?= e($o['category']) ?></span></div>
          <div class="between"><span class="muted small">Price</span><b><?= e(ksh($o['price'])) ?></b></div>
          <div class="between"><span class="muted small">Validity</span><span><?= e($o['validity']) ?> <span class="muted small">(<?= e($o['band']) ?>)</span></span></div>
          <div class="between"><span class="muted small">Daily rule</span><span class="small"><?= e(OfferRepository::RULES[$o['daily_rule']] ?? $o['daily_rule']) ?></span></div>
          <?php if (!empty($o['max_per_day'])): ?><div class="between"><span class="muted small">Max per day</span><span><?= (int) $o['max_per_day'] ?></span></div><?php endif; ?>
          <?php $mFrom = OfferRepository::hhmm($o['available_from'] ?? null); $mTo = OfferRepository::hhmm($o['available_to'] ?? null); ?>
          <div class="between"><span class="muted small">Sells between</span><span class="small"><?= ($mFrom !== '' && $mTo !== '') ? e($mFrom . ' – ' . $mTo) . ' <span class="muted">Nairobi</span>' : 'Any time' ?></span></div>
          <div class="between"><span class="muted small">Commercial tag</span><span><?= e($o['commercial_tag'] ?: '—') ?></span></div>
          <div class="between"><span class="muted small">Offline eligible</span><span><?= ((int) $o['offline_eligible'] === 1) ? 'Yes' : 'No' ?></span></div>
          <div class="between"><span class="muted small">Restrictions</span><span class="small"><?= e($o['restrictions'] ?: '—') ?></span></div>
          <div class="between"><span class="muted small">Campaign starts</span><span class="small"><?= e($o['starts_at'] ? fmt_nairobi($o['starts_at']) : '—') ?></span></div>
          <div class="between"><span class="muted small">Campaign ends</span><span class="small"><?= e($o['ends_at'] ? fmt_nairobi($o['ends_at']) : '—') ?></span></div>
          <div class="between"><span class="muted small">Sort hint</span><span><?= (int) ($o['sort_hint'] ?? 0) ?></span></div>
          <div class="between"><span class="muted small">Last updated</span><span class="small"><?= e(fmt_nairobi($o['updated_at'] ?? null)) ?><?= !empty($o['updated_by']) ? ' · ' . e($o['updated_by']) : '' ?></span></div>
        </div>
        <div class="modal__actions">
          <?php if (can('offers.edit')): ?><a class="btn btn--secondary" href="<?= e(url('/offers/' . $o['offer_id'] . '/edit')) ?>">Edit</a><?php endif; ?>
          <button class="btn" type="button" data-modal-close>Close</button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
