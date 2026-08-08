<?php

namespace App\Core;

/**
 * Routeur minimaliste : associe méthode HTTP + motif d'URL à
 * [ControllerClass, méthode], avec une chaîne de middlewares optionnelle.
 * Supporte les segments dynamiques du type /subsidiaries/{id}.
 */
class Router
{
    private array $routes = [];

    public function get(string $pattern, array $action, array $middleware = []): void
    {
        $this->add('GET', $pattern, $action, $middleware);
    }

    public function post(string $pattern, array $action, array $middleware = []): void
    {
        $this->add('POST', $pattern, $action, $middleware);
    }

    private function add(string $method, string $pattern, array $action, array $middleware): void
    {
        $this->routes[] = compact('method', 'pattern', 'action', 'middleware');
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }

            $request->setParams($params);

            foreach ($route['middleware'] as $middleware) {
                /** @var callable $middleware */
                $result = $middleware($request);
                if ($result === false) {
                    return; // le middleware a déjà émis la réponse (redirection, 403...)
                }
            }

            [$controllerClass, $methodName] = $route['action'];
            $controller = new $controllerClass();
            $controller->$methodName($request, ...array_values($params));
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Introuvable']);
    }

    /**
     * Convertit un motif "/subsidiaries/{id}" en expression régulière et
     * retourne les paramètres nommés si la correspondance est trouvée.
     */
    private function match(string $pattern, string $path): ?array
    {
        $paramNames = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_]+)\}#', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        array_shift($matches);
        return array_combine($paramNames, $matches);
    }
}
