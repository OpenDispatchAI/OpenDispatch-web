<?php

namespace App\Tests\Service;

use App\Service\SkillYamlValidator;
use PHPUnit\Framework\TestCase;

class SkillYamlValidatorTest extends TestCase
{
    private SkillYamlValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SkillYamlValidator();
    }

    public function testValidSkillReturnsNoErrors(): void
    {
        $yaml = file_get_contents(__DIR__ . '/../fixtures/valid-skill.yaml');
        $errors = $this->validator->validate($yaml);
        $this->assertEmpty($errors);
    }

    public function testInvalidYamlSyntaxReturnsError(): void
    {
        $errors = $this->validator->validate("invalid: yaml: [broken");
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid YAML', $errors[0]);
    }

    public function testMissingSkillIdReturnsError(): void
    {
        $yaml = "name: Test\nversion: 1.0.0\nactions:\n  - id: test.action\n    examples:\n      - example";
        $errors = $this->validator->validate($yaml);
        $this->assertContains('skill_id is required', $errors);
    }

    public function testMissingNameReturnsError(): void
    {
        $yaml = "skill_id: test\nversion: 1.0.0\nactions:\n  - id: test.action\n    examples:\n      - example";
        $errors = $this->validator->validate($yaml);
        $this->assertContains('name is required', $errors);
    }

    public function testInvalidVersionReturnsError(): void
    {
        $yaml = "skill_id: test\nname: Test\nversion: abc\nactions:\n  - id: test.action\n    examples:\n      - example";
        $errors = $this->validator->validate($yaml);
        $this->assertContains('version must be valid semver (e.g. 1.0.0)', $errors);
    }

    public function testMissingActionsReturnsError(): void
    {
        $yaml = file_get_contents(__DIR__ . '/../fixtures/invalid-skill-no-actions.yaml');
        $errors = $this->validator->validate($yaml);
        $this->assertContains('actions must be a non-empty array', $errors);
    }

    public function testActionWithoutIdReturnsError(): void
    {
        $yaml = "skill_id: test\nname: Test\nversion: 1.0.0\nactions:\n  - title: No ID\n    examples:\n      - example";
        $errors = $this->validator->validate($yaml);
        $this->assertNotEmpty(array_filter($errors, fn($e) => str_contains($e, 'id is required')));
    }

    public function testActionIdPatternEnforced(): void
    {
        $yaml = "skill_id: test\nname: Test\nversion: 1.0.0\nactions:\n  - id: invalid\n    examples:\n      - example";
        $errors = $this->validator->validate($yaml);
        $this->assertNotEmpty(array_filter($errors, fn($e) => str_contains($e, 'must match pattern')));
    }

    public function testActionIdWithMultipleSegmentsIsValid(): void
    {
        $yaml = "skill_id: test\nname: Test\nversion: 1.0.0\nactions:\n  - id: vehicle.climate.set_temperature\n    examples:\n      - example";
        $errors = $this->validator->validate($yaml);
        $this->assertEmpty($errors);
    }

    public function testActionWithoutExamplesReturnsError(): void
    {
        $yaml = "skill_id: test\nname: Test\nversion: 1.0.0\nactions:\n  - id: test.action\n    title: Test";
        $errors = $this->validator->validate($yaml);
        $this->assertNotEmpty(array_filter($errors, fn($e) => str_contains($e, 'at least one example')));
    }

    public function testBridgeShortcutWithoutShareUrlReturnsError(): void
    {
        $yaml = "skill_id: test\nname: Test\nversion: 1.0.0\nbridge_shortcut:\n  name: Test Bridge\nactions:\n  - id: test.action\n    examples:\n      - example";
        $errors = $this->validator->validate($yaml);
        $this->assertContains('bridge_shortcut.share_url is required when bridge_shortcut is set', $errors);
    }
}
