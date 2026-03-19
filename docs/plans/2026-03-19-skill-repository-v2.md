# Skill Repository V2 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:executing-plans to implement this plan task-by-task.

**Goal:** Build a full-stack Symfony 8.0 app — public skill browser, JSON API for iOS, webhook sync from GitHub, and admin dashboard.

**Architecture:** GitHub skills repo is source of truth. Webhook triggers sync → validate → upsert to PostgreSQL → compile static files. Symfony serves the public website (Twig + Symfony UX), JSON API (static files + dynamic download endpoint), and admin dashboard.

**Tech Stack:** Symfony 8.0, PHP 8.5, PostgreSQL 17, Twig + Symfony UX (Turbo, Live Components, Stimulus, Twig Components), AssetMapper, Docker (Nginx + PHP-FPM), league/commonmark (docs)

**Design doc:** `docs/plans/2026-03-19-skill-repository-v2-design.md`

**Skills repo structure** (the separate GitHub repo that holds skills):
```
skills-repo/
├── tags.yaml              # allowed tags list
└── skills/
    └── {skill_id}/
        ├── skill.yaml     # the skill definition
        └── icon.png       # optional icon
```

---

### Task 0: Clean Slate + Scaffold + Docker

**Files:**
- Delete: `backend/`, `frontend/`
- Create: Symfony project at repo root via `symfony new`
- Reuse: `docker/php/Dockerfile`, `docker/nginx/default.conf`
- Modify: `docker-compose.yml` (change volume mounts from `./backend` to `./`)
- Modify: `Makefile` (add `sync` target)

**Step 1: Clean up old V1 code**

Preserve `docs/`, `docker/`, `.claude/`, `.git/`. Delete everything else from V1:
```bash
rm -rf backend/ frontend/ Makefile docker-compose.yml
```

**Step 2: Create Symfony project at root**

```bash
symfony new tmp_app --version=8.0 --webapp --no-git
# Move everything from tmp_app to repo root
shopt -s dotglob && mv tmp_app/* . && rmdir tmp_app
```

**Step 3: Install additional packages**

```bash
# Symfony UX extras
composer require symfony/uid symfony/ux-twig-component symfony/ux-live-component

# Markdown
composer require league/commonmark

# Dev
composer require --dev doctrine/doctrine-fixtures-bundle
```

**Step 4: Configure .env**

Set DATABASE_URL:
```
DATABASE_URL="postgresql://opendispatch:opendispatch@postgres:5432/opendispatch?serverVersion=17&charset=utf8"
```

Add webhook secret and skills repo URL:
```
WEBHOOK_SECRET=changeme
SKILLS_REPO_URL=https://github.com/your-org/opendispatch-skills.git
```

**Step 5: Update .gitignore**

Append:
```
/public/api/
/var/skills-repo/
```

**Step 6: Adapt docker-compose.yml**

Copy from V1 but change volume mounts from `./backend` to `./`:
```yaml
services:
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    volumes:
      - ./:/var/www/html
    depends_on:
      postgres:
        condition: service_healthy
    environment:
      DATABASE_URL: "postgresql://opendispatch:opendispatch@postgres:5432/opendispatch?serverVersion=17&charset=utf8"

  nginx:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - ./public:/var/www/html/public:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - php

  postgres:
    image: postgres:17-alpine
    environment:
      POSTGRES_DB: opendispatch
      POSTGRES_USER: opendispatch
      POSTGRES_PASSWORD: opendispatch
    volumes:
      - postgres_data:/var/lib/postgresql/data
    ports:
      - "5432:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U opendispatch"]
      interval: 5s
      timeout: 5s
      retries: 5

volumes:
  postgres_data:
```

**Step 7: Update Makefile**

```makefile
.PHONY: up down build shell migrate test compile sync console

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

shell:
	docker compose exec php bash

migrate:
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

test:
	docker compose exec php bin/phpunit

compile:
	docker compose exec php bin/console app:compile

sync:
	docker compose exec php bin/console app:sync

console:
	docker compose exec php bin/console $(cmd)
```

**Step 8: Build, start, verify**

```bash
make build && make up
docker compose exec php composer install
curl http://localhost:8080
```

Expected: Symfony welcome page or 404 (no routes yet). Confirms stack works.

**Step 9: Commit**

```bash
git add -A
git commit -m "feat: clean slate — Symfony 8.0 at repo root with Docker"
```

---

### Task 1: Entities & Migrations

