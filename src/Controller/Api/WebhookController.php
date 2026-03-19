<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\SkillSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(
        private SkillSyncService $syncService,
        private string $webhookSecret,
        private string $skillsRepoUrl,
        private string $skillsRepoDir,
    ) {}

    #[Route('/api/webhook/sync', name: 'api_webhook_sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-Webhook-Secret');
        if (!hash_equals($this->webhookSecret, $token ?? '')) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $commitSha = $payload['commit_sha'] ?? 'unknown';
        $commitUrl = $payload['commit_url'] ?? '';
        $actionRunUrl = $payload['action_run_url'] ?? null;

        $syncLog = $this->syncService->sync(
            $this->skillsRepoUrl,
            $this->skillsRepoDir,
            $commitSha,
            $commitUrl,
            $actionRunUrl,
        );

        $statusCode = $syncLog->getStatus() === 'success' ? 200 : 422;

        return new JsonResponse([
            'status' => $syncLog->getStatus(),
            'skill_count' => $syncLog->getSkillCount(),
            'error' => $syncLog->getErrorMessage(),
        ], $statusCode);
    }
}
