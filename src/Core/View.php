<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Plain PHP templates with a single level of layout inheritance.
 *
 * A template's variables are tracked on a stack so a partial inherits its
 * parent's data automatically: `View::partial('partials/topbar')` needs no
 * arguments. (Templates must never pass get_defined_vars() around — that also
 * captures this class's own locals and nests the data array into itself.)
 */
final class View
{
    private static array $shared = [];

    /** @var array<int,array<string,mixed>> */
    private static array $stack = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture('layouts/' . $layout, $data + ['content' => $content]);
    }

    public static function display(string $template, array $data = [], ?string $layout = 'app'): void
    {
        echo self::render($template, $data, $layout);
    }

    /** Render a partial; it inherits the calling template's variables. */
    public static function partial(string $template, array $extra = []): string
    {
        $inherited = self::$stack === [] ? [] : self::$stack[count(self::$stack) - 1];
        return self::capture($template, $extra + $inherited);
    }

    private static function capture(string $__template, array $__data): string
    {
        $__path = dirname(__DIR__, 2) . '/views/' . str_replace('.', '/', $__template) . '.php';
        if (!is_file($__path)) {
            throw new \RuntimeException('ไม่พบเทมเพลต: ' . $__template);
        }

        $__vars = $__data + self::$shared;
        self::$stack[] = $__vars;
        unset($__data);

        extract($__vars, EXTR_OVERWRITE);
        unset($__vars);

        ob_start();
        try {
            require $__path;
        } catch (\Throwable $e) {
            ob_end_clean();
            array_pop(self::$stack);
            throw $e;
        }
        array_pop(self::$stack);

        return (string) ob_get_clean();
    }
}
