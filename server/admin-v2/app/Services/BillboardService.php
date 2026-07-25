<?php
/**
 * Billboard token resolution, validation and the explainable personalisation scoring
 * used by the "Why this billboard" simulator. Scoring is a transparent weighted model
 * (never opaque AI) whose weights come from app_config within validated bounds.
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

    /**
     * Explainable personalisation score for a candidate offer given anonymous local
     * behaviour. Returns the total and a per-factor breakdown for the admin simulator.
     *
     * @param array $offer   candidate offer (category, price, validity band)
     * @param array $behaviour ['categoryCounts'=>['DATA'=>3,...], 'avgSpend'=>float, 'boughtTodayIds'=>[]]
     * @param array $weights app_config personalisation weights
     */
    public static function scoreOffer(array $offer, array $behaviour, array $weights): array
    {
        $freqWeight    = (float) ($weights['frequency_weight'] ?? 1.0);
        $valueWeight   = (float) ($weights['value_weight'] ?? 0.6);
        $validityWeight= (float) ($weights['validity_weight'] ?? 0.4);
        $maxStepUp     = (float) ($weights['max_step_up'] ?? 3.0);

        $catCounts = $behaviour['categoryCounts'] ?? [];
        $totalBuys = array_sum($catCounts) ?: 1;
        $avgSpend  = (float) ($behaviour['avgSpend'] ?? 0);

        // Frequency: prefer categories the customer buys more often.
        $catShare = ($catCounts[$offer['category']] ?? 0) / $totalBuys;
        $freqScore = $catShare * $freqWeight;

        // Value step-up: reward a reasonable increase over normal spend, penalise extremes.
        $price = (float) $offer['price'];
        $ratio = $avgSpend > 0 ? $price / $avgSpend : 1.0;
        $stepScore = 0.0;
        if ($ratio >= 1.0 && $ratio <= $maxStepUp) {
            // Peak reward around a moderate step up (~1.5x).
            $stepScore = (1 - abs($ratio - 1.5) / max(0.5, $maxStepUp)) * $valueWeight;
        } elseif ($ratio < 1.0) {
            $stepScore = ($ratio - 0.2) * $valueWeight * 0.3; // slight reward for same/cheaper
        } // ratio > maxStepUp → 0 (irrelevant extreme jump)
        $stepScore = max(0.0, $stepScore);

        // Validity: longer-validity offers get a small boost.
        $validityScore = self::validityRank($offer['band'] ?? $offer['validity'] ?? '') * $validityWeight;

        $total = $freqScore + $stepScore + $validityScore;
        return [
            'total'    => round($total, 4),
            'factors'  => [
                'frequency' => round($freqScore, 4),
                'valueStepUp' => round($stepScore, 4),
                'validity' => round($validityScore, 4),
            ],
            'ratio'    => round($ratio, 2),
            'excluded' => in_array($offer['id'] ?? '', $behaviour['boughtTodayIds'] ?? [], true),
        ];
    }

    private static function validityRank(string $band): float
    {
        $b = strtolower($band);
        if (str_contains($b, 'month') || str_contains($b, '30')) return 1.0;
        if (str_contains($b, 'week') || str_contains($b, '7')) return 0.7;
        if (str_contains($b, 'day') || str_contains($b, 'midnight') || str_contains($b, '24')) return 0.4;
        return 0.2;
    }
}
