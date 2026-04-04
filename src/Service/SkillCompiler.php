<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Skill;
use App\Repository\SkillRepository;
use App\Trait\LoggerTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class SkillCompiler
{
    use LoggerTrait;

    private Filesystem $filesystem;

    public function __construct(
        private SkillRepository $skillRepository,
        private string $publicDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function compile(): void
    {
        $skills = $this->skillRepository->findAll();
        $this->compileIndex($skills);

        foreach ($skills as $skill) {
            $this->compileSkill($skill);
        }

        $this->cleanupRemovedSkills($skills);

        $this->logger?->info('Compilation complete', ['skillCount' => count($skills)]);
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
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }

        file_put_contents(
            $dir . '/index.json',
            json_encode($index, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    private function compileSkill(Skill $skill): void
    {
        $skillDir = $this->publicDir . '/api/v1/skills/' . $skill->getSkillId();
        if (!@mkdir($skillDir, 0755, true) && !is_dir($skillDir)) {
            throw new \RuntimeException("Failed to create directory: {$skillDir}");
        }

        // Write YAML
        file_put_contents($skillDir . '/skill.yaml', $skill->getYamlContent());

        // Write info.json (needs YAML parsing for action details)
        $data = Yaml::parse($skill->getYamlContent());
        $info = $this->buildSkillInfo($skill, $data);
        file_put_contents(
            $skillDir . '/info.json',
            json_encode($info, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        // Copy icon if available
        if ($skill->getIconPath() && file_exists($skill->getIconPath())) {
            $this->filesystem->copy($skill->getIconPath(), $skillDir . '/icon.png', true);
        }

        // Copy bridge shortcut if available, preserving its original name
        if ($skill->getBridgeShortcutFilePath() && file_exists($skill->getBridgeShortcutFilePath())) {
            $shortcutName = ($data['bridge_shortcut'] ?? '') . '.shortcut';
            $this->filesystem->copy($skill->getBridgeShortcutFilePath(), $skillDir . '/' . $shortcutName, true);
        }
    }

    private function buildIndexEntry(Skill $skill): array
    {
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
            'download_url' => '/api/v1/skills/' . $skill->getSkillId() . '/download',
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
            'name' => $skill->getName(),
            'version' => $skill->getVersion(),
            'description' => $skill->getDescription(),
            'author' => $skill->getAuthor(),
            'author_url' => $skill->getAuthorUrl(),
            'tags' => $skill->getTags(),
            'languages' => $skill->getLanguages(),
            'requires_bridge_shortcut' => $skill->requiresBridgeShortcut(),
            'bridge_shortcut_name' => $skill->getBridgeShortcutName(),
            'bridge_shortcut_share_url' => $skill->getBridgeShortcutShareUrl(),
            'actions' => $actions,
            'created_at' => $skill->getCreatedAt()->format('c'),
            'updated_at' => $skill->getUpdatedAt()->format('c'),
        ];
    }

    /** @param Skill[] $currentSkills */
    private function cleanupRemovedSkills(array $currentSkills): void
    {
        $skillsDir = $this->publicDir . '/api/v1/skills';
        if (!is_dir($skillsDir)) {
            return;
        }

        $currentIds = array_map(static fn(Skill $s) => $s->getSkillId(), $currentSkills);

        foreach (new \DirectoryIterator($skillsDir) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }
            if (!in_array($item->getFilename(), $currentIds, true)) {
                $this->filesystem->remove($item->getPathname());
            }
        }
    }

}
