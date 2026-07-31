<?php
/**
 * Billboard token resolution and validation. Turns a simple billboard's linked offer
 * into display copy and refuses to publish any unresolved {{token}}. Personalisation
 * (which advert a given customer sees) runs automatically in the app, not here.
 */

namespace App\Services;

final class BillboardService
{
    /** Tokens allowed in simple-billboard copy. */
    public const TOKENS = ['offer_name', 'price', 'validity', 'category'];

    /** What a tap on the advert does. Kept in sync with the target_action column. */
    public const TARGET_ACTIONS = ['none', 'offer', 'category', 'url', 'internal'];

    /** Media the advert draws. Kept in sync with the media_type column. */
    public const MEDIA_TYPES = ['none', 'image', 'gif'];

    /**
     * Resolve a billboard's display copy. For a simple billboard the copy is generated
     * from its linked offer; if the linked offer is missing/unavailable, returns null so
     * the caller drops the billboard (never publishes an unresolved {{token}}).
     *
     * @param array $b       billboard row
     * @param array|null $offer linked offer row (offer_id,name,price,validity) or null
     * @return array{tag:string, headline:string, body:string}|null
     */
    public static function resolveContent(array $b, ?array $offer): ?array
    {
        $kind = $b['kind'] ?? 'simple';
        if ($kind === 'advanced') {
            // Advanced billboards carry literal copy; still refuse stray tokens.
            $tag = (string) ($b['tag'] ?? '');
            $headline = (string) ($b['headline'] ?? '');
            $body = (string) ($b['body'] ?? '');
            if (self::hasToken($tag) || self::hasToken($headline) || self::hasToken($body)) {
                return null;
            }
            return ['tag' => $tag, 'headline' => $headline, 'body' => $body];
        }

        // Simple billboard: must have a resolvable linked offer.
        if (!$offer) {
            return null;
        }
        $map = [
            'offer_name' => (string) $offer['name'],
            'price'      => (string) $offer['price'],
            'validity'   => (string) ($offer['validity'] ?? ''),
            'category'   => (string) ($offer['category'] ?? ''),
        ];
        $tag = $b['tag'] !== '' ? (string) $b['tag'] : 'BEST VALUE';
        $headline = $b['headline'] !== '' ? (string) $b['headline'] : '{{offer_name}} for KSh {{price}}';
        $body = $b['body'] !== '' ? (string) $b['body'] : 'Stay connected for {{validity}}.';

        $tag = self::render($tag, $map);
        $headline = self::render($headline, $map);
        $body = self::render($body, $map);
        if (self::hasToken($tag) || self::hasToken($headline) || self::hasToken($body)) {
            return null; // an unsupported token remained
        }
        return ['tag' => $tag, 'headline' => $headline, 'body' => $body];
    }

