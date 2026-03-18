<?php

namespace App\Service;

use App\Entity\Skill;
use App\Repository\SkillRepository;
use Symfony\Component\Yaml\Yaml;

class SkillCompiler
{
    public function __construct(
        private SkillRepository $skillRepository,
        private string $publicDir,
    ) {}

    public function compile(): void
    {
        $skills = $this->skillRepository->findPublished();
        $this->compileIndex($skills);
        foreach ($skills as $skill) {
            $this->compileSkill($skill);
        }
        $this->cleanupRemovedSkills($skills);
    }

    /** @param Skill[] $skills */
    private function compileIndex(array $skills): void
    {
        $index = [
            'version' => 1,
            'generated_at' => (new \DateTimeImmutable())->format('c'),
            'skill_count' => count($skills),
            'skills' => array_map($this->buildIndexEntry(...), $skills),
        ];

        $dir = $this->publicDir . '/api/v1';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir . '/index.json',
            json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function compileSkill(Skill $skill): void
    {
        $data = Yaml::parse($skill->getYamlContent());
        $skillDir = $this->publicDir . '/api/v1/skills/' . $skill->getSkillId();

        if (!is_dir($skillDir)) {
            mkdir($skillDir, 0755, true);
        }

        file_put_contents($skillDir . '/skill.yaml', $skill->getYamlContent());

        $info = $this->buildSkillInfo($skill, $data);
        file_put_contents(
            $skillDir . '/info.json',
            json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if ($skill->getIconPath() && file_exists($skill->getIconPath())) {
            copy($skill->getIconPath(), $skillDir . '/icon.png');
        }
    }

    private function buildIndexEntry(Skill $skill): array
    {
        $data = Yaml::parse($skill->getYamlContent());
        $actions = $data['actions'] ?? [];

        $exampleCount = 0;
        foreach ($actions as $action) {
            $exampleCount += count($action['examples'] ?? []);
        }

        return [
            'skill_id' => $skill->getSkillId(),
            'name' => $data['name'] ?? '',
            'version' => $data['version'] ?? '',
            'description' => $data['description'] ?? '',
            'author' => $data['author'] ?? '',
            'author_url' => $data['author_url'] ?? null,
            'action_count' => count($actions),
            'example_count' => $exampleCount,
            'tags' => $skill->getTags(),
            'languages' => $data['languages'] ?? [],
            'requires_bridge_shortcut' => isset($data['bridge_shortcut']),
            'bridge_shortcut_share_url' => $data['bridge_shortcut']['share_url'] ?? null,
            'download_url' => '/api/v1/skills/' . $skill->getSkillId() . '/skill.yaml',
            'icon_url' => $skill->getIconPath()
                ? '/api/v1/skills/' . $skill->getSkillId() . '/icon.png'
                : null,
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
            'name' => $data['name'] ?? '',
            'version' => $data['version'] ?? '',
            'description' => $data['description'] ?? '',
            'author' => $data['author'] ?? '',
            'author_url' => $data['author_url'] ?? null,
            'tags' => $skill->getTags(),
            'languages' => $data['languages'] ?? [],
            'requires_bridge_shortcut' => isset($data['bridge_shortcut']),
            'bridge_shortcut_name' => $data['bridge_shortcut']['name'] ?? null,
            'bridge_shortcut_share_url' => $data['bridge_shortcut']['share_url'] ?? null,
            'actions' => $actions,
            'created_at' => $skill->getCreatedAt()->format('c'),
            'updated_at' => $skill->getUpdatedAt()->format('c'),
        ];
    }

    /** @param Skill[] $publishedSkills */
    private function cleanupRemovedSkills(array $publishedSkills): void
    {
        $skillsDir = $this->publicDir . '/api/v1/skills';
        if (!is_dir($skillsDir)) return;

        $publishedIds = array_map(fn(Skill $s) => $s->getSkillId(), $publishedSkills);

        foreach (new \DirectoryIterator($skillsDir) as $item) {
            if ($item->isDot() || !$item->isDir()) continue;
            if (!in_array($item->getFilename(), $publishedIds, true)) {
                $this->removeDirectory($item->getPathname());
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        foreach (new \DirectoryIterator($path) as $item) {
            if ($item->isDot()) continue;
            if ($item->isDir()) {
                $this->removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
