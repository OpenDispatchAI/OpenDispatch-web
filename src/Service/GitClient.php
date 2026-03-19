<?php

declare(strict_types=1);

namespace App\Service;

use App\Trait\LoggerTrait;

class GitClient
{
    use LoggerTrait;

    /**
     * Shallow clone a repo to a temporary directory. Returns the path.
     * Caller is responsible for cleanup.
     */
    public function clone(string $repoUrl, string $branch = 'main'): string
    {
        $tmpDir = sys_get_temp_dir() . '/opendispatch-skills-' . bin2hex(random_bytes(8));

        $command = sprintf(
            'git clone --depth 1 --branch %s %s %s',
            escapeshellarg($branch),
            escapeshellarg($repoUrl),
            escapeshellarg($tmpDir),
        );

        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->logger?->error('Git clone failed', ['exitCode' => $exitCode, 'output' => $output]);
            throw new \RuntimeException("Git clone failed ({$exitCode})");
        }

        return $tmpDir;
    }
}
