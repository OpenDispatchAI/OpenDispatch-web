<?php

namespace App\Tests\Service;

use App\Service\SkillYamlValidator;
use PHPUnit\Framework\TestCase;

class SkillYamlValidatorTest extends TestCase
{
    private SkillYamlValidator $validator;
    private string $validYaml;

    protected function setUp(): void
    {
        $this->validator = new SkillYamlValidator();
        $this->validYaml = file_get_contents(__DIR__ . '/../fixtures/skills-repo/skills/tesla/skill.yaml');
    }

    public function testValidSkillReturnsNoErrors(): void
    {
        $allowedTags = ['automotive', 'smart-home', 'productivity', 'health', 'entertainment', 'communication', 'finance'];
        $errors = $this->validator->validate($this->validYaml, $allowedTags);

        self::assertSame([], $errors);
    }

    public function testInvalidYamlSyntaxReturnsError(): void
    {
        $brokenYaml = "skill_id: tesla\n  invalid: indentation\n: bad";
        $errors = $this->validator->validate($brokenYaml);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('Invalid YAML syntax', $errors[0]);
    }

    public function testMissingSkillIdReturnsError(): void
    {
        $yaml = <<<YAML
name: Tesla
version: 1.0.0
actions:
  - id: vehicle.unlock
    examples:
      - "unlock my car"
YAML;

        $errors = $this->validator->validate($yaml);

        self::assertContains('skill_id is required', $errors);
    }

    public function testMissingNameReturnsError(): void
    {
        $yaml = <<<YAML
skill_id: tesla
version: 1.0.0
actions:
  - id: vehicle.unlock
    examples:
      - "unlock my car"
YAML;

        $errors = $this->validator->validate($yaml);

        self::assertContains('name is required', $errors);
    }

    public function testInvalidVersionReturnsError(): void
    {
        $yaml = <<<YAML
skill_id: tesla
name: Tesla
version: latest
actions:
  - id: vehicle.unlock
    examples:
      - "unlock my car"
YAML;

        $errors = $this->validator->validate($yaml);

        self::assertContains('version must be valid semver (e.g. 1.0.0)', $errors);
    }

    public function testMissingActionsReturnsError(): void
    {
        $yaml = <<<YAML
skill_id: tesla
name: Tesla
version: 1.0.0
YAML;

        $errors = $this->validator->validate($yaml);

        self::assertContains('actions must be a non-empty array', $errors);
    }

    public function testActionWithoutIdReturnsError(): void
    {
        $yaml = <<<YAML
skill_id: tesla
name: Tesla
version: 1.0.0
actions:
  - title: Unlock
    examples:
      - "unlock my car"
YAML;

        $errors = $this->validator->validate($yaml);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('id is required', $errors[0]);
    }

    public function testActionIdPatternEnforced(): void
    {
        $yaml = <<<YAML
skill_id: tesla
name: Tesla
version: 1.0.0
actions:
  - id: unlock
    examples:
      - "unlock my car"
YAML;

        $errors = $this->validator->validate($yaml);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('id must match pattern', $errors[0]);
    }

    public function testActionIdWithMultipleSegmentsIsValid(): void
    {
        $yaml = <<<YAML
skill_id: tesla
name: Tesla
version: 1.0.0
actions:
  - id: vehicle.climate.set_temperature
    examples:
      - "set my car to 72 degrees"
YAML;

        $errors = $this->validator->validate($yaml);

        // Should have no errors related to action id
        self::assertEmpty(
            array_filter($errors, fn (string $e) => str_contains($e, 'id must match pattern')),
            'Multi-segment action id should not trigger pattern error',
        );
    }

    public function testActionWithoutExamplesReturnsError(): void
    {
        $yaml = <<<YAML
skill_id: tesla
name: Tesla
version: 1.0.0
actions:
  - id: vehicle.unlock
    title: Unlock
YAML;

        $errors = $this->validator->validate($yaml);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('must have at least one example', $errors[0]);
    }

    public function testBridgeShortcutWithoutShareUrlReturnsError(): void
    {
        $yaml = <<<YAML
skill_id: tesla
name: Tesla
version: 1.0.0
bridge_shortcut:
  name: "OpenDispatch - Tesla V1"
actions:
  - id: vehicle.unlock
    examples:
      - "unlock my car"
YAML;

        $errors = $this->validator->validate($yaml);

        self::assertContains('bridge_shortcut.share_url is required when bridge_shortcut is set', $errors);
    }

    public function testDisallowedTagReturnsError(): void
    {
        $yaml = <<<YAML
skill_id: tesla
name: Tesla
version: 1.0.0
tags:
  - automotive
  - nonexistent-tag
actions:
  - id: vehicle.unlock
    examples:
      - "unlock my car"
YAML;

        $allowedTags = ['automotive', 'smart-home'];
        $errors = $this->validator->validate($yaml, $allowedTags);

        self::assertContains("Tag 'nonexistent-tag' is not in the allowed tags list", $errors);
    }

    public function testAllowedTagsPassValidation(): void
    {
        $yaml = <<<YAML
skill_id: tesla
name: Tesla
version: 1.0.0
tags:
  - automotive
  - smart-home
actions:
  - id: vehicle.unlock
    examples:
      - "unlock my car"
YAML;

        $allowedTags = ['automotive', 'smart-home', 'productivity'];
        $errors = $this->validator->validate($yaml, $allowedTags);

        // No tag-related errors
        self::assertEmpty(
            array_filter($errors, fn (string $e) => str_contains($e, 'not in the allowed tags list')),
            'Allowed tags should not trigger tag validation errors',
        );
    }

    public function testEmptyAllowedTagsSkipsTagValidation(): void
    {
        $yaml = <<<YAML
skill_id: tesla
name: Tesla
version: 1.0.0
tags:
  - some-random-tag
actions:
  - id: vehicle.unlock
    examples:
      - "unlock my car"
YAML;

        // Empty allowedTags means no tag validation
        $errors = $this->validator->validate($yaml, []);

        self::assertEmpty(
            array_filter($errors, fn (string $e) => str_contains($e, 'not in the allowed tags list')),
            'Empty allowedTags should skip tag validation entirely',
        );
    }
}
