<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\SkillSyncService;
use App\Trait\LoggerTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    use LoggerTrait;

    public function __construct(
        private readonly SkillSyncService $syncService,
        private readonly string $webhookSecret,
    ) {}

    #[Route('/api/webhook/sync', name: 'api_webhook_sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-Webhook-Secret');
        if (!hash_equals($this->webhookSecret, $token ?? '')) {
            $this->logger?->warning('Webhook auth failed', ['ip' => $request->getClientIp()]);

            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logger?->warning('Webhook received invalid JSON', ['ip' => $request->getClientIp()]);

            return new JsonResponse(['error' => 'Invalid JSON payload'], 400);
        }

        $syncLog = $this->syncService->sync(
            $payload['commit_sha'] ?? 'unknown',
            $payload['commit_url'] ?? '',
            $payload['action_run_url'] ?? null,
        );

        $statusCode = $syncLog->getStatus() === 'success' ? 200 : 422;

        return new JsonResponse([
            'status' => $syncLog->getStatus(),
            'skill_count' => $syncLog->getSkillCount(),
            'error' => $syncLog->getStatus() === 'failed' ? 'Sync failed — check admin dashboard for details' : null,
        ], $statusCode);
    }
}
