<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Skill;
use App\Entity\SyncLog;
use App\Repository\SkillRepository;
use App\Service\GitClient;
use App\Service\SkillCompiler;
use App\Service\SkillSyncService;
use App\Service\SkillYamlValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class SkillSyncServiceTest extends TestCase
{
    /** @var object[] */
    private array $persisted = [];

    /** @var object[] */
    private array $removed = [];

    private int $flushCount = 0;

    private SkillRepository $skillRepository;
    private SkillSyncService $service;

    /**
     * Copy a fixture dir to a temp location so sync()'s finally block can safely delete it.
     */
    private function copyFixturesToTmp(string $fixtureDir): string
    {
        $tmpDir = sys_get_temp_dir() . '/opendispatch-test-' . bin2hex(random_bytes(4));
        (new Filesystem())->mirror($fixtureDir, $tmpDir);

        return $tmpDir;
    }

    protected function setUp(): void
    {
        $this->persisted = [];
        $this->removed = [];
        $this->flushCount = 0;

        $em = $this->createStub(EntityManagerInterface::class);
        $this->skillRepository = $this->createStub(SkillRepository::class);
        $compiler = $this->createStub(SkillCompiler::class);

        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $em->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
        });
        $em->method('flush')->willReturnCallback(function (): void {
            $this->flushCount++;
        });

        // GitClient stub returns a temp copy of the valid fixtures
        $tmpDir = $this->copyFixturesToTmp(__DIR__ . '/../fixtures/skills-repo-valid');
        $gitClient = $this->createStub(GitClient::class);
        $gitClient->method('clone')->willReturn($tmpDir);

        $this->service = new SkillSyncService(
            $em,
            $this->skillRepository,
            new SkillYamlValidator(),
            $compiler,
            $gitClient,
            'https://example.com/skills.git',
        );
    }

    private function serviceWithInvalidRepo(): SkillSyncService
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $em->method('flush')->willReturnCallback(function (): void {
            $this->flushCount++;
        });

        $skillRepo = $this->createStub(SkillRepository::class);
        $skillRepo->method('findOneBy')->willReturn(null);
        $skillRepo->method('findAll')->willReturn([]);

        $tmpDir = $this->copyFixturesToTmp(__DIR__ . '/../fixtures/skills-repo');
        $gitClient = $this->createStub(GitClient::class);
        $gitClient->method('clone')->willReturn($tmpDir);

        return new SkillSyncService(
            $em,
            $skillRepo,
            new SkillYamlValidator(),
            $this->createStub(SkillCompiler::class),
            $gitClient,
            'https://example.com/skills.git',
        );
    }

    public function testSyncCreatesNewSkills(): void
    {
        $this->skillRepository->method('findOneBy')->willReturn(null);
        $this->skillRepository->method('findAll')->willReturn([]);

        $result = $this->service->sync('abc123def456', 'https://github.com/example/commit/abc123', 'https://github.com/example/actions/runs/1');

        self::assertSame('success', $result->getStatus());
        self::assertSame(1, $result->getSkillCount());

        $skills = array_filter($this->persisted, fn(object $e) => $e instanceof Skill);
        self::assertCount(1, $skills);

        $skill = reset($skills);
        self::assertSame('tesla', $skill->getSkillId());
        self::assertSame('Tesla', $skill->getName());
        self::assertSame('1.0.0', $skill->getVersion());
        self::assertSame(2, $skill->getActionCount());
        self::assertSame(['automotive', 'smart-home'], $skill->getTags());
    }

    public function testSyncAbortsOnInvalidYaml(): void
    {
        $service = $this->serviceWithInvalidRepo();

        $result = $service->sync('abc123def456', 'https://github.com/example/commit/abc123', null);

        self::assertSame('failed', $result->getStatus());
        self::assertSame(0, $result->getSkillCount());
        self::assertNotNull($result->getErrorMessage());
        self::assertStringContainsString('broken', $result->getErrorMessage());

        $skillsPersisted = array_filter($this->persisted, fn(object $e) => $e instanceof Skill);
        self::assertCount(0, $skillsPersisted);
    }

    public function testSyncUpsertsExistingSkill(): void
    {
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

        $result = $this->service->sync('abc123def456', 'https://github.com/example/commit/abc123', null);

        self::assertSame('success', $result->getStatus());

        $skillsPersisted = array_filter($this->persisted, fn(object $e) => $e instanceof Skill);
        self::assertCount(0, $skillsPersisted, 'Existing skill should NOT be re-persisted');

        self::assertSame('Tesla', $existingSkill->getName());
        self::assertSame('1.0.0', $existingSkill->getVersion());
        self::assertSame(2, $existingSkill->getActionCount());
    }

    public function testSyncPreservesIsFeatured(): void
    {
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

        $result = $this->service->sync('abc123def456', 'https://github.com/example/commit/abc123', null);

        self::assertSame('success', $result->getStatus());
        self::assertTrue($existingSkill->isFeatured(), 'isFeatured must be preserved after sync');
    }

    public function testSyncDeletesOrphanedSkills(): void
    {
        $orphanSkill = new Skill();
        $orphanSkill->setSkillId('deleted-skill');
        $orphanSkill->setName('Deleted');
        $orphanSkill->setVersion('1.0.0');
        $orphanSkill->setDescription('Should be removed');
        $orphanSkill->setAuthor('test');
        $orphanSkill->setYamlContent('old yaml');
        $orphanSkill->setActionCount(0);
        $orphanSkill->setExampleCount(0);

        $this->skillRepository->method('findOneBy')->willReturn(null);
        $this->skillRepository->method('findAll')->willReturn([$orphanSkill]);

        $result = $this->service->sync('abc123def456', 'https://github.com/example/commit/abc123', null);

        self::assertSame('success', $result->getStatus());
        self::assertContains($orphanSkill, $this->removed, 'Orphaned skill should be removed');
    }

    public function testSyncLogsSyncResult(): void
    {
        $this->skillRepository->method('findOneBy')->willReturn(null);
        $this->skillRepository->method('findAll')->willReturn([]);

        $result = $this->service->sync(
            'abc123def456',
            'https://github.com/example/commit/abc123',
            'https://github.com/example/actions/runs/42',
        );

        self::assertInstanceOf(SyncLog::class, $result);
        self::assertSame('success', $result->getStatus());
        self::assertSame(1, $result->getSkillCount());
        self::assertSame('abc123def456', $result->getCommitSha());
        self::assertSame('https://github.com/example/commit/abc123', $result->getCommitUrl());
        self::assertSame('https://github.com/example/actions/runs/42', $result->getActionRunUrl());
        self::assertNull($result->getErrorMessage());

        $syncLogs = array_filter($this->persisted, fn(object $e) => $e instanceof SyncLog);
        self::assertCount(1, $syncLogs);
    }
}
