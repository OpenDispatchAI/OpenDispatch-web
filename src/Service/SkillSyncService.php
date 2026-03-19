<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Skill;
use App\Entity\SyncLog;
use App\Repository\SkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Yaml\Yaml;

class SkillSyncService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SkillRepository $skillRepository,
        private SkillYamlValidator $validator,
        private SkillCompiler $compiler,
    ) {}

    /**
     * Sync from a local directory containing the skills repo.
     */
    public function syncFromDirectory(
        string $repoDir,
        string $commitSha,
        string $commitUrl,
        ?string $actionRunUrl,
    ): SyncLog {
        // 1. Read allowed tags
        $allowedTags = $this->readAllowedTags($repoDir);

        // 2. Find all skill YAMLs
        $skillDirs = glob($repoDir . '/skills/*/skill.yaml') ?: [];

        // 3. Validate ALL before making any changes
        $parsedSkills = [];
        foreach ($skillDirs as $yamlPath) {
            $yamlContent = file_get_contents($yamlPath);
            $errors = $this->validator->validate($yamlContent, $allowedTags);

            if (!empty($errors)) {
                return $this->logResult(
                    'failed', 0, $commitSha, $commitUrl, $actionRunUrl,
                    'Validation failed for ' . basename(dirname($yamlPath)) . ': ' . implode('; ', $errors)
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

        // 4. All valid — upsert
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

        // 5. Delete orphans
        foreach ($this->skillRepository->findAll() as $existing) {
            if (!in_array($existing->getSkillId(), $syncedIds, true)) {
                $this->em->remove($existing);
            }
        }

        $this->em->flush();

        // 6. Compile static files
        $this->compiler->compile();

        // 7. Log success
        return $this->logResult('success', count($parsedSkills), $commitSha, $commitUrl, $actionRunUrl);
    }

    /**
     * Full sync: clone/pull repo, then syncFromDirectory.
     */
    public function sync(
        string $repoUrl,
        string $targetDir,
        string $commitSha,
        string $commitUrl,
        ?string $actionRunUrl,
    ): SyncLog {
        if (is_dir($targetDir . '/.git')) {
            $this->exec("git -C " . escapeshellarg($targetDir) . " fetch origin main && git -C " . escapeshellarg($targetDir) . " reset --hard origin/main");
        } else {
            $this->exec("git clone --depth 1 --branch main " . escapeshellarg($repoUrl) . " " . escapeshellarg($targetDir));
        }

        return $this->syncFromDirectory($targetDir, $commitSha, $commitUrl, $actionRunUrl);
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

    private function exec(string $command): void
    {
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException("Command failed ({$exitCode}): " . implode("\n", $output));
        }
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
