<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Skill;
use App\Entity\SyncLog;
use App\Repository\SkillManifestRepository;
use App\Repository\SkillRepository;
use App\Trait\LoggerTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

class SkillSyncService
{
    use LoggerTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SkillRepository $skillRepository,
        private readonly SkillYamlValidator $validator,
        private readonly SkillCompiler $compiler,
        private readonly GitClient $gitClient,
        private readonly RouterInterface $router,
        private readonly string $skillsRepoUrl,
        private readonly SkillManifestRepository $manifestRepository,
    ) {}

    public function sync(
        string $commitSha,
        string $commitUrl,
        ?string $actionRunUrl,
    ): SyncLog {
        $this->logger?->info('Starting sync', ['commitSha' => $commitSha]);

        // 1. Clone repo to temp dir
        $repoDir = $this->gitClient->clone($this->skillsRepoUrl);

        try {
            return $this->processRepo($repoDir, $commitSha, $commitUrl, $actionRunUrl);
        } finally {
            (new Filesystem())->remove($repoDir);
        }
    }

    private function processRepo(
        string $repoDir,
        string $commitSha,
        string $commitUrl,
        ?string $actionRunUrl,
    ): SyncLog {
        // 2. Read allowed tags
        $allowedTags = $this->readAllowedTags($repoDir);

        // 3. Find all skill YAMLs
        $skillDirs = glob($repoDir . '/skills/*/skill.yaml') ?: [];

        // 4. Validate ALL before making any changes
        $parsedSkills = [];
        foreach ($skillDirs as $yamlPath) {
            $yamlContent = file_get_contents($yamlPath);
            $errors = $this->validator->validate($yamlContent, $allowedTags);

            if (!empty($errors)) {
                $skillDir = basename(dirname($yamlPath));
                $this->logger?->warning('Sync aborted: validation failed', ['skill' => $skillDir, 'errors' => $errors]);

                return $this->logResult(
                    'failed', 0, $commitSha, $commitUrl, $actionRunUrl,
                    'Validation failed for ' . $skillDir . ': ' . implode('; ', $errors)
                );
            }

            $data = $this->validator->extractSkillData($yamlContent);
            $skillDir = dirname($yamlPath);
            $shortcutFilePath = null;

            // If skill has a bridge_shortcut_source, find the compiled .shortcut file
            if (!empty($data['bridge_shortcut_source'])) {
                $shortcutFileName = preg_replace('/\.cherri$/', '.shortcut', $data['bridge_shortcut_source']);
                $resolvedSkillDir = realpath($skillDir);

                // Resolve the real path to guard against directory traversal in user-supplied filenames
                $resolvedPath = realpath($resolvedSkillDir . '/' . $shortcutFileName);
                if ($resolvedPath === false || !str_starts_with($resolvedPath, $resolvedSkillDir . '/')) {
                    $errorDetail = $resolvedPath === false
                        ? 'expected ' . $shortcutFileName
                        : 'path traversal detected';

                    return $this->logResult(
                        'failed', 0, $commitSha, $commitUrl, $actionRunUrl,
                        'Invalid bridge_shortcut_source for ' . basename($skillDir) . ': ' . $errorDetail,
                    );
                }
                $shortcutFilePath = $resolvedPath;

                // Rewrite YAML: replace bridge_shortcut_source with bridge_shortcut_share_url
                $shortcutPublicName = $data['bridge_shortcut'] . '.shortcut';
                $ctx = $this->router->getContext();
                $baseUrl = $ctx->getScheme() . '://' . $ctx->getHost();
                $shareUrl = $baseUrl . '/api/v1/skills/' . $data['skill_id'] . '/' . rawurlencode($shortcutPublicName);
                unset($data['bridge_shortcut_source']);
                $data['bridge_shortcut_share_url'] = $shareUrl;
                $yamlContent = Yaml::dump($data, 10, 2);
            }

            $iconData = null;
            $iconPath = $skillDir . '/icon.png';
            if (file_exists($iconPath)) {
                $iconData = base64_encode(file_get_contents($iconPath));
            }

            $shortcutData = null;
            if ($shortcutFilePath) {
                $shortcutData = base64_encode(file_get_contents($shortcutFilePath));
            }

            $parsedSkills[] = [
                'yamlContent' => $yamlContent,
                'data' => $data,
                'iconData' => $iconData,
                'shortcutData' => $shortcutData,
            ];
        }

        // 5. All valid — upsert
        $syncedIds = [];
        $upsertedSkills = [];
        foreach ($parsedSkills as $parsed) {
            $skillId = $parsed['data']['skill_id'];
            $skill = $this->skillRepository->findOneBy(['skillId' => $skillId]);

            if (!$skill) {
                $skill = new Skill();
                $skill->setSkillId($skillId);
                $this->em->persist($skill);
            }

            $skill->updateFromYaml($parsed['yamlContent'], $parsed['data']);
            $skill->setIconData($parsed['iconData']);
            $skill->setShortcutData($parsed['shortcutData']);

            $upsertedSkills[] = $skill;
            $syncedIds[] = $skillId;
        }

        // 6. Delete orphans
        foreach ($this->skillRepository->findAll() as $existing) {
            if (!in_array($existing->getSkillId(), $syncedIds, true)) {
                $this->em->remove($existing);
            }
        }

        // 7. Compile (writes compiled_info on each skill entity, creates SkillManifest — not flushed yet)
        $this->compiler->compile($upsertedSkills, $commitSha);

        // 8. Flush everything atomically (skills + manifest in one transaction)
        $this->em->flush();

        // 9. Prune old manifests, keeping only the last 50
        $this->manifestRepository->pruneOldManifests();

        // 10. Log success
        $this->logger?->info('Sync completed', ['skillCount' => count($parsedSkills), 'commitSha' => $commitSha]);

        return $this->logResult('success', count($parsedSkills), $commitSha, $commitUrl, $actionRunUrl);
    }

    private function readAllowedTags(string $repoDir): array
    {
        $tagsFile = $repoDir . '/tags.yaml';
        if (!file_exists($tagsFile)) {
            return [];
        }
        $data = Yaml::parseFile($tagsFile);

        return $data['tags'] ?? [];
    }

    private function logResult(
        string $status,
        int $skillCount,
        string $commitSha,
        string $commitUrl,
        ?string $actionRunUrl,
        ?string $error = null,
    ): SyncLog {
        $log = new SyncLog();
        $log->setStatus($status);
        $log->setSkillCount($skillCount);
        $log->setCommitSha($commitSha);
        $log->setCommitUrl($commitUrl);
        $log->setActionRunUrl($actionRunUrl);
        if ($error) {
            $log->setErrorMessage($error);
        }
        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }
}
