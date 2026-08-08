<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        if (Session::get('user')) {
            $this->redirect('/dashboard');
            return;
        }

        $this->view('auth/login', [
            'title' => 'Connexion',
            'error' => Session::pullFlashes()['error'][0] ?? null,
        ], layout: null);
    }

    public function login(Request $request): void
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            Session::flash('error', 'Veuillez renseigner votre email et votre mot de passe.');
            $this->redirect('/login');
            return;
        }

        $user = (new AuthService())->attempt($email, $password, $request);

        if (!$user) {
            Session::flash('error', 'Identifiants incorrects.');
            $this->redirect('/login');
            return;
        }

        $this->redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        (new AuthService())->logout($request);
        $this->redirect('/login');
    }
}
