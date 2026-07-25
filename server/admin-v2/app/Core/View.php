<?php
/**
 * Tiny PHP-template view layer. `View::render('offers/index', [...])` runs
 * app/Views/offers/index.php inside app/Views/layout.php. Views use plain PHP with
 * the `e()` escaper. No template-engine dependency.
 */

namespace App\Core;

final class View
{
    private static string $viewDir = '';
    private static array $shared = [];

    public static function boot(string $viewDir): void
    {
        self::$viewDir = rtrim($viewDir, '/\\');
    }

    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    /** Render a view inside the main layout and return HTML. */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, array_merge($data, ['content' => $content]));
    }

    /** Render a view file without a layout (used for partials and the layout itself). */
    public static function partial(string $template, array $data = []): string
    {
        $file = self::$viewDir . '/' . str_replace('..', '', $template) . '.php';
        if (!is_file($file)) {
            return '<!-- missing view: ' . e($template) . ' -->';
        }
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    public static function renderTo(string $template, array $data = [], ?string $layout = 'layout', int $code = 200): void
    {
        Response::html(self::render($template, $data, $layout), $code);
    }
}
