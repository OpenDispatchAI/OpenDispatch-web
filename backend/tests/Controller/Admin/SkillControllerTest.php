<?php

namespace App\Tests\Controller\Admin;

use App\Entity\AdminUser;
use App\Entity\Skill;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SkillControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = new AdminUser();
        $admin->setEmail('test@test.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin->setPassword($hasher->hashPassword($admin, 'password'));
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);
    }

    protected function tearDown(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\Skill')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\AdminUser')->execute();
        parent::tearDown();
    }

    public function testSkillListPageLoads(): void
    {
        $this->client->request('GET', '/admin/skills');
        $this->assertResponseIsSuccessful();
    }

    public function testCreateSkillWithValidYaml(): void
    {
        $yamlPath = __DIR__ . '/../../fixtures/valid-skill.yaml';
        $uploadedFile = new UploadedFile($yamlPath, 'skill.yaml', 'text/yaml', null, true);

        $this->client->request('GET', '/admin/skills/new');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'skill[yamlFile]' => $uploadedFile,
            'skill[tags]' => 'automotive, smart-home',
        ]);

        $this->assertResponseRedirects('/admin/skills');

        $skill = $this->em->getRepository(Skill::class)->findOneBy(['skillId' => 'tesla']);
        $this->assertNotNull($skill);
        $this->assertSame(['automotive', 'smart-home'], $skill->getTags());
    }

    public function testCreateSkillWithInvalidYamlShowsErrors(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'yaml');
        file_put_contents($tmpFile, "skill_id: broken\nname: Broken");
        $uploadedFile = new UploadedFile($tmpFile, 'skill.yaml', 'text/yaml', null, true);

        $this->client->request('GET', '/admin/skills/new');
        $this->client->submitForm('Save', [
            'skill[yamlFile]' => $uploadedFile,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testPublishAndUnpublishSkill(): void
    {
        $skill = new Skill();
        $skill->setSkillId('test');
        $skill->setYamlContent(file_get_contents(__DIR__ . '/../../fixtures/valid-skill.yaml'));
        $this->em->persist($skill);
        $this->em->flush();
        $id = $skill->getId();

        $this->client->request('POST', '/admin/skills/' . $id . '/publish');
        $this->assertResponseRedirects('/admin/skills');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $skill = $em->getRepository(Skill::class)->find($id);
        $this->assertNotNull($skill->getPublishedAt());

        $this->client->request('POST', '/admin/skills/' . $id . '/unpublish');
        $this->assertResponseRedirects('/admin/skills');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $skill = $em->getRepository(Skill::class)->find($id);
        $this->assertNull($skill->getPublishedAt());
    }

    public function testDeleteSkill(): void
    {
        $skill = new Skill();
        $skill->setSkillId('deleteme');
        $skill->setYamlContent(file_get_contents(__DIR__ . '/../../fixtures/valid-skill.yaml'));
        $this->em->persist($skill);
        $this->em->flush();
        $id = $skill->getId();

        // Load the list page first to get a valid CSRF token from the rendered form
        $crawler = $this->client->request('GET', '/admin/skills');
        $deleteForm = $crawler->filter('form[action$="/delete"]')->form();

        $this->client->submit($deleteForm);

        $this->assertResponseRedirects('/admin/skills');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->assertNull($em->getRepository(Skill::class)->find($id));
    }
}
