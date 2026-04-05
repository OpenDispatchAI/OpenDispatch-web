<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Skill;
use App\Entity\SkillManifest;
use App\Trait\LoggerTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

class SkillCompiler
{
    use LoggerTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private RouterInterface $router,
    ) {}

    /** @param Skill[] $skills */
    public function compile(array $skills, string $commitSha): void
    {
        foreach ($skills as $skill) {
            $this->compileSkill($skill);
        }

        $this->compileIndex($skills, $commitSha);

        $this->logger?->info('Compilation complete', ['skillCount' => count($skills)]);
    }

    private function compileSkill(Skill $skill): void
    {
        $data = Yaml::parse($skill->getYamlContent());
        $info = $this->buildSkillInfo($skill, $data);
        $skill->setCompiledInfo(
            json_encode($info, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );
    }

    /** @param Skill[] $skills */
    private function compileIndex(array $skills, string $commitSha): void
    {
        $index = [
            'version' => 1,
            'generated_at' => (new \DateTimeImmutable())->format('c'),
            'skill_count' => count($skills),
            'skills' => array_map($this->buildIndexEntry(...), $skills),
        ];

        $json = json_encode($index, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $manifest = new SkillManifest($json, $commitSha);
        $this->em->persist($manifest);
    }

    private function buildIndexEntry(Skill $skill): array
    {
        $ctx = $this->router->getContext();
        $baseUrl = $ctx->getScheme() . '://' . $ctx->getHost();

        return [
            'skill_id' => $skill->getSkillId(),
            'name' => $skill->getName(),
            'version' => $skill->getVersion(),
            'description' => $skill->getDescription(),
            'author' => $skill->getAuthor(),
            'author_url' => $skill->getAuthorUrl(),
            'action_count' => $skill->getActionCount(),
            'example_count' => $skill->getExampleCount(),
            'tags' => $skill->getTags(),
            'languages' => $skill->getLanguages(),
            'requires_bridge_shortcut' => $skill->requiresBridgeShortcut(),
            'bridge_shortcut_share_url' => $skill->getBridgeShortcutShareUrl(),
            'download_url' => $baseUrl . '/api/v1/skills/' . $skill->getSkillId() . '/download',
            'icon' => $skill->getIconData(),
            'created_at' => $skill->getCreatedAt()->format('c'),
            'updated_at' => $skill->getUpdatedAt()->format('c'),
        ];
    }

    private function buildSkillInfo(Skill $skill, array $data): array
    {
        $actions = [];
        foreach ($data['actions'] ?? [] as $action) {
            $actions[] = [
                'id' => $action['id'],
                'title' => $action['title'] ?? '',
                'description' => $action['description'] ?? '',
                'example_count' => count($action['examples'] ?? []),
                'has_parameters' => !empty($action['parameters']),
                'confirmation' => $action['confirmation'] ?? null,
            ];
        }

        return [
            'skill_id' => $skill->getSkillId(),
            'name' => $skill->getName(),
            'version' => $skill->getVersion(),
            'description' => $skill->getDescription(),
            'author' => $skill->getAuthor(),
            'author_url' => $skill->getAuthorUrl(),
            'tags' => $skill->getTags(),
            'languages' => $skill->getLanguages(),
            'requires_bridge_shortcut' => $skill->requiresBridgeShortcut(),
            'bridge_shortcut' => $skill->getBridgeShortcutName(),
            'bridge_shortcut_share_url' => $skill->getBridgeShortcutShareUrl(),
            'actions' => $actions,
            'created_at' => $skill->getCreatedAt()->format('c'),
            'updated_at' => $skill->getUpdatedAt()->format('c'),
        ];
    }
}
