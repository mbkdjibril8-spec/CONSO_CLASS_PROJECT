<?php

namespace App\Core;

use App\Models\User;

/**
 * Contrôleur de base : expose des raccourcis communs (rendu de vue,
 * redirection, utilisateur courant) à tous les contrôleurs de l'application.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . base_url($path));
        exit;
    }

    protected function currentUser(): ?User
    {
        return Session::get('user');
    }
}
