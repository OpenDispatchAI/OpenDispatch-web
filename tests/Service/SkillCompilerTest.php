<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Skill;
use App\Repository\SkillRepository;
use App\Service\SkillCompiler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class SkillCompilerTest extends TestCase
{
    private string $publicDir;
    private SkillRepository $skillRepository;
    private SkillCompiler $compiler;
    private string $fixtureYaml;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/skill-compiler-test-' . uniqid();
        mkdir($this->publicDir, 0755, true);

        $this->skillRepository = $this->createStub(SkillRepository::class);
        $this->compiler = new SkillCompiler($this->skillRepository, $this->publicDir);
        $this->fixtureYaml = file_get_contents(__DIR__ . '/../fixtures/skills-repo/skills/tesla/skill.yaml');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->publicDir);
    }

    public function testCompileCreatesIndexJson(): void
    {
        $skill = $this->createSkill('tesla', $this->fixtureYaml);

        $this->skillRepository->method('findAll')->willReturn([$skill]);

        $this->compiler->compile();

        $indexPath = $this->publicDir . '/api/v1/index.json';
        self::assertFileExists($indexPath);

        $index = json_decode(file_get_contents($indexPath), true);

        self::assertSame(1, $index['version']);
        self::assertSame(1, $index['skill_count']);
        self::assertArrayHasKey('generated_at', $index);
        self::assertCount(1, $index['skills']);

        $entry = $index['skills'][0];
        self::assertSame('tesla', $entry['skill_id']);
        self::assertSame('Tesla', $entry['name']);
        self::assertSame('1.0.0', $entry['version']);
        self::assertSame('Control your Tesla', $entry['description']);
        self::assertSame('opendispatch', $entry['author']);
        self::assertSame('https://opendispatch.ai', $entry['author_url']);
        self::assertSame(2, $entry['action_count']);
        self::assertSame(['test-tag'], $entry['tags']);
        self::assertSame(['en'], $entry['languages']);
        self::assertSame('/api/v1/skills/tesla/download', $entry['download_url']);
        self::assertNull($entry['icon_url']);
        self::assertArrayHasKey('example_count', $entry);
        self::assertArrayHasKey('created_at', $entry);
        self::assertArrayHasKey('updated_at', $entry);
    }

    public function testCompileCreatesSkillYaml(): void
    {
        $skill = $this->createSkill('tesla', $this->fixtureYaml);

        $this->skillRepository->method('findAll')->willReturn([$skill]);

        $this->compiler->compile();

        $yamlPath = $this->publicDir . '/api/v1/skills/tesla/skill.yaml';
        self::assertFileExists($yamlPath);
        self::assertSame($this->fixtureYaml, file_get_contents($yamlPath));
    }

    public function testCompileCreatesInfoJson(): void
    {
        $skill = $this->createSkill('tesla', $this->fixtureYaml);

        $this->skillRepository->method('findAll')->willReturn([$skill]);

        $this->compiler->compile();

        $infoPath = $this->publicDir . '/api/v1/skills/tesla/info.json';
        self::assertFileExists($infoPath);

        $info = json_decode(file_get_contents($infoPath), true);

        self::assertSame('tesla', $info['skill_id']);
        self::assertSame('Tesla', $info['name']);
        self::assertCount(2, $info['actions']);

        $firstAction = $info['actions'][0];
        self::assertSame('vehicle.unlock', $firstAction['id']);
        self::assertSame('Unlock', $firstAction['title']);
        self::assertSame('Unlock the car doors', $firstAction['description']);
        self::assertSame(2, $firstAction['example_count']);
        self::assertFalse($firstAction['has_parameters']);

        $secondAction = $info['actions'][1];
        self::assertSame('vehicle.climate.set_temperature', $secondAction['id']);
        self::assertSame('Set Temperature', $secondAction['title']);
        self::assertSame(1, $secondAction['example_count']);
        self::assertTrue($secondAction['has_parameters']);
    }

    public function testCompileRemovesOrphanedSkillFiles(): void
    {
        // Create an orphaned skill directory
        $orphanDir = $this->publicDir . '/api/v1/skills/old-skill';
        mkdir($orphanDir, 0755, true);
        file_put_contents($orphanDir . '/skill.yaml', 'orphan content');

        $skill = $this->createSkill('tesla', $this->fixtureYaml);

        $this->skillRepository->method('findAll')->willReturn([$skill]);

        $this->compiler->compile();

        self::assertDirectoryDoesNotExist($orphanDir);
        self::assertDirectoryExists($this->publicDir . '/api/v1/skills/tesla');
    }

    public function testCompileWithEmptySkillListCreatesEmptyIndex(): void
    {
        $this->skillRepository->method('findAll')->willReturn([]);

        $this->compiler->compile();

        $indexPath = $this->publicDir . '/api/v1/index.json';
        self::assertFileExists($indexPath);

        $index = json_decode(file_get_contents($indexPath), true);

        self::assertSame(1, $index['version']);
        self::assertSame(0, $index['skill_count']);
        self::assertSame([], $index['skills']);
    }

    private function createSkill(string $skillId, string $yamlContent): Skill
    {
        $skill = new Skill();
        $skill->setSkillId($skillId);
        $data = Yaml::parse($yamlContent);
        $skill->updateFromYaml($yamlContent, $data);
        $skill->setTags(['test-tag']); // override tags for test assertion
        return $skill;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (new \DirectoryIterator($path) as $item) {
            if ($item->isDot()) {
                continue;
            }
            if ($item->isDir()) {
                $this->removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
