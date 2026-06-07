<?php
namespace App\Core;

final class View
{
    private static string $viewsDir;

    public static function setViewsDir(string $dir): void
    {
        self::$viewsDir = $dir;
    }

    public static function render(string $name, array $data = [], ?string $layout = 'main'): string
    {
        $viewFile = self::$viewsDir . '/' . $name . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: $name ($viewFile)");
        }

        $slot = self::renderFile($viewFile, $data);

        if ($layout === null) {
            return $slot;
        }

        $layoutFile = self::$viewsDir . '/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException("Layout not found: $layout ($layoutFile)");
        }

        return self::renderFile($layoutFile, array_merge($data, ['slot' => $slot]));
    }

    public static function partial(string $name, array $data = []): void
    {
        $file = self::$viewsDir . '/partials/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Partial not found: $name ($file)");
        }
        echo self::renderFile($file, $data);
    }

    private static function renderFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }
}
