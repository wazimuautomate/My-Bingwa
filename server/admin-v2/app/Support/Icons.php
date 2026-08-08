<?php
/**
 * A single, consistent outline icon family rendered as inline SVG (stroke=currentColor).
 * No emoji, no icon font, no external requests. Use via the global icon() helper.
 */

namespace App\Support;

final class Icons
{
    private const PATHS = [
        'dashboard'    => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'offers'       => '<path d="M20.6 12.6 12 21l-8-8V4h9l7.6 7.6a1.4 1.4 0 0 1 0 2z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
        'billboards'   => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'notifications'=> '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'templates'    => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.3L3 21l1.2-3.6A8.4 8.4 0 1 1 21 11.5z"/><path d="M8 10h8M8 13.5h5"/>',
        'payments'     => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 9.5h19"/><path d="M6 14.5h4"/>',
        'support'      => '<circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4"/><path d="M12 17h.01"/>',
        'config'       => '<path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h8M16 18h4"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="14" cy="18" r="2"/>',
        'versions'     => '<path d="M12 3a9 9 0 1 0 9 9"/><path d="M21 3v5h-5"/><path d="M12 8v4l3 2"/>',
        'audit'        => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 16.5h4"/>',
        'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 0 1-4 0v-.1A1.6 1.6 0 0 0 7 19.4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 4.6 15H4.5a2 2 0 0 1 0-4h.1A1.6 1.6 0 0 0 6 8.3l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 2.7-1.1V4.5a2 2 0 0 1 4 0v.1A1.6 1.6 0 0 0 17 6l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7h.1a2 2 0 0 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
        'help'         => '<circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4"/><path d="M12 17h.01"/>',
        'logout'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'search'       => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'bell'         => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'sun'          => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'         => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'menu'         => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'more'         => '<circle cx="12" cy="5" r="1.6" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1.6" fill="currentColor" stroke="none"/>',
        'plus'         => '<path d="M12 5v14M5 12h14"/>',
        'edit'         => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'archive'      => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/><path d="M10 12h4"/>',
        'restore'      => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
        'trash'        => '<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/><path d="M9 7V4h6v3"/>',
        'copy'         => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
        'up'           => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'down'         => '<path d="M12 5v14M5 12l7 7 7-7"/>',
        'check'        => '<path d="M20 6 9 17l-5-5"/>',
        'close'        => '<path d="M18 6 6 18M6 6l12 12"/>',
        'chevron'      => '<path d="M9 18l6-6-6-6"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'external'     => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/>',
        'upload'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 8l5-5 5 5"/><path d="M12 3v12"/>',
        'download'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'publish'      => '<path d="M4.5 16.5 3 21l4.5-1.5"/><path d="M14 4c3 0 6 3 6 6l-8 8-4-4 8-8"/><path d="M14 4l-2 2"/><circle cx="15" cy="9" r="1.2"/>',
        'rollback'     => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'warning'      => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'info'         => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
        'shield'       => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'phone'        => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.5 2.1L8 9.6a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.8.3 1.7.5 2.6.6a2 2 0 0 1 1.7 2z"/>',
        'clock'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'filter'       => '<path d="M3 5h18l-7 8v6l-4-2v-4z"/>',
        'eye'          => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'sync'         => '<path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 12A9 9 0 0 1 18 5.3L21 8"/><path d="M21 3v5h-5M3 21v-5h5"/>',
        'money'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5A2.5 2.5 0 0 1 12 8c1.4 0 2.5.8 2.5 1.8M14.5 14.2A2.5 2.5 0 0 1 12 16c-1.4 0-2.5-.8-2.5-1.8"/>',
        'calendar'     => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/>',
        'user'         => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/>',
        'key'          => '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2 21 2M17 6l3 3M14 9l3 3"/>',
        'flask'        => '<path d="M9 3h6"/><path d="M10 3v6.5L4.6 18A2 2 0 0 0 6.3 21h11.4a2 2 0 0 0 1.7-3L14 9.5V3"/><path d="M7.5 15h9"/>',
        'image'        => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="M21 16l-5-5-6 6-3-3-4 4"/>',
        'layers'       => '<path d="M12 3 3 8l9 5 9-5z"/><path d="M3 13l9 5 9-5"/>',
        'flag'         => '<path d="M5 21V4"/><path d="M5 5h11l-1.5 3L16 11H5z"/>',
        'toggle'       => '<rect x="2.5" y="7" width="19" height="10" rx="5"/><circle cx="16" cy="12" r="3"/>',
        'variations'   => '<rect x="3" y="4" width="13" height="9" rx="2"/><path d="M8 17h13M8 20h9"/>',
    ];

    public static function svg(string $name, int $size = 20, string $extraClass = ''): string
    {
        $path = self::PATHS[$name] ?? self::PATHS['info'];
        $cls = trim('icon ' . $extraClass);
        return '<svg class="' . htmlspecialchars($cls, ENT_QUOTES) . '" width="' . $size . '" height="' . $size . '" '
            . 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    }
}
