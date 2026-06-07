<?php
namespace App\Core;

final class Router
{
    /** @var array<int,array{0:string,1:string,2:array}> */
    private array $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method;
        $path   = '/' . trim($request->path, '/');
        if ($path === '/') {
            $path = '/';
        }

        if ($method === 'HEAD') {
            $method = 'GET';
        }

        foreach ($this->routes as [$rMethod, $rPath, $handler]) {
            if (strtoupper($rMethod) !== $method) {
                continue;
            }
            $params = self::match($rPath, $path);
            if ($params === null) {
                continue;
            }
            $request->params = $params;
            [$class, $action] = $handler;
            $instance = new $class();
            $instance->{$action}($request);
            return;
        }

        Response::notFound('No route matches ' . $request->method . ' ' . $path);
    }

    private static function match(string $pattern, string $path): ?array
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts    = explode('/', trim($path, '/'));

        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];
        foreach ($patternParts as $i => $segment) {
            if (preg_match('/^\{(\w+)\}$/', $segment, $m)) {
                $params[$m[1]] = $pathParts[$i];
            } elseif ($segment !== $pathParts[$i]) {
                return null;
            }
        }
        return $params;
    }
}
