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
        <th>ID</th><th>Category</th><th>Name</th><th>Price</th><th>Validity</th><th>Rule</th><th>Status</th><th></th>
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
            <td><span class="status <?= e($o['status']) ?>"><?= ucfirst($o['status']) ?></span></td>
            <td>
              <div class="row-actions">
                <?php if (can('offers.edit')): ?><a class="btn btn--secondary btn--sm" href="<?= e(url('/offers/' . $o['offer_id'] . '/edit')) ?>"><?= icon('edit', 14) ?></a><?php endif; ?>
                <?php if (can('offers.create')): ?>
                  <form method="post" action="<?= e(url('/offers/' . $o['offer_id'] . '/duplicate')) ?>" data-confirm="Create a draft copy of this offer with a new ID?">
                    <?= App\Core\Csrf::field() ?><button class="btn btn--ghost btn--sm" title="Duplicate"><?= icon('copy', 14) ?></button>
                  </form>
                <?php endif; ?>
                <?php if (can('offers.archive') && $o['status'] !== 'archived'): ?>
                  <form method="post" action="<?= e(url('/offers/' . $o['offer_id'] . '/archive')) ?>" data-confirm="Archive this offer? It stays in history but leaves the app on next publish.">
                    <?= App\Core\Csrf::field() ?><button class="btn btn--ghost btn--sm" title="Archive"><?= icon('archive', 14) ?></button>
                  </form>
                <?php elseif (can('offers.archive') && $o['status'] === 'archived'): ?>
                  <form method="post" action="<?= e(url('/offers/' . $o['offer_id'] . '/restore')) ?>">
                    <?= App\Core\Csrf::field() ?><button class="btn btn--ghost btn--sm" title="Restore"><?= icon('restore', 14) ?></button>
                  </form>
                <?php endif; ?>
                <?php if (can('offers.delete') && $o['status'] === 'draft'): ?>
                  <form method="post" action="<?= e(url('/offers/' . $o['offer_id'] . '/delete')) ?>" data-confirm="Permanently delete this draft offer? This cannot be undone.">
                    <?= App\Core\Csrf::field() ?><button class="btn btn--danger btn--sm" title="Delete"><?= icon('trash', 14) ?></button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$offers): ?>
          <tr><td colspan="8"><div class="empty"><?= icon('offers', 32) ?><h3>No offers match</h3><p>Adjust the filters or add a new offer.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
