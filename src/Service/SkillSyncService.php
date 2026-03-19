<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Skill;
use App\Entity\SyncLog;
use App\Repository\SkillRepository;
use App\Trait\LoggerTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
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
        private readonly string $skillsRepoUrl,
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
            $iconPath = dirname($yamlPath) . '/icon.png';

            $parsedSkills[] = [
                'yamlContent' => $yamlContent,
                'data' => $data,
                'iconPath' => file_exists($iconPath) ? $iconPath : null,
            ];
        }

        // 5. All valid — upsert
        $syncedIds = [];
        foreach ($parsedSkills as $parsed) {
            $skillId = $parsed['data']['skill_id'];
            $skill = $this->skillRepository->findOneBy(['skillId' => $skillId]);

            if (!$skill) {
                $skill = new Skill();
                $skill->setSkillId($skillId);
                $this->em->persist($skill);
            }

            $skill->updateFromYaml($parsed['yamlContent'], $parsed['data']);

            if ($parsed['iconPath']) {
                $skill->setIconPath($parsed['iconPath']);
            }

            $syncedIds[] = $skillId;
        }

        // 6. Delete orphans
        foreach ($this->skillRepository->findAll() as $existing) {
            if (!in_array($existing->getSkillId(), $syncedIds, true)) {
                $this->em->remove($existing);
            }
        }

        $this->em->flush();

        // 7. Compile static files
        $this->compiler->compile();

        // 8. Log success
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
