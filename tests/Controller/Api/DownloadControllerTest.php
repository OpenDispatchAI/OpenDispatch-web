<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Skill;
use App\Entity\SkillDownload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Yaml\Yaml;

class DownloadControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Create a test skill
        $yamlContent = file_get_contents(__DIR__ . '/../../fixtures/skills-repo-valid/skills/tesla/skill.yaml');
        $data = Yaml::parse($yamlContent);
        $skill = new Skill();
        $skill->setSkillId('tesla');
        $skill->updateFromYaml($yamlContent, $data);
        $this->em->persist($skill);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\SkillDownload')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Skill')->execute();
        parent::tearDown();
    }

    public function testDownloadReturnsYamlContent(): void
    {
        $this->client->request('GET', '/api/v1/skills/tesla/download');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('text/yaml', $this->client->getResponse()->headers->get('Content-Type'));
        $this->assertResponseHeaderSame('Content-Disposition', 'attachment; filename="skill.yaml"');
        $this->assertStringContainsString('skill_id: tesla', $this->client->getResponse()->getContent());
    }

    public function testDownloadCreatesSkillDownloadRecord(): void
    {
        $this->client->request('GET', '/api/v1/skills/tesla/download', [], [], [
            'HTTP_X_OPENDISPATCH_VERSION' => '1.2.0',
        ]);

        $this->assertResponseIsSuccessful();

        $downloads = $this->em->getRepository(SkillDownload::class)->findAll();
        $this->assertCount(1, $downloads);
        $this->assertSame('1.2.0', $downloads[0]->getAppVersion());
    }

    public function testDownloadWithoutVersionHeaderStoresNull(): void
    {
        $this->client->request('GET', '/api/v1/skills/tesla/download');

        $downloads = $this->em->getRepository(SkillDownload::class)->findAll();
        $this->assertCount(1, $downloads);
        $this->assertNull($downloads[0]->getAppVersion());
    }

    public function testDownloadReturns404ForUnknownSkill(): void
    {
        $this->client->request('GET', '/api/v1/skills/nonexistent/download');
        $this->assertResponseStatusCodeSame(404);
    }
}
