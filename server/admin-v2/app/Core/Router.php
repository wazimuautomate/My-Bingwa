<?php
/**
 * A small pattern router. Routes are (METHOD, /path/{param}, [Controller::class, 'method']).
 * `{param}` segments are captured and passed to the controller method in order.
 * Dispatch resolves the controller, instantiates it, and calls the action.
 */

namespace App\Core;

final class Router
{
    /** @var array<int,array{method:string,regex:string,params:string[],handler:array}> */
    private array $routes = [];

    public function get(string $path, array $handler): void    { $this->add('GET', $path, $handler); }
    public function post(string $path, array $handler): void   { $this->add('POST', $path, $handler); }
    public function any(string $path, array $handler): void    { $this->add('ANY', $path, $handler); }

    private function add(string $method, string $path, array $handler): void
    {
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, rtrim($path, '/') ?: '/');
        $this->routes[] = [
            'method' => $method,
            'regex'  => '#^' . $regex . '$#',
            'params' => $params,
            'handler'=> $handler,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                [$class, $action] = $route['handler'];
                $controller = new $class();
                $controller->$action($request, ...$matches);
                return;
            }
        }
        Response::notFound('No route for ' . $method . ' ' . $path);
    }
}
