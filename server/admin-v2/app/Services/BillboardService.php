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
}
