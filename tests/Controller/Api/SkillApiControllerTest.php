<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Skill;
use App\Entity\SkillManifest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Yaml\Yaml;

class SkillApiControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\SkillDownload')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\SkillManifest')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Skill')->execute();
        parent::tearDown();
    }

    private function createTestSkill(): Skill
    {
        $yamlContent = file_get_contents(__DIR__ . '/../../fixtures/skills-repo-valid/skills/tesla/skill.yaml');
        $data = Yaml::parse($yamlContent);
        $skill = new Skill();
        $skill->setSkillId('tesla');
        $skill->updateFromYaml($yamlContent, $data);

        return $skill;
    }

    public function testIndexJson(): void
    {
        $manifest = new SkillManifest('{"skills":[]}', 'abc123');
        $this->em->persist($manifest);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/index.json');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/json', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame('{"skills":[]}', $this->client->getResponse()->getContent());
    }

    public function testIndexJsonReturns404WhenNoManifest(): void
    {
        $this->client->request('GET', '/api/v1/index.json');
        self::assertResponseStatusCodeSame(404);
    }

    public function testIndexJsonEtag(): void
    {
        $manifest = new SkillManifest('{"skills":[]}', 'abc123');
        $this->em->persist($manifest);
        $this->em->flush();

        // First request — expect 200 with ETag
        $this->client->request('GET', '/api/v1/index.json');
        self::assertResponseIsSuccessful();
        $etag = $this->client->getResponse()->headers->get('ETag');
        self::assertNotNull($etag);

        // Second request with If-None-Match — expect 304
        $this->client->request('GET', '/api/v1/index.json', [], [], [
            'HTTP_IF_NONE_MATCH' => $etag,
        ]);
        self::assertResponseStatusCodeSame(304);
    }

    public function testSkillInfoJson(): void
    {
        $skill = $this->createTestSkill();
        $skill->setCompiledInfo('{"skill_id":"tesla","name":"Tesla"}');
        $this->em->persist($skill);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/skills/tesla/info.json');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/json', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame('{"skill_id":"tesla","name":"Tesla"}', $this->client->getResponse()->getContent());
    }

    public function testSkillInfoJsonReturns404WhenNoCompiledInfo(): void
    {
        $skill = $this->createTestSkill();
        $this->em->persist($skill);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/skills/tesla/info.json');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSkillYaml(): void
    {
        $skill = $this->createTestSkill();
        $this->em->persist($skill);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/skills/tesla/skill.yaml');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/yaml', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('skill_id: tesla', $this->client->getResponse()->getContent());
    }

    public function testSkillIcon(): void
    {
        // Create a minimal 1x1 PNG and base64-encode it
        $pngBytes = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xde\x00\x00\x00\x0cIDATx\x9cc\xf8\x0f\x00\x00\x01\x01\x00\x05\x18\xd8N\x00\x00\x00\x00IEND\xaeB`\x82";
        $iconData = base64_encode($pngBytes);

        $skill = $this->createTestSkill();
        $skill->setIconData($iconData);
        $this->em->persist($skill);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/skills/tesla/icon.png');

        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame($pngBytes, $this->client->getResponse()->getContent());
    }

    public function testSkillIconReturns404WhenNoIconData(): void
    {
        $skill = $this->createTestSkill();
        $this->em->persist($skill);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/skills/tesla/icon.png');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSkillShortcut(): void
    {
        $binaryData = "shortcut binary content";
        $shortcutData = base64_encode($binaryData);

        $skill = $this->createTestSkill();
        $skill->setShortcutData($shortcutData);
        $skill->setBridgeShortcutName('TestShortcut');
        $this->em->persist($skill);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/skills/tesla/TestShortcut.shortcut');

        self::assertResponseIsSuccessful();
        self::assertSame('application/octet-stream', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename="TestShortcut.shortcut"', $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertSame($binaryData, $this->client->getResponse()->getContent());
    }

    public function testShortcutWrongFilename404(): void
    {
        $skill = $this->createTestSkill();
        $skill->setShortcutData(base64_encode('some data'));
        $skill->setBridgeShortcutName('TestShortcut');
        $this->em->persist($skill);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/skills/tesla/WrongName.shortcut');
        self::assertResponseStatusCodeSame(404);
    }

    public function testNonexistentSkill404(): void
    {
        $this->client->request('GET', '/api/v1/skills/nonexistent/info.json');
        self::assertResponseStatusCodeSame(404);
    }
}
