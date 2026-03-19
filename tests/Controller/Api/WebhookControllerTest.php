<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\SyncLog;
use App\Service\SkillSyncService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WebhookControllerTest extends WebTestCase
{
    public function testWebhookRejectsNoSecret(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/webhook/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['commit_sha' => 'abc123']));

        self::assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Unauthorized', $data['error']);
    }

    public function testWebhookRejectsInvalidSecret(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/webhook/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SECRET' => 'wrong-secret',
        ], json_encode(['commit_sha' => 'abc123']));

        self::assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Unauthorized', $data['error']);
    }

    public function testWebhookAcceptsValidSecretAndCallsSync(): void
    {
        $client = static::createClient();

        // Create a mock SyncLog that the mocked service will return
        $syncLog = new SyncLog();
        $syncLog->setStatus('success');
        $syncLog->setSkillCount(3);
        $syncLog->setCommitSha('abc123');
        $syncLog->setCommitUrl('https://github.com/example/commit/abc123');

        // Mock the SkillSyncService
        $syncService = $this->createMock(SkillSyncService::class);
        $syncService->expects($this->once())
            ->method('sync')
            ->willReturnCallback(function (
                string $commitSha,
                string $commitUrl,
                ?string $actionRunUrl,
            ) use ($syncLog): SyncLog {
                self::assertSame('abc123', $commitSha);
                self::assertSame('https://github.com/example/commit/abc123', $commitUrl);
                self::assertSame('https://github.com/example/actions/runs/1', $actionRunUrl);

                return $syncLog;
            });

        // Replace the real service with our mock
        static::getContainer()->set(SkillSyncService::class, $syncService);

        $client->request('POST', '/api/webhook/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SECRET' => 'test-secret',
        ], json_encode([
            'commit_sha' => 'abc123',
            'commit_url' => 'https://github.com/example/commit/abc123',
            'action_run_url' => 'https://github.com/example/actions/runs/1',
        ]));

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('success', $data['status']);
        self::assertSame(3, $data['skill_count']);
        self::assertNull($data['error']);
    }

    public function testWebhookReturns422OnSyncFailure(): void
    {
        $client = static::createClient();

        $syncLog = new SyncLog();
        $syncLog->setStatus('failed');
        $syncLog->setSkillCount(0);
        $syncLog->setCommitSha('abc123');
        $syncLog->setCommitUrl('');
        $syncLog->setErrorMessage('Validation failed for broken-skill: missing actions');

        $syncService = $this->createMock(SkillSyncService::class);
        $syncService->expects($this->once())
            ->method('sync')
            ->willReturn($syncLog);

        static::getContainer()->set(SkillSyncService::class, $syncService);

        $client->request('POST', '/api/webhook/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SECRET' => 'test-secret',
        ], json_encode(['commit_sha' => 'abc123']));

        self::assertResponseStatusCodeSame(422);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('failed', $data['status']);
        self::assertSame(0, $data['skill_count']);
        self::assertSame('Sync failed — check admin dashboard for details', $data['error']);
    }

    public function testWebhookRejectsGetMethod(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/webhook/sync');

        self::assertResponseStatusCodeSame(405);
    }
}