**Files:**
- Create: `src/Entity/Skill.php`
- Create: `src/Entity/SkillDownload.php`
- Create: `src/Entity/SyncLog.php`
- Create: `src/Entity/AdminUser.php`
- Create: `src/Repository/SkillRepository.php`
- Create: `src/Repository/SkillDownloadRepository.php`
- Create: `src/Repository/SyncLogRepository.php`
- Create: `src/Repository/AdminUserRepository.php`

**Step 1: Create Skill entity**

```php
<?php

namespace App\Entity;

use App\Repository\SkillRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SkillRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Skill
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 255, unique: true)]
    private string $skillId;

    #[ORM\Column(type: Types::TEXT)]
    private string $yamlContent;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 50)]
    private string $version;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 255)]
    private string $author;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authorUrl = null;

    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(type: Types::JSON)]
    private array $languages = [];

    #[ORM\Column]
    private bool $requiresBridgeShortcut = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bridgeShortcutName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bridgeShortcutShareUrl = null;

    #[ORM\Column]
    private int $actionCount = 0;

    #[ORM\Column]
    private int $exampleCount = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iconPath = null;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $syncedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, SkillDownload> */
    #[ORM\OneToMany(targetEntity: SkillDownload::class, mappedBy: 'skill', cascade: ['remove'])]
    private Collection $downloads;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->syncedAt = new \DateTimeImmutable();
        $this->downloads = new ArrayCollection();
    }

    // Getters and setters for all fields.
    // Key methods:

    public function getId(): Uuid { return $this->id; }
    public function getSkillId(): string { return $this->skillId; }
    public function setSkillId(string $v): static { $this->skillId = $v; return $this; }
    public function getYamlContent(): string { return $this->yamlContent; }
    public function setYamlContent(string $v): static { $this->yamlContent = $v; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getVersion(): string { return $this->version; }
    public function setVersion(string $v): static { $this->version = $v; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $v): static { $this->description = $v; return $this; }
    public function getAuthor(): string { return $this->author; }
    public function setAuthor(string $v): static { $this->author = $v; return $this; }
    public function getAuthorUrl(): ?string { return $this->authorUrl; }
    public function setAuthorUrl(?string $v): static { $this->authorUrl = $v; return $this; }
    public function getTags(): array { return $this->tags; }
    public function setTags(array $v): static { $this->tags = $v; return $this; }
    public function getLanguages(): array { return $this->languages; }
    public function setLanguages(array $v): static { $this->languages = $v; return $this; }
    public function getRequiresBridgeShortcut(): bool { return $this->requiresBridgeShortcut; }
    public function setRequiresBridgeShortcut(bool $v): static { $this->requiresBridgeShortcut = $v; return $this; }
    public function getBridgeShortcutName(): ?string { return $this->bridgeShortcutName; }
    public function setBridgeShortcutName(?string $v): static { $this->bridgeShortcutName = $v; return $this; }
    public function getBridgeShortcutShareUrl(): ?string { return $this->bridgeShortcutShareUrl; }
    public function setBridgeShortcutShareUrl(?string $v): static { $this->bridgeShortcutShareUrl = $v; return $this; }
    public function getActionCount(): int { return $this->actionCount; }
    public function setActionCount(int $v): static { $this->actionCount = $v; return $this; }
    public function getExampleCount(): int { return $this->exampleCount; }
    public function setExampleCount(int $v): static { $this->exampleCount = $v; return $this; }
    public function getIconPath(): ?string { return $this->iconPath; }
    public function setIconPath(?string $v): static { $this->iconPath = $v; return $this; }
    public function isFeatured(): bool { return $this->isFeatured; }
    public function setIsFeatured(bool $v): static { $this->isFeatured = $v; return $this; }
    public function getSyncedAt(): \DateTimeImmutable { return $this->syncedAt; }
    public function setSyncedAt(\DateTimeImmutable $v): static { $this->syncedAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getDownloads(): Collection { return $this->downloads; }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /**
     * Populate all YAML-derived fields from parsed data.
     * Call this during sync. Does NOT touch isFeatured.
     */
    public function updateFromYaml(string $yamlContent, array $data): void
    {
        $this->yamlContent = $yamlContent;
        $this->name = $data['name'];
        $this->version = $data['version'];
        $this->description = $data['description'] ?? '';
        $this->author = $data['author'] ?? '';
        $this->authorUrl = $data['author_url'] ?? null;
        $this->tags = $data['tags'] ?? [];
        $this->languages = $data['languages'] ?? [];
        $this->requiresBridgeShortcut = isset($data['bridge_shortcut']);
        $this->bridgeShortcutName = $data['bridge_shortcut']['name'] ?? null;
        $this->bridgeShortcutShareUrl = $data['bridge_shortcut']['share_url'] ?? null;

        $actions = $data['actions'] ?? [];
        $this->actionCount = count($actions);
        $this->exampleCount = array_sum(array_map(fn($a) => count($a['examples'] ?? []), $actions));

        $this->syncedAt = new \DateTimeImmutable();
    }
}
```

