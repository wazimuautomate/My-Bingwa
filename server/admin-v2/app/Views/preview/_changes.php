<?php
/**
 * Shared renderer for a grouped change list (Preview, publish review, release detail).
 *
 * One module = one collapsed <details>. Collapsed is the point: an operator opens the two
 * groups they care about instead of scrolling a flat list of every entity in the product.
 * Inside a group each item shows its name, what happened to it, and the exact field values
 * that moved — nothing else.
 *
 * Included with require_once from a view, so the markup exists in one place only. Uses
 * native <details>/<summary>; there is no JavaScript here.
 */

if (!function_exists('mb_change_group_styles')) {
    /** Page-scoped styles, emitted once per request. */
    function mb_change_group_styles(): string
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;
        return <<<'CSS'
<style>
.chg { border: 1px solid var(--divider); border-radius: var(--radius-sm); margin-bottom: 10px; background: var(--surface); }
.chg > summary { display: flex; align-items: center; gap: 10px; padding: 12px 14px; cursor: pointer; list-style: none; font-weight: 600; font-size: 14px; }
.chg > summary::-webkit-details-marker { display: none; }
.chg > summary:hover { background: var(--grouped); }
.chg__caret { color: var(--text-2); transition: transform .16s ease; flex: none; }
.chg[open] > summary .chg__caret { transform: rotate(90deg); }
.chg__spacer { flex: 1; }
.chg__body { padding: 0 14px 12px; border-top: 1px dashed var(--divider); }
.chg__item { padding: 12px 0; border-bottom: 1px dashed var(--divider); }
.chg__item:last-child { border-bottom: 0; }
.chg__title { font-weight: 600; font-size: 13.5px; }
.chg__badge { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; flex: none; }
.chg__badge.added { background: var(--success-soft); color: var(--success); }
.chg__badge.changed { background: var(--warning-soft); color: var(--warning); }
.chg__badge.removed { background: var(--error-soft); color: var(--error); }
.chg__fields { list-style: none; margin: 8px 0 0; padding: 0; display: flex; flex-direction: column; gap: 5px; }
.chg__fields li { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px; font-size: 12.5px; }
.chg__field { min-width: 150px; color: var(--text-2); font-weight: 600; }
.chg__from { text-decoration: line-through; color: var(--text-2); }
.chg__arrow { color: var(--text-2); }
.chg__to { font-weight: 600; }
.chg__more { font-size: 12px; color: var(--text-2); margin-top: 6px; }
.res-strip { display: flex; flex-wrap: wrap; gap: 8px; }
.res-chip { display: inline-flex; align-items: baseline; gap: 7px; padding: 6px 11px; border-radius: 999px; border: 1px solid var(--divider); font-size: 12.5px; }
.res-chip b { font-weight: 700; }
.res-chip.is-moving { border-color: var(--warning); background: var(--warning-soft); }
@media (max-width: 720px) {
  .chg__field { min-width: 0; width: 100%; }
}
</style>
CSS;
    }
}

if (!function_exists('mb_change_field_rows')) {
    /** The "Price  KSh 19 → KSh 25" lines for one item. */
    function mb_change_field_rows(array $item, int $limit = 8): string
    {
        $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];
        if ($fields === []) {
            return '';
        }
        $type = (string) ($item['change_type'] ?? 'changed');
        $shown = array_slice($fields, 0, $limit);
        $html = '<ul class="chg__fields">';
        foreach ($shown as $f) {
            $name  = (string) ($f['field'] ?? '');
            $label = (string) ($f['label'] ?? $name);
            $from  = \App\Services\ChangeDetector::displayValue($f['from'] ?? null, $name);
            $to    = \App\Services\ChangeDetector::displayValue($f['to'] ?? null, $name);
            $html .= '<li><span class="chg__field">' . e($label) . '</span>';
            if ($type === 'added') {
                $html .= '<span class="chg__to">' . e($to) . '</span>';
            } elseif ($type === 'removed') {
                $html .= '<span class="chg__from">' . e($from) . '</span>';
            } else {
                $html .= '<span class="chg__from">' . e($from) . '</span>'
                       . '<span class="chg__arrow">&rarr;</span>'
                       . '<span class="chg__to">' . e($to) . '</span>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        $extra = count($fields) - count($shown);
        if ($extra > 0) {
            $html .= '<div class="chg__more">and ' . (int) $extra . ' more field' . ($extra === 1 ? '' : 's') . '.</div>';
        }
        return $html;
    }
}

if (!function_exists('mb_change_groups')) {
    /**
     * @param array $groups moduleKey => ['label'=>string,'count'=>int,'items'=>array]
     * @param array $opts   ['open'=>bool, 'moduleLinks'=>bool]
     */
    function mb_change_groups(array $groups, array $opts = []): string
    {
        $open = !empty($opts['open']);
        $links = !empty($opts['moduleLinks']);
        $html = '';
        foreach ($groups as $key => $group) {
            $count = (int) ($group['count'] ?? 0);
            $html .= '<details class="chg"' . ($open ? ' open' : '') . '>';
            $html .= '<summary>' . icon('chevron', 16, 'chg__caret')
                   . '<span>' . e((string) ($group['label'] ?? $key)) . '</span>'
                   . '<span class="tag muted">' . $count . '</span>'
                   . '<span class="chg__spacer"></span>'
                   . '</summary><div class="chg__body">';
            if ($links) {
                $html .= '<div class="chg__more"><a href="' . e(url('/preview/diff?module=' . rawurlencode((string) $key))) . '">'
                       . 'Show only ' . e((string) ($group['label'] ?? $key)) . '</a></div>';
            }
            foreach (($group['items'] ?? []) as $item) {
                $type = (string) ($item['change_type'] ?? 'changed');
                $label = (string) ($item['entity_label'] ?? '');
                if ($label === '') {
                    $label = (string) ($item['summary'] ?? $item['entity_id'] ?? '');
                }
                $html .= '<div class="chg__item"><div class="between">'
                       . '<span class="chg__title">' . e($label) . '</span>'
                       . '<span class="chg__badge ' . e($type) . '">' . e(ucfirst($type)) . '</span>'
                       . '</div>'
                       . mb_change_field_rows($item)
                       . '</div>';
            }
            $html .= '</div></details>';
        }
        return $html;
    }
}
