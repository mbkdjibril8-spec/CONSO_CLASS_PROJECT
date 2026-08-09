<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\NotificationRepository;

/** Centre de notifications (soumission, rejet, mismatch, consolidation prête). */
class NotificationController extends Controller
{
    public function index(Request $request): void
    {
        $repo = new NotificationRepository();
        $user = $this->currentUser();

        $this->view('notifications/index', [
            'title' => 'Notifications',
            'notifications' => $repo->forUser($user->id),
        ], $request->isAjax() ? null : 'layouts/main');
    }

    public function markRead(Request $request, string $id): void
    {
        (new NotificationRepository())->markRead((int) $id, $this->currentUser()->id);
        $this->redirect('/notifications');
    }

    public function markAllRead(Request $request): void
    {
        (new NotificationRepository())->markAllRead($this->currentUser()->id);
        $this->redirect('/notifications');
    }
}
