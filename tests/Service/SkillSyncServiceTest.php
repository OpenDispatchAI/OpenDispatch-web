<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Skill;
use App\Entity\SyncLog;
use App\Repository\SkillRepository;
use App\Service\SkillCompiler;
use App\Service\SkillSyncService;
use App\Service\SkillYamlValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SkillSyncServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private SkillRepository $skillRepository;
    private SkillYamlValidator $validator;
    private SkillCompiler $compiler;
    private SkillSyncService $service;

    /** @var object[] Entities passed to persist() */
    private array $persisted = [];

    /** @var object[] Entities passed to remove() */
    private array $removed = [];

    private int $flushCount = 0;

    private string $validRepoDir;
    private string $invalidRepoDir;

    protected function setUp(): void
    {
        $this->persisted = [];
        $this->removed = [];
        $this->flushCount = 0;

        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->skillRepository = $this->createStub(SkillRepository::class);
        $this->validator = new SkillYamlValidator(); // real validator — it's a pure service
        $this->compiler = $this->createStub(SkillCompiler::class);

        $this->em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $this->em->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
        });
        $this->em->method('flush')->willReturnCallback(function (): void {
            $this->flushCount++;
        });

        $this->service = new SkillSyncService(
            $this->em,
            $this->skillRepository,
            $this->validator,
            $this->compiler,
        );

        $this->validRepoDir = __DIR__ . '/../fixtures/skills-repo-valid';
        $this->invalidRepoDir = __DIR__ . '/../fixtures/skills-repo';
    }

    public function testSyncFromDirectoryCreatesNewSkills(): void
    {
        // No existing skills in the DB
        $this->skillRepository->method('findOneBy')->willReturn(null);
        $this->skillRepository->method('findAll')->willReturn([]);

        $result = $this->service->syncFromDirectory(
            $this->validRepoDir,
            'abc123def456',
            'https://github.com/example/commit/abc123',
            'https://github.com/example/actions/runs/1',
        );

        // SyncLog should be success
        $this->assertSame('success', $result->getStatus());
        $this->assertSame(1, $result->getSkillCount());

        // A new Skill entity should have been persisted
        $skills = array_filter($this->persisted, fn (object $e) => $e instanceof Skill);
        $this->assertCount(1, $skills);

        /** @var Skill $skill */
        $skill = reset($skills);
        $this->assertSame('tesla', $skill->getSkillId());
        $this->assertSame('Tesla', $skill->getName());
        $this->assertSame('1.0.0', $skill->getVersion());
        $this->assertSame('Control your Tesla', $skill->getDescription());
        $this->assertSame('opendispatch', $skill->getAuthor());
        $this->assertSame(2, $skill->getActionCount());
        $this->assertSame(['automotive', 'smart-home'], $skill->getTags());
        $this->assertSame(['en'], $skill->getLanguages());
    }

    public function testSyncFromDirectoryAbortsOnInvalidYaml(): void
    {
        // The invalid repo has a broken skill (missing actions)
        $this->skillRepository->method('findOneBy')->willReturn(null);
        $this->skillRepository->method('findAll')->willReturn([]);

        $result = $this->service->syncFromDirectory(
            $this->invalidRepoDir,
            'abc123def456',
            'https://github.com/example/commit/abc123',
            null,
        );

        // SyncLog should indicate failure
        $this->assertSame('failed', $result->getStatus());
        $this->assertSame(0, $result->getSkillCount());
        $this->assertNotNull($result->getErrorMessage());
        $this->assertStringContainsString('broken', $result->getErrorMessage());

        // No Skill entities should have been persisted (only SyncLog)
        $skillsPersisted = array_filter($this->persisted, fn (object $e) => $e instanceof Skill);
        $this->assertCount(0, $skillsPersisted);
    }

    public function testSyncFromDirectoryUpsertsExistingSkill(): void
    {
        // Pre-existing skill with old data
        $existingSkill = new Skill();
        $existingSkill->setSkillId('tesla');
        $existingSkill->setName('Tesla Old');
        $existingSkill->setVersion('0.9.0');
        $existingSkill->setDescription('Old description');
        $existingSkill->setAuthor('old-author');
        $existingSkill->setYamlContent('old yaml');
        $existingSkill->setActionCount(0);
        $existingSkill->setExampleCount(0);

        $this->skillRepository->method('findOneBy')->willReturn($existingSkill);
        $this->skillRepository->method('findAll')->willReturn([$existingSkill]);

        $result = $this->service->syncFromDirectory(
            $this->validRepoDir,
            'abc123def456',
            'https://github.com/example/commit/abc123',
            null,
        );

        $this->assertSame('success', $result->getStatus());

        // Existing skill should be updated, not a new one persisted
        $skillsPersisted = array_filter($this->persisted, fn (object $e) => $e instanceof Skill);
        $this->assertCount(0, $skillsPersisted, 'Existing skill should NOT be re-persisted');

        // Verify the existing skill was updated in place
        $this->assertSame('Tesla', $existingSkill->getName());
        $this->assertSame('1.0.0', $existingSkill->getVersion());
        $this->assertSame('Control your Tesla', $existingSkill->getDescription());
        $this->assertSame('opendispatch', $existingSkill->getAuthor());
        $this->assertSame(2, $existingSkill->getActionCount());
    }

    public function testSyncFromDirectoryPreservesIsFeatured(): void
    {
        // Pre-existing featured skill
        $existingSkill = new Skill();
        $existingSkill->setSkillId('tesla');
        $existingSkill->setName('Tesla');
        $existingSkill->setVersion('1.0.0');
        $existingSkill->setDescription('Control your Tesla');
        $existingSkill->setAuthor('opendispatch');
        $existingSkill->setYamlContent('old yaml');
        $existingSkill->setActionCount(2);
        $existingSkill->setExampleCount(3);
        $existingSkill->setIsFeatured(true);

        $this->skillRepository->method('findOneBy')->willReturn($existingSkill);
        $this->skillRepository->method('findAll')->willReturn([$existingSkill]);

        $result = $this->service->syncFromDirectory(
            $this->validRepoDir,
            'abc123def456',
            'https://github.com/example/commit/abc123',
            null,
        );

        $this->assertSame('success', $result->getStatus());

        // isFeatured should NOT be overwritten by sync
        $this->assertTrue($existingSkill->isFeatured(), 'isFeatured must be preserved after sync');
    }

    public function testSyncFromDirectoryDeletesOrphanedSkills(): void
    {
        // An orphan skill that is NOT in the repo
        $orphanSkill = new Skill();
        $orphanSkill->setSkillId('deleted-skill');
        $orphanSkill->setName('Deleted');
        $orphanSkill->setVersion('1.0.0');
        $orphanSkill->setDescription('Should be removed');
        $orphanSkill->setAuthor('test');
        $orphanSkill->setYamlContent('old yaml');
        $orphanSkill->setActionCount(0);
        $orphanSkill->setExampleCount(0);

        // Tesla exists in repo, orphan does not
        $this->skillRepository->method('findOneBy')->willReturn(null);
        $this->skillRepository->method('findAll')->willReturn([$orphanSkill]);

        $result = $this->service->syncFromDirectory(
            $this->validRepoDir,
            'abc123def456',
            'https://github.com/example/commit/abc123',
            null,
        );

        $this->assertSame('success', $result->getStatus());

        // The orphan should have been removed
        $this->assertContains($orphanSkill, $this->removed, 'Orphaned skill should be removed');
    }

    public function testSyncFromDirectoryLogsSyncResult(): void
    {
        $this->skillRepository->method('findOneBy')->willReturn(null);
        $this->skillRepository->method('findAll')->willReturn([]);

        $result = $this->service->syncFromDirectory(
            $this->validRepoDir,
            'abc123def456',
            'https://github.com/example/commit/abc123',
            'https://github.com/example/actions/runs/42',
        );

        // Verify the SyncLog entity
        $this->assertInstanceOf(SyncLog::class, $result);
        $this->assertSame('success', $result->getStatus());
        $this->assertSame(1, $result->getSkillCount());
        $this->assertSame('abc123def456', $result->getCommitSha());
        $this->assertSame('https://github.com/example/commit/abc123', $result->getCommitUrl());
        $this->assertSame('https://github.com/example/actions/runs/42', $result->getActionRunUrl());
        $this->assertNull($result->getErrorMessage());

        // SyncLog should have been persisted
        $syncLogs = array_filter($this->persisted, fn (object $e) => $e instanceof SyncLog);
        $this->assertCount(1, $syncLogs);

        // flush should have been called (at least once for skill upsert + log)
        $this->assertGreaterThanOrEqual(1, $this->flushCount);
    }
}
