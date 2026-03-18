<?php

namespace App\Tests\Service;

use App\Entity\Skill;
use App\Repository\SkillRepository;
use App\Service\SkillCompiler;
use PHPUnit\Framework\TestCase;

class SkillCompilerTest extends TestCase
{
    private string $outputDir;
    private SkillCompiler $compiler;
    private SkillRepository $skillRepository;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/opendispatch_test_' . uniqid();
        mkdir($this->outputDir, 0755, true);

        $this->skillRepository = $this->createStub(SkillRepository::class);
        $this->compiler = new SkillCompiler($this->skillRepository, $this->outputDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->outputDir);
    }

    public function testCompileCreatesIndexJson(): void
    {
        $skill = $this->createSkill('tesla', file_get_contents(__DIR__ . '/../fixtures/valid-skill.yaml'));
        $this->skillRepository->method('findPublished')->willReturn([$skill]);

        $this->compiler->compile();

        $indexPath = $this->outputDir . '/api/v1/index.json';
        $this->assertFileExists($indexPath);

        $index = json_decode(file_get_contents($indexPath), true);
        $this->assertSame(1, $index['version']);
        $this->assertSame(1, $index['skill_count']);
        $this->assertSame('tesla', $index['skills'][0]['skill_id']);
        $this->assertSame('Tesla', $index['skills'][0]['name']);
        $this->assertSame('1.0.0', $index['skills'][0]['version']);
        $this->assertSame(2, $index['skills'][0]['action_count']);
        $this->assertSame(3, $index['skills'][0]['example_count']);
        $this->assertSame(['test-tag'], $index['skills'][0]['tags']);
    }

    public function testCompileCreatesSkillYaml(): void
    {
        $yamlContent = file_get_contents(__DIR__ . '/../fixtures/valid-skill.yaml');
        $skill = $this->createSkill('tesla', $yamlContent);
        $this->skillRepository->method('findPublished')->willReturn([$skill]);

        $this->compiler->compile();

        $yamlPath = $this->outputDir . '/api/v1/skills/tesla/skill.yaml';
        $this->assertFileExists($yamlPath);
        $this->assertSame($yamlContent, file_get_contents($yamlPath));
    }

    public function testCompileCreatesInfoJson(): void
    {
        $skill = $this->createSkill('tesla', file_get_contents(__DIR__ . '/../fixtures/valid-skill.yaml'));
        $this->skillRepository->method('findPublished')->willReturn([$skill]);

        $this->compiler->compile();

        $infoPath = $this->outputDir . '/api/v1/skills/tesla/info.json';
        $this->assertFileExists($infoPath);

        $info = json_decode(file_get_contents($infoPath), true);
        $this->assertSame('tesla', $info['skill_id']);
        $this->assertCount(2, $info['actions']);
        $this->assertSame('vehicle.unlock', $info['actions'][0]['id']);
        $this->assertSame(2, $info['actions'][0]['example_count']);
        $this->assertFalse($info['actions'][0]['has_parameters']);
        $this->assertTrue($info['actions'][1]['has_parameters']);
    }

    public function testCompileRemovesUnpublishedSkillFiles(): void
    {
        $oldDir = $this->outputDir . '/api/v1/skills/old_skill';
        mkdir($oldDir, 0755, true);
        file_put_contents($oldDir . '/skill.yaml', 'old');

        $this->skillRepository->method('findPublished')->willReturn([]);
        $this->compiler->compile();

        $this->assertDirectoryDoesNotExist($oldDir);
    }

    public function testCompileWithEmptySkillListCreatesEmptyIndex(): void
    {
        $this->skillRepository->method('findPublished')->willReturn([]);
        $this->compiler->compile();

        $indexPath = $this->outputDir . '/api/v1/index.json';
        $this->assertFileExists($indexPath);

        $index = json_decode(file_get_contents($indexPath), true);
        $this->assertSame(0, $index['skill_count']);
        $this->assertEmpty($index['skills']);
    }

    private function createSkill(string $skillId, string $yamlContent): Skill
    {
        $skill = new Skill();
        $skill->setSkillId($skillId);
        $skill->setYamlContent($yamlContent);
        $skill->setTags(['test-tag']);
        $skill->setPublishedAt(new \DateTimeImmutable());
        return $skill;
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) return;
        foreach (new \DirectoryIterator($path) as $item) {
            if ($item->isDot()) continue;
            if ($item->isDir()) {
                $this->removeDir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
