<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SkillDownloadRepository;
use App\Repository\SkillRepository;
use App\Repository\SyncLogRepository;
use App\Service\SkillSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function index(
        SkillRepository $skillRepo,
        SkillDownloadRepository $downloadRepo,
        SyncLogRepository $syncLogRepo,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'skill_count' => count($skillRepo->findAll()),
            'total_downloads' => $downloadRepo->getTotalCount(),
            'last_sync' => $syncLogRepo->findLatest(1)[0] ?? null,
        ]);
    }

    #[Route('/resync', name: 'app_admin_resync', methods: ['POST'])]
    public function resync(SkillSyncService $syncService): Response
    {
        $result = $syncService->sync('manual', '', null);

        if ($result->getStatus() === 'success') {
            $this->addFlash('success', "Synced {$result->getSkillCount()} skills.");
        } else {
            $this->addFlash('error', $result->getErrorMessage() ?? 'Sync failed');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }
}