    public static function render(string $text, array $map): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($map) {
            return array_key_exists($m[1], $map) ? $map[$m[1]] : $m[0];
        }, $text);
    }

    public static function hasToken(string $text): bool
    {
        return strpos($text, '{{') !== false;
    }

    /** Validate that a copy string only uses supported tokens. Returns bad tokens. */
    public static function unsupportedTokens(string $text): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $text, $m);
        $bad = [];
        foreach ($m[1] as $tok) {
            if (!in_array($tok, self::TOKENS, true)) {
                $bad[] = $tok;
            }
        }
        return array_values(array_unique($bad));
    }

    /* ------------------------------------------------------- effective state ----
     * The state an operator actually sees. It is DERIVED from the dates every time
     * the page renders, so a billboard whose window has started is "Live now"
     * without anybody toggling anything, and one whose window closed reads "Ended"
     * even though its stored status column still says active.
     *
     * Deliberate asymmetry (mirrors PublishingService::buildBillboards): a SIMPLE
     * billboard is always-on — its start/end window is ignored so an offer-linked
     * advert can never silently expire. An ADVANCED billboard honours its window.
     */

    /**
     * @param array $billboard  billboard row (enabled, status, kind, starts_at, ends_at)
     * @param \DateTimeImmutable $nairobiNow current time (any zone; compared absolutely)
     * @return string 'disabled'|'live'|'scheduled'|'ended'|'draft'
     */
    public static function effectiveState(array $billboard, \DateTimeImmutable $nairobiNow): string
    {
        // An explicit off switch beats everything else, including a live window.
        if (array_key_exists('enabled', $billboard) && (int) $billboard['enabled'] !== 1) {
            return 'disabled';
        }
        $status = (string) ($billboard['status'] ?? 'draft');
        if ($status === 'draft') {
            return 'draft';
        }
        // Only active/scheduled rows are ever published; anything else (paused,
        // expired, archived) is off as far as the app is concerned.
        if ($status !== 'active' && $status !== 'scheduled') {
            return 'disabled';
        }
        // Simple billboards ignore their schedule entirely (always-on).
        if ((string) ($billboard['kind'] ?? 'simple') !== 'advanced') {
            return 'live';
        }
        $ends = self::moment($billboard['ends_at'] ?? null);
        if ($ends !== null && $ends <= $nairobiNow) {
            return 'ended';
        }
        $starts = self::moment($billboard['starts_at'] ?? null);
        if ($starts !== null && $starts > $nairobiNow) {
            return 'scheduled';
        }
        return 'live';
    }

    /** Plain-English label for an effective state. */
    public static function stateLabel(string $state): string
    {
        switch ($state) {
            case 'live':      return 'Live now';
            case 'scheduled': return 'Scheduled';
            case 'ended':     return 'Ended';
            case 'disabled':  return 'Off';
            default:          return 'Draft';
        }
    }

    /** CSS class from the shared .status palette for an effective state. */
    public static function stateClass(string $state): string
    {
        switch ($state) {
            case 'live':      return 'active';
            case 'scheduled': return 'scheduled';
            case 'ended':     return 'expired';
            case 'disabled':  return 'paused';
            default:          return 'draft';
        }
    }

    /** Parse a stored UTC datetime. Returns null for empty/invalid values. */
    private static function moment($utc): ?\DateTimeImmutable
    {
        $utc = trim((string) $utc);
        if ($utc === '' || strpos($utc, '0000-00-00') === 0) {
            return null;
        }
        try {
            return new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* ------------------------------------------------------------ tap target ---- */

    /**
     * Validate what happens when someone taps the advert. Returns human-readable
     * errors (empty array = valid). Pure: the caller looks the offer and the
     * category list up and passes the answers in.
     *
     * @param array    $input             target_action, linked_offer_id, target_category,
     *                                    click_url, internal_action
     * @param string[] $knownCategoryKeys enabled category keys, e.g. ['DATA','SMS']
     * @param bool     $offerExists       does the linked offer exist?
     * @return string[]
     */
    public static function validateTarget(array $input, array $knownCategoryKeys, bool $offerExists): array
    {
        $action = trim((string) ($input['target_action'] ?? 'none'));
        if (!in_array($action, self::TARGET_ACTIONS, true)) {
            return ['Choose what happens when someone taps this advert.'];
        }
        $errors = [];
        if ($action === 'offer') {
            if (trim((string) ($input['linked_offer_id'] ?? '')) === '') {
                $errors[] = 'Choose the offer this advert opens.';
            } elseif (!$offerExists) {
                $errors[] = 'That offer no longer exists, so the advert would open nothing.';
            }
        } elseif ($action === 'category') {
            $cat = trim((string) ($input['target_category'] ?? ''));
            if ($cat === '') {
                $errors[] = 'Choose the category this advert opens.';
            } elseif (!in_array($cat, $knownCategoryKeys, true)) {
                $errors[] = 'That category is not switched on, so the advert would open nothing.';
            }
        } elseif ($action === 'url') {
            if (!self::isAllowedUrl((string) ($input['click_url'] ?? ''))) {
                $errors[] = 'Enter the full web address starting with https:// (http:// and scripts are not allowed).';
            }
        } elseif ($action === 'internal') {
            if (trim((string) ($input['internal_action'] ?? '')) === '') {
                $errors[] = 'Name the app screen this advert opens.';
            }
        }
        return $errors;
    }

    /**
     * A web address we are willing to hand to the app: https only, a real host, no
     * javascript:/data: smuggling and no control characters.
     */
    public static function isAllowedUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 255) {
            return false;
        }
        if (preg_match('/[\x00-\x20\x7F]/', $url)) {
            return false; // whitespace or control characters
        }
        if (stripos($url, 'https://') !== 0) {
            return false; // rejects http://, javascript:, data:, mailto:, protocol-relative
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }
        $host = (string) ($parts['host'] ?? '');
        if ($host === '' || !preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?$/', $host)) {
            return false;
        }
        // A public advert link must be a real domain, not a bare hostname.
        return strpos($host, '.') !== false;
    }
}
