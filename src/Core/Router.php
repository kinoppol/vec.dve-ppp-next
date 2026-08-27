<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Small path router. Routes are literal paths or patterns with {placeholders};
 * handlers are [ControllerClass, 'method'].
 */
final class Router
{
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][trim($path, '/')] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][trim($path, '/')] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path   = trim($path, '/');
        $table  = $this->routes[$method] ?? [];

        if (isset($table[$path])) {
            $this->call($table[$path], []);
            return;
        }

        foreach ($table as $pattern => $handler) {
            if (!str_contains($pattern, '{')) {
                continue;
            }
            // Escape the literal parts, then turn \{name\} back into a capture group.
            $regex = preg_quote($pattern, '#');
            $regex = preg_replace('#\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}#', '(?P<$1>[^/]+)', $regex);
            if (preg_match('#^' . $regex . '$#', $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->call($handler, array_values($params));
                return;
            }
        }

        http_response_code(404);
        View::display('errors/404', ['title' => 'ไม่พบหน้าที่ต้องการ'], 'app');
    }

    private function call(array $handler, array $params): void
    {
        [$class, $action] = $handler;
        $controller = new $class();
        $controller->{$action}(...$params);
    }
}