**Step 2: Create SkillDownload entity**

```php
<?php

namespace App\Entity;

use App\Repository\SkillDownloadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SkillDownloadRepository::class)]
#[ORM\Index(columns: ['downloaded_at'], name: 'idx_download_date')]
class SkillDownload
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Skill::class, inversedBy: 'downloads')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Skill $skill;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $downloadedAt;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $appVersion = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->downloadedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getSkill(): Skill { return $this->skill; }
    public function setSkill(Skill $v): static { $this->skill = $v; return $this; }
    public function getDownloadedAt(): \DateTimeImmutable { return $this->downloadedAt; }
    public function getAppVersion(): ?string { return $this->appVersion; }
    public function setAppVersion(?string $v): static { $this->appVersion = $v; return $this; }
}
```

**Step 3: Create SyncLog entity**

```php
<?php

namespace App\Entity;

use App\Repository\SyncLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SyncLogRepository::class)]
class SyncLog
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column]
    private int $skillCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(length: 40)]
    private string $commitSha;

    #[ORM\Column(length: 255)]
    private string $commitUrl;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionRunUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $syncedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->syncedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getSkillCount(): int { return $this->skillCount; }
    public function setSkillCount(int $v): static { $this->skillCount = $v; return $this; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $v): static { $this->errorMessage = $v; return $this; }
    public function getCommitSha(): string { return $this->commitSha; }
    public function setCommitSha(string $v): static { $this->commitSha = $v; return $this; }
    public function getCommitUrl(): string { return $this->commitUrl; }
    public function setCommitUrl(string $v): static { $this->commitUrl = $v; return $this; }
    public function getActionRunUrl(): ?string { return $this->actionRunUrl; }
    public function setActionRunUrl(?string $v): static { $this->actionRunUrl = $v; return $this; }
    public function getSyncedAt(): \DateTimeImmutable { return $this->syncedAt; }
}
```

**Step 4: Create AdminUser entity** (same as V1)

Same as V1: UUID, email, password, roles. Implements UserInterface + PasswordAuthenticatedUserInterface.

**Step 5: Create all repositories**

