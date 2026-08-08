<?php

namespace App\Services;

use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Repositories\UserRepository;

/**
 * Authentification par mot de passe. Ne gère volontairement rien d'autre
 * (pas de "mot de passe oublié" ni d'OAuth : hors périmètre V1).
 */
class AuthService
{
    private UserRepository $users;
    private AuditService $audit;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->audit = new AuditService();
    }

    /** Tente une connexion ; journalise systématiquement le résultat (succès ou échec). */
    public function attempt(string $email, string $password, Request $request): ?User
    {
        $user = $this->users->findByEmail($email);

        if (!$user || !$user->isActive || !password_verify($password, $user->passwordHash)) {
            $this->audit->logLoginFailed($email, $request);
            return null;
        }

        // Régénération de l'identifiant de session pour prévenir la fixation de session.
        Session::regenerate();
        Session::set('user_id', $user->id);
        Session::set('user', $user);

        $this->users->touchLastLogin($user->id);
        $this->audit->logLogin($user, $request);

        return $user;
    }

    public function logout(Request $request): void
    {
        $user = Session::get('user');
        if ($user instanceof User) {
            $this->audit->logLogout($user, $request);
        }
        Session::destroy();
    }
}
