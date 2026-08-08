<?php

namespace App\Core;

/**
 * Moteur de rendu minimal : compile une vue PHP dans un layout commun.
 * Pas de moteur de templates externe (contrainte "aucune dépendance requise").
 */
class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $viewPath = __DIR__ . '/../../views/' . $view . '.php';
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("Vue introuvable : {$view}");
        }

        extract($data, EXTR_SKIP);

        $render = function () use ($viewPath, $data) {
            extract($data, EXTR_SKIP);
            require $viewPath;
        };

        if ($layout === null) {
            $render();
            return;
        }

        $layoutPath = __DIR__ . '/../../views/' . $layout . '.php';
        $content = $render;
        require $layoutPath;
    }
}