- `SkillRepository` with `findPublished()` (all skills — there's no unpublished concept in V2, but keep for compiler), `search(string $query, string $tag, string $sort)`, `findAllTags()`.
- `SkillDownloadRepository` with `countBySkill(Skill $skill)` and `getTotalCount()`.
- `SyncLogRepository` with `findLatest(int $limit)`.
- `AdminUserRepository` (basic).

Key method — `SkillRepository::search()`:
```php
public function search(string $query = '', string $tag = '', string $sort = 'name'): array
{
    $qb = $this->createQueryBuilder('s');

    if ($query !== '') {
        $qb->andWhere('LOWER(s.name) LIKE LOWER(:query) OR LOWER(s.description) LIKE LOWER(:query)')
           ->setParameter('query', '%' . $query . '%');
    }

    if ($tag !== '') {
        $qb->andWhere('s.tags LIKE :tag')
           ->setParameter('tag', '%"' . $tag . '"%');
    }

    match ($sort) {
        'popular' => $qb
            ->leftJoin('s.downloads', 'd')
            ->groupBy('s.id')
            ->orderBy('COUNT(d.id)', 'DESC'),
        'newest' => $qb->orderBy('s.createdAt', 'DESC'),
        default => $qb->orderBy('s.name', 'ASC'),
    };

    return $qb->getQuery()->getResult();
}

public function findAllTags(): array
{
    $skills = $this->findAll();
    $tags = [];
    foreach ($skills as $skill) {
        foreach ($skill->getTags() as $tag) {
            $tags[$tag] = ($tags[$tag] ?? 0) + 1;
        }
    }
    arsort($tags);
    return array_keys($tags);
}
```

**Step 6: Generate and run migration**

```bash
docker compose exec php bin/console doctrine:migrations:diff
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

**Step 7: Commit**

```bash
git add src/Entity/ src/Repository/ migrations/
git commit -m "feat: add Skill, SkillDownload, SyncLog, AdminUser entities"
```

---

### Task 2: Authentication

Same as V1. See V1 plan Task 3 for full code.

**Files:**
- Modify: `config/packages/security.yaml`
- Create: `src/Controller/Admin/LoginController.php`
- Create: `templates/admin/login.html.twig`
- Create: `src/Command/CreateAdminCommand.php`

Security config: entity provider on AdminUser, form login at `/admin/login`, logout at `/admin/logout`, access control requires ROLE_ADMIN for `/admin/*`.

**Commit message:** `feat: add admin authentication with form login`

---

### Task 3: YAML Validator (TDD)

**Files:**
- Create: `tests/fixtures/skills-repo/tags.yaml`
- Create: `tests/fixtures/skills-repo/skills/tesla/skill.yaml`
- Create: `tests/fixtures/skills-repo/skills/broken/skill.yaml`
- Create: `tests/Service/SkillYamlValidatorTest.php`
- Create: `src/Service/SkillYamlValidator.php`

The V2 validator adds **tag allowlist validation** compared to V1.

**Step 1: Create test fixtures**

Create `tests/fixtures/skills-repo/tags.yaml`:
```yaml
tags:
  - automotive
  - smart-home
  - productivity
  - health
  - entertainment
  - communication
  - finance
```

Create `tests/fixtures/skills-repo/skills/tesla/skill.yaml`:
```yaml
skill_id: tesla
name: Tesla
version: 1.0.0
description: "Control your Tesla"
author: opendispatch
author_url: https://opendispatch.ai
tags:
  - automotive
  - smart-home
languages:
  - en
bridge_shortcut:
  name: "OpenDispatch - Tesla V1"
  share_url: "https://www.icloud.com/shortcuts/abc123"
actions:
  - id: vehicle.unlock
    title: Unlock
    description: "Unlock the car doors"
    examples:
      - "unlock my car"
      - "unlock the tesla"
  - id: vehicle.climate.set_temperature
    title: Set Temperature
    description: "Set the cabin temperature"
    parameters:
      - name: temperature
        type: number
    examples:
      - "set my car to 72 degrees"
```

Create `tests/fixtures/skills-repo/skills/broken/skill.yaml`:
```yaml
skill_id: broken
name: Broken Skill
version: 1.0.0
```

**Step 2: Write tests**

Same as V1 tests plus these additional tests:
```php
public function testDisallowedTagReturnsError(): void
{
    $yaml = "skill_id: test\nname: Test\nversion: 1.0.0\ntags:\n  - nonexistent\nactions:\n  - id: test.action\n    examples:\n      - example";
    $errors = $this->validator->validate($yaml, ['automotive', 'smart-home']);
    $this->assertNotEmpty(array_filter($errors, fn($e) => str_contains($e, 'not in the allowed tags')));
}

public function testAllowedTagsPassValidation(): void
{
    $yaml = "skill_id: test\nname: Test\nversion: 1.0.0\ntags:\n  - automotive\nactions:\n  - id: test.action\n    examples:\n      - example";
    $errors = $this->validator->validate($yaml, ['automotive', 'smart-home']);
    $this->assertEmpty($errors);
}

public function testEmptyAllowedTagsSkipsTagValidation(): void
{
    $yaml = "skill_id: test\nname: Test\nversion: 1.0.0\ntags:\n  - anything\nactions:\n  - id: test.action\n    examples:\n      - example";
    $errors = $this->validator->validate($yaml);
    $this->assertEmpty($errors);
}
```

**Step 3: Implement validator**

Same as V1 but `validate()` accepts optional `array $allowedTags = []`:
```php
public function validate(string $yamlContent, array $allowedTags = []): array
{
    // ... all V1 validation ...

    // Tag allowlist
    if (!empty($allowedTags) && !empty($data['tags'])) {
        foreach ($data['tags'] as $tag) {
            if (!in_array($tag, $allowedTags, true)) {
                $errors[] = "Tag '{$tag}' is not in the allowed tags list";
            }
        }
    }

    return $errors;
}
```

Also add `extractSkillData()` that returns the full parsed array (used by sync service):
```php
public function extractSkillData(string $yamlContent): array
{
    return Yaml::parse($yamlContent);
}
```

**Step 4: Run tests, verify pass**

**Step 5: Commit**

---

### Task 4: Sync Service (TDD)

**Files:**
- Create: `tests/Service/SkillSyncServiceTest.php`
- Create: `src/Service/SkillSyncService.php`

**Step 1: Write tests**

```php
<?php

namespace App\Tests\Service;

use App\Entity\Skill;
use App\Entity\SyncLog;
use App\Repository\SkillRepository;
use App\Service\SkillCompiler;
use App\Service\SkillSyncService;
use App\Service\SkillYamlValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SkillSyncServiceTest extends TestCase
{
    private string $fixtureRepoPath;

    protected function setUp(): void
    {
        $this->fixtureRepoPath = __DIR__ . '/../fixtures/skills-repo';
    }

    public function testSyncCreatesNewSkills(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $skillRepo = $this->createMock(SkillRepository::class);
        $compiler = $this->createMock(SkillCompiler::class);
        $validator = new SkillYamlValidator();

        $skillRepo->method('findOneBy')->willReturn(null);
        $skillRepo->method('findAll')->willReturn([]);

        $persisted = [];
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $service = new SkillSyncService($em, $skillRepo, $validator, $compiler);
        $result = $service->syncFromDirectory($this->fixtureRepoPath, 'abc123', 'https://github.com/commit/abc123', null);

        $this->assertSame('success', $result->getStatus());
        // Should have persisted: 1 valid skill (tesla) + 1 SyncLog. Broken skill dir has invalid YAML.
        // Actually broken skill will cause abort. Let me reconsider fixtures...
    }

    public function testSyncAbortsOnInvalidYaml(): void
    {
        // Use fixture repo that includes broken skill
        $em = $this->createMock(EntityManagerInterface::class);
        $skillRepo = $this->createMock(SkillRepository::class);
        $compiler = $this->createMock(SkillCompiler::class);
        $validator = new SkillYamlValidator();

        $service = new SkillSyncService($em, $skillRepo, $validator, $compiler);
        $result = $service->syncFromDirectory($this->fixtureRepoPath, 'abc123', 'https://github.com/commit/abc123', null);

        $this->assertSame('failed', $result->getStatus());
        $this->assertNotNull($result->getErrorMessage());
    }

    public function testSyncUpsertsExistingSkill(): void
    {
        // Create a valid-only fixture dir for this test
        // Test that existing skills get updated, isFeatured preserved
    }

    public function testSyncDeletesOrphanedSkills(): void
    {
        // Test that skills in DB but not in repo get deleted
    }
}
```

Note: Tests need two fixture repos — one with only valid skills, one with a broken skill. Create:
- `tests/fixtures/skills-repo-valid/` — only valid skills + tags.yaml
- `tests/fixtures/skills-repo-invalid/` — includes a broken skill

**Step 2: Implement SkillSyncService**

```php
<?php

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

    public function syncFromDirectory(
        string $repoDir,
        string $commitSha,
        string $commitUrl,
        ?string $actionRunUrl,
    ): SyncLog {
        // 1. Read allowed tags
        $tagsFile = $repoDir . '/tags.yaml';
        $allowedTags = [];
        if (file_exists($tagsFile)) {
            $tagsData = Yaml::parseFile($tagsFile);
            $allowedTags = $tagsData['tags'] ?? [];
        }

        // 2. Find all skill YAMLs
        $skillDirs = glob($repoDir . '/skills/*/skill.yaml');
        if ($skillDirs === false) {
            $skillDirs = [];
        }

        // 3. Validate ALL before making any changes
        $parsedSkills = [];
        foreach ($skillDirs as $yamlPath) {
            $yamlContent = file_get_contents($yamlPath);
            $errors = $this->validator->validate($yamlContent, $allowedTags);

            if (!empty($errors)) {
                return $this->logFailure(
                    $commitSha, $commitUrl, $actionRunUrl,
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
        $allSkills = $this->skillRepository->findAll();
        foreach ($allSkills as $existing) {
            if (!in_array($existing->getSkillId(), $syncedIds, true)) {
                $this->em->remove($existing);
            }
        }

        $this->em->flush();

        // 6. Compile static files
        $this->compiler->compile();

        // 7. Log success
        return $this->logSuccess($commitSha, $commitUrl, $actionRunUrl, count($parsedSkills));
    }

    /**
     * Full sync: clone repo, then call syncFromDirectory.
     */
    public function sync(
        string $repoUrl,
        string $targetDir,
        string $commitSha,
        string $commitUrl,
        ?string $actionRunUrl,
    ): SyncLog {
        // Clone or pull
        if (is_dir($targetDir . '/.git')) {
            $this->exec("git -C {$targetDir} fetch origin main && git -C {$targetDir} reset --hard origin/main");
        } else {
            $this->exec("git clone --depth 1 --branch main {$repoUrl} {$targetDir}");
        }

        return $this->syncFromDirectory($targetDir, $commitSha, $commitUrl, $actionRunUrl);
    }

    private function exec(string $command): void
    {
        $result = shell_exec($command . ' 2>&1');
        if ($result === null) {
            throw new \RuntimeException("Command failed: {$command}");
        }
    }

    private function logSuccess(string $sha, string $url, ?string $runUrl, int $count): SyncLog
    {
        $log = new SyncLog();
        $log->setStatus('success');
        $log->setSkillCount($count);
        $log->setCommitSha($sha);
        $log->setCommitUrl($url);
        $log->setActionRunUrl($runUrl);
        $this->em->persist($log);
        $this->em->flush();
        return $log;
    }

    private function logFailure(string $sha, string $url, ?string $runUrl, string $error): SyncLog
    {
        $log = new SyncLog();
        $log->setStatus('failed');
        $log->setSkillCount(0);
        $log->setErrorMessage($error);
        $log->setCommitSha($sha);
        $log->setCommitUrl($url);
        $log->setActionRunUrl($runUrl);
        $this->em->persist($log);
        $this->em->flush();
        return $log;
    }
}
```

**Step 3: Run tests, verify pass**

**Step 4: Commit**

---

### Task 5: Compiler Service (TDD)

**Files:**
- Create: `tests/Service/SkillCompilerTest.php`
- Create: `src/Service/SkillCompiler.php`

Adapted from V1. Key difference: reads from entity fields instead of parsing YAML on every compile. The `buildIndexEntry()` method uses `$skill->getName()`, `$skill->getActionCount()` etc.

Same tests as V1: index.json, skill.yaml, info.json, cleanup, empty list.

For `info.json`, the action list still needs to be parsed from YAML since individual action details (title, description, parameters, examples) aren't stored as entity fields.

Wire `$publicDir` in `config/services.yaml`:
```yaml
App\Service\SkillCompiler:
    arguments:
        $publicDir: '%kernel.project_dir%/public'
```

**Commit message:** `feat: add skill compiler for static file generation`

---

### Task 6: Webhook + Sync Command

**Files:**
- Create: `src/Controller/Api/WebhookController.php`
- Create: `src/Command/SyncCommand.php`
- Create: `tests/Controller/Api/WebhookControllerTest.php`

**Step 1: Create WebhookController**

```php
<?php

namespace App\Controller\Api;

use App\Service\SkillSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(
        private SkillSyncService $syncService,
        private string $webhookSecret,
        private string $skillsRepoUrl,
        private string $skillsRepoDir,
    ) {}

    #[Route('/api/webhook/sync', name: 'api_webhook_sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-Webhook-Secret');
        if (!hash_equals($this->webhookSecret, $token ?? '')) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $commitSha = $payload['commit_sha'] ?? 'unknown';
        $commitUrl = $payload['commit_url'] ?? '';
        $actionRunUrl = $payload['action_run_url'] ?? null;

        $syncLog = $this->syncService->sync(
            $this->skillsRepoUrl,
            $this->skillsRepoDir,
            $commitSha,
            $commitUrl,
            $actionRunUrl,
        );

        $status = $syncLog->getStatus() === 'success' ? 200 : 422;

        return new JsonResponse([
            'status' => $syncLog->getStatus(),
            'skill_count' => $syncLog->getSkillCount(),
            'error' => $syncLog->getErrorMessage(),
        ], $status);
    }
}
```

Wire parameters in `config/services.yaml`:
```yaml
App\Controller\Api\WebhookController:
    arguments:
        $webhookSecret: '%env(WEBHOOK_SECRET)%'
        $skillsRepoUrl: '%env(SKILLS_REPO_URL)%'
        $skillsRepoDir: '%kernel.project_dir%/var/skills-repo'
