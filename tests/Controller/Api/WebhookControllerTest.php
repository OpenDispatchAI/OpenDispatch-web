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

        $this->assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Unauthorized', $data['error']);
    }

    public function testWebhookRejectsInvalidSecret(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/webhook/sync', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SECRET' => 'wrong-secret',
        ], json_encode(['commit_sha' => 'abc123']));

        $this->assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Unauthorized', $data['error']);
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
                string $repoUrl,
                string $targetDir,
                string $commitSha,
                string $commitUrl,
                ?string $actionRunUrl,
            ) use ($syncLog): SyncLog {
                $this->assertSame('abc123', $commitSha);
                $this->assertSame('https://github.com/example/commit/abc123', $commitUrl);
                $this->assertSame('https://github.com/example/actions/runs/1', $actionRunUrl);

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

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(3, $data['skill_count']);
        $this->assertNull($data['error']);
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

        $this->assertResponseStatusCodeSame(422);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('failed', $data['status']);
        $this->assertSame(0, $data['skill_count']);
        $this->assertStringContainsString('Validation failed', $data['error']);
    }

    public function testWebhookRejectsGetMethod(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/webhook/sync');

        $this->assertResponseStatusCodeSame(405);
    }
}
