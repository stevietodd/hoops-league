<?php

declare(strict_types=1);

final class Router
{
    /** @var array<int, array{method:string,pattern:string,handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->map('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->map('POST', $pattern, $handler);
    }

    private function map(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler');
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = '@^' . preg_replace('@\{([a-zA-Z_]+)\}@', '(?P<$1>[^/]+)', $route['pattern']) . '$@';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            $params = array_filter(
                $matches,
                static fn ($k) => !is_int($k),
                ARRAY_FILTER_USE_KEY
            );
            ($route['handler'])(...array_values($params));
            return;
        }
        http_response_code(404);
        render('errors/404', ['title' => 'Not found']);
    }
}