```

**Step 2: Create SyncCommand**

```php
<?php

namespace App\Command;

use App\Service\SkillSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync', description: 'Sync skills from the GitHub repository')]
class SyncCommand extends Command
{
    public function __construct(
        private SkillSyncService $syncService,
        private string $skillsRepoUrl,
        private string $skillsRepoDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dir', 'd', InputOption::VALUE_REQUIRED, 'Use a local directory instead of cloning');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $localDir = $input->getOption('dir');

        if ($localDir) {
            $result = $this->syncService->syncFromDirectory($localDir, 'local', '', null);
        } else {
            $result = $this->syncService->sync($this->skillsRepoUrl, $this->skillsRepoDir, 'manual', '', null);
        }

        if ($result->getStatus() === 'success') {
            $io->success("Synced {$result->getSkillCount()} skills.");
            return Command::SUCCESS;
        }

        $io->error($result->getErrorMessage());
        return Command::FAILURE;
    }
}
```

Wire `SyncCommand` parameters in services.yaml too.

**Step 3: Write functional tests for webhook**

Test auth rejection (wrong secret), successful sync (use fixture dir via `--dir`), and failure on invalid YAML.

**Step 4: Commit**

---

### Task 7: Download API

**Files:**
- Create: `src/Controller/Api/DownloadController.php`
- Create: `tests/Controller/Api/DownloadControllerTest.php`

**Step 1: Create DownloadController**

```php
<?php

