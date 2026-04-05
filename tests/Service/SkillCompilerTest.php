<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Skill;
use App\Entity\SkillManifest;
use App\Service\SkillCompiler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

class SkillCompilerTest extends TestCase
{
    private array $persisted = [];
    private SkillCompiler $compiler;
    private string $fixtureYaml;

    protected function setUp(): void
    {
        $this->persisted = [];

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $context = new RequestContext();
        $context->setScheme('https');
        $context->setHost('app.opendispatch.org');
        $router = $this->createStub(RouterInterface::class);
        $router->method('getContext')->willReturn($context);

        $this->compiler = new SkillCompiler($em, $router);
        $this->fixtureYaml = file_get_contents(__DIR__ . '/../fixtures/skills-repo/skills/tesla/skill.yaml');
    }

    public function testCompileCreatesIndexJson(): void
    {
        $skill = $this->createSkill('tesla', $this->fixtureYaml);

        $this->compiler->compile([$skill], 'abc123');

        $manifests = array_values(array_filter($this->persisted, fn($e) => $e instanceof SkillManifest));
        self::assertCount(1, $manifests);

        $index = json_decode($manifests[0]->getContent(), true);

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
        self::assertSame('https://app.opendispatch.org/api/v1/skills/tesla/download', $entry['download_url']);
        self::assertNull($entry['icon']);
        self::assertArrayHasKey('example_count', $entry);
        self::assertArrayHasKey('created_at', $entry);
        self::assertArrayHasKey('updated_at', $entry);
    }

    public function testCompileCreatesInfoJson(): void
    {
        $skill = $this->createSkill('tesla', $this->fixtureYaml);

        $this->compiler->compile([$skill], 'abc123');

        $compiledInfo = $skill->getCompiledInfo();
        self::assertNotNull($compiledInfo);

        $info = json_decode($compiledInfo, true);

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

    public function testCompileWithEmptySkillListCreatesEmptyIndex(): void
    {
        $this->compiler->compile([], 'abc123');

        $manifests = array_values(array_filter($this->persisted, fn($e) => $e instanceof SkillManifest));
        self::assertCount(1, $manifests);

        $index = json_decode($manifests[0]->getContent(), true);

        self::assertSame(1, $index['version']);
        self::assertSame(0, $index['skill_count']);
        self::assertSame([], $index['skills']);
    }

    public function testCompileUsesAbsoluteUrls(): void
    {
        $skill = $this->createSkill('tesla', $this->fixtureYaml);

        $this->compiler->compile([$skill], 'abc123');

        $manifests = array_values(array_filter($this->persisted, fn($e) => $e instanceof SkillManifest));
        $index = json_decode($manifests[0]->getContent(), true);
        $entry = $index['skills'][0];

        self::assertStringStartsWith('https://', $entry['download_url']);
    }

    public function testCompileIncludesIconInIndex(): void
    {
        $skill = $this->createSkill('tesla', $this->fixtureYaml);
        $skill->setIconData('iVBORw0KGgoAAAANSUhEUg==');

        $this->compiler->compile([$skill], 'abc123');

        $manifests = array_values(array_filter($this->persisted, fn($e) => $e instanceof SkillManifest));
        $index = json_decode($manifests[0]->getContent(), true);
        $entry = $index['skills'][0];

        self::assertSame('iVBORw0KGgoAAAANSUhEUg==', $entry['icon']);
    }

    public function testCompileStoresCompiledInfoOnSkill(): void
    {
        $skill = $this->createSkill('tesla', $this->fixtureYaml);

        self::assertNull($skill->getCompiledInfo());

        $this->compiler->compile([$skill], 'abc123');

        self::assertNotNull($skill->getCompiledInfo());
        $info = json_decode($skill->getCompiledInfo(), true);
        self::assertSame('tesla', $info['skill_id']);
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
}
