<?php

namespace App\Service;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class SkillYamlValidator
{
    /**
     * @return string[] Array of error messages, empty if valid
     */
    public function validate(string $yamlContent): array
    {
        try {
            $data = Yaml::parse($yamlContent);
        } catch (ParseException $e) {
            return ['Invalid YAML syntax: ' . $e->getMessage()];
        }

        if (!is_array($data)) {
            return ['YAML must be a mapping'];
        }

        $errors = [];

        if (empty($data['skill_id'])) {
            $errors[] = 'skill_id is required';
        }

        if (empty($data['name'])) {
            $errors[] = 'name is required';
        }

        if (empty($data['version'])) {
            $errors[] = 'version is required';
        } elseif (!preg_match('/^\d+\.\d+\.\d+$/', $data['version'])) {
            $errors[] = 'version must be valid semver (e.g. 1.0.0)';
        }

        if (empty($data['actions']) || !is_array($data['actions'])) {
            $errors[] = 'actions must be a non-empty array';
        } else {
            foreach ($data['actions'] as $i => $action) {
                $actionLabel = !empty($action['id']) ? $action['id'] : "index {$i}";

                if (empty($action['id'])) {
                    $errors[] = "Action {$actionLabel}: id is required";
                } elseif (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $action['id'])) {
                    $errors[] = "Action {$actionLabel}: id must match pattern word.word (e.g. vehicle.unlock)";
                }

                if (empty($action['examples']) || !is_array($action['examples'])) {
                    $errors[] = "Action {$actionLabel}: must have at least one example";
                }
            }
        }

        if (!empty($data['bridge_shortcut']) && empty($data['bridge_shortcut']['share_url'])) {
            $errors[] = 'bridge_shortcut.share_url is required when bridge_shortcut is set';
        }

        return $errors;
    }

    /**
     * Extract the skill_id from YAML content. Call only after validation passes.
     */
    public function extractSkillId(string $yamlContent): string
    {
        $data = Yaml::parse($yamlContent);
        return $data['skill_id'];
    }
}
