<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SyncLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class SyncLogController extends AbstractController
{
    #[Route('/sync-log', name: 'app_admin_sync_log')]
    public function list(SyncLogRepository $syncLogRepo): Response
    {
        return $this->render('admin/sync_log/list.html.twig', [
            'logs' => $syncLogRepo->findLatest(50),
        ]);
    }
}
