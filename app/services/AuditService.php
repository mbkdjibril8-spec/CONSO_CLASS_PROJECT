<?php

namespace App\Services;

use App\Core\Request;
use App\Models\User;
use App\Repositories\AuditLogRepository;

/**
 * Point d'entrée unique pour toute écriture dans le journal d'audit.
 * Toute action sensible (connexion, accès refusé, modification de donnée,
 * changement de statut) doit transiter par ce service.
 */
class AuditService
{
    private AuditLogRepository $repository;

    public function __construct()
    {
        $this->repository = new AuditLogRepository();
    }

    public function logLogin(User $user, Request $request): void
    {
        $this->repository->log($user->id, 'login', 'user', $user->id, null, null, $request->ip());
    }

    public function logLoginFailed(string $email, Request $request): void
    {
        $this->repository->log(null, 'login_failed', 'user', null, null, $email, $request->ip());
    }

    public function logLogout(User $user, Request $request): void
    {
        $this->repository->log($user->id, 'logout', 'user', $user->id, null, null, $request->ip());
    }

    public function logUnauthorizedAccess(?User $user, Request $request, string $reason): void
    {
        $this->repository->log(
            $user?->id,
            'unauthorized_access',
            'route',
            null,
            null,
            $reason . ' [' . $request->method() . ' ' . $request->path() . ']',
            $request->ip()
        );
    }

    public function logChange(
        User $user,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request,
        ?int $subsidiaryId = null,
        ?int $periodId = null
    ): void {
        $this->repository->log(
            $user->id,
            $action,
            $entityType,
            $entityId,
            $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null,
            $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null,
            $request->ip(),
            $subsidiaryId,
            $periodId
        );
    }
}