namespace App\Controller\Api;

use App\Entity\SkillDownload;
use App\Repository\SkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DownloadController extends AbstractController
{
    #[Route('/api/v1/skills/{skillId}/download', name: 'api_skill_download')]
    public function download(
        string $skillId,
        Request $request,
        SkillRepository $skillRepository,
        EntityManagerInterface $em,
    ): Response {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill) {
            throw $this->createNotFoundException();
        }

        $download = new SkillDownload();
        $download->setSkill($skill);
        $download->setAppVersion($request->headers->get('X-OpenDispatch-Version'));
        $em->persist($download);
        $em->flush();

        return new Response($skill->getYamlContent(), 200, [
            'Content-Type' => 'text/yaml',
            'Content-Disposition' => 'attachment; filename="skill.yaml"',
        ]);
    }
}
```

**Step 2: Write tests**

Test: returns YAML, creates SkillDownload row, captures app version header, 404 for unknown skill.

**Step 3: Commit**

---

### Task 8: Admin Dashboard

**Files:**
- Create: `src/Controller/Admin/DashboardController.php`
- Create: `src/Controller/Admin/AdminSkillController.php`
- Create: `src/Controller/Admin/SyncLogController.php`
- Create: `src/Twig/Components/FeaturedToggle.php`
- Create: `templates/admin/dashboard.html.twig`
- Create: `templates/admin/skill/list.html.twig`
- Create: `templates/admin/sync_log/list.html.twig`
- Create: `templates/components/FeaturedToggle.html.twig`

**DashboardController** (`/admin`): Shows skill count, total downloads (from SkillDownloadRepository), last sync (from SyncLogRepository), manual resync button (POST to `/admin/resync` which calls SyncService).

**AdminSkillController** (`/admin/skills`): Table with name, tags, download count, featured toggle. The featured toggle is a Live Component that toggles `isFeatured` on click.

**FeaturedToggle** Live Component:
```php
<?php

