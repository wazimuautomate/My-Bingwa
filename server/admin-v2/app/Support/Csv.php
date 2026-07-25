<?php
/**
 * Safe CSV export. Streams rows with a filename, escaping every cell to defeat CSV
 * formula injection (a leading =, +, -, @ or tab is neutralised) so a value pulled from
 * the database can never execute in a spreadsheet.
 */

namespace App\Support;

final class Csv
{
    public static function stream(string $filename, array $header, array $rows): void
    {
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $filename) . '"');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($out, array_map([self::class, 'safe'], $header));
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'safe'], $row));
        }
        fclose($out);
        exit;
    }

    public static function safe($value): string
    {
        $s = (string) $value;
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $s;
        }
        return $s;
    }
}
