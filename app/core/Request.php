<?php

namespace App\Core;

/**
 * Représente la requête HTTP courante. Centralise l'accès à
 * $_GET/$_POST/$_SERVER pour éviter la dispersion des superglobales.
 */
class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $body;
    private array $params = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query = $_GET;
        $this->body = $_POST;
        $this->path = $this->resolvePath();
    }

    /**
     * Calcule le chemin de route en retirant le chemin de base du script
     * (ex: /groupfin/public) afin que le routeur reste indépendant de
     * l'emplacement d'installation sous htdocs.
     */
    private function resolvePath(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }

        if ($uri === '' || $uri === false) {
            $uri = '/';
        }

        if (strlen($uri) > 1 && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    public function method(): string
    {
        // Support du champ caché _method pour PUT/DELETE depuis un <form>.
        if ($this->method === 'POST' && isset($this->body['_method'])) {
            return strtoupper($this->body['_method']);
        }
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