namespace App\Twig\Components;

use App\Entity\Skill;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class FeaturedToggle
{
    use DefaultActionTrait;

    #[LiveProp]
    public Skill $skill;

    public function __construct(private EntityManagerInterface $em) {}

    #[LiveAction]
    public function toggle(): void
    {
        $this->skill->setIsFeatured(!$this->skill->isFeatured());
        $this->em->flush();
    }
}
```

**SyncLogController** (`/admin/sync-log`): Table with timestamp, status, skill count, error (truncated), commit SHA (linked to commitUrl), action run link.

**Step: Write functional tests for admin pages**

**Commit message:** `feat: add admin dashboard with stats, skill list, and sync log`

---

### Task 9: Skill Browser (Live Component)

**Files:**
- Create: `src/Twig/Components/SkillBrowser.php`
- Create: `templates/components/SkillBrowser.html.twig`
- Create: `src/Controller/Public/SkillController.php`
- Create: `templates/public/skills/index.html.twig`

**SkillBrowser** Live Component:
```php
<?php

namespace App\Twig\Components;

use App\Repository\SkillRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class SkillBrowser
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    #[LiveProp(writable: true, url: true)]
    public string $tag = '';

    #[LiveProp(writable: true, url: true)]
    public string $sort = 'name';

    public function __construct(private SkillRepository $skillRepository) {}

    public function getSkills(): array
    {
        return $this->skillRepository->search($this->query, $this->tag, $this->sort);
    }

    public function getTags(): array
    {
        return $this->skillRepository->findAllTags();
    }
}
```

Template uses `data-model="debounce(300)|query"` for the search input, clickable tag chips that set `tag`, and sort buttons.

**Controller** just renders the page that embeds the Live Component:
```php
#[Route('/skills', name: 'app_skills')]
public function index(): Response
{
    return $this->render('public/skills/index.html.twig');
}
```

Template:
```twig
{% extends 'base.html.twig' %}
{% block body %}
    <twig:SkillBrowser />
{% endblock %}
```

**Commit message:** `feat: add skill browser with Live Component search`

---

### Task 10: Skill Detail Page

**Files:**
- Create: `src/Controller/Public/SkillDetailController.php`
- Create: `templates/public/skills/show.html.twig`

Shows: name, description, author, version, tags, languages, action list (parsed from YAML), download count (from repository), bridge shortcut info if applicable.

Download count query:
```php
$downloadCount = $downloadRepository->count(['skill' => $skill]);
```

**Commit message:** `feat: add skill detail page`

---

### Task 11: Landing Page

**Files:**
- Create: `src/Controller/Public/HomeController.php`
- Create: `templates/public/home.html.twig`

Server-rendered landing page: hero section explaining OpenDispatch, how it works (3-step flow), CTA to browse skills, stats (skill count, total downloads).

**Commit message:** `feat: add landing page`

---

### Task 12: Docs System

**Files:**
- Create: `src/Controller/Public/DocsController.php`
- Create: `templates/public/docs/show.html.twig`
- Create: `content/docs/getting-started.md`
- Create: `content/docs/writing-skills.md`
- Modify: `config/services.yaml` (register commonmark)

**DocsController:**
```php
<?php

namespace App\Controller\Public;

use League\CommonMark\MarkdownConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DocsController extends AbstractController
{
    public function __construct(
        private MarkdownConverter $converter,
        private string $docsDir,
    ) {}

    #[Route('/docs/{slug}', name: 'app_docs', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function show(string $slug): Response
    {
        $path = $this->docsDir . '/' . $slug . '.md';
        if (!file_exists($path)) {
            throw $this->createNotFoundException();
        }

        $markdown = file_get_contents($path);
        $html = $this->converter->convert($markdown);

        return $this->render('public/docs/show.html.twig', [
            'content' => $html,
            'slug' => $slug,
            'navigation' => $this->buildNavigation(),
        ]);
    }

    private function buildNavigation(): array
    {
        $files = glob($this->docsDir . '/*.md');
        $nav = [];
        foreach ($files as $file) {
            $slug = basename($file, '.md');
            // Extract title from first H1 line
            $firstLine = strtok(file_get_contents($file), "\n");
            $title = ltrim($firstLine, '# ');
            $nav[] = ['slug' => $slug, 'title' => $title];
        }
        return $nav;
    }
}
```

Wire in `config/services.yaml`:
```yaml
App\Controller\Public\DocsController:
    arguments:
        $docsDir: '%kernel.project_dir%/content/docs'
```

Register CommonMark converter as a service.

**Commit message:** `feat: add docs system with markdown rendering`

---

### Task 13: UI Styling

**Files:**
- Modify: `assets/styles/app.css`
- Modify: `templates/base.html.twig`
- Create: `assets/controllers/confirm_controller.js`
- Modify all templates for consistent styling

Final polish — apply CSS, navigation, and consistent styling across all pages (public and admin). Uses the V1 CSS as a starting point but expands for the public-facing pages (skill cards, tag chips, landing page layout, docs sidebar).

Reference the Symfony UX skills in `.claude/skills/` for proper Stimulus/Turbo/Live Component patterns.

**Commit message:** `feat: style all pages with Symfony UX`

---

## Summary

| Task | Description | TDD | Key Deliverable |
|------|-------------|-----|-----------------|
| 0 | Clean slate + scaffold + Docker | — | Working Symfony 8.0 at repo root |
| 1 | Entities & migrations | — | Skill, SkillDownload, SyncLog, AdminUser |
| 2 | Authentication | — | Form login, admin user command |
| 3 | YAML Validator | Yes | Validator with tag allowlist |
| 4 | Sync Service | Yes | Pull repo, validate, upsert, compile |
| 5 | Compiler Service | Yes | Static file generation |
| 6 | Webhook + Sync Command | Yes | POST endpoint + CLI |
| 7 | Download API | Yes | YAML download with tracking |
| 8 | Admin Dashboard | — | Stats, featured toggle, sync log |
| 9 | Skill Browser | — | Live Component with search + tags |
| 10 | Skill Detail | — | Full skill info page |
| 11 | Landing Page | — | Marketing/intro page |
| 12 | Docs System | — | Markdown rendering |
| 13 | UI Styling | — | CSS + consistent templates |
