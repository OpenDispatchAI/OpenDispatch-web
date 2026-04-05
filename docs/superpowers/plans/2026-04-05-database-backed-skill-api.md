# Database-Backed Skill API Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers-extended-cc:subagent-driven-development (if subagents available) or superpowers-extended-cc:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move skill API from static files in `public/` to database-backed controller routes, fixing deploy wipes and enabling observability.

**Architecture:** Store compiled JSON, base64-encoded icons and shortcut binaries in SQLite. Serve via Symfony controllers with ETag caching. Compile at sync time, not request time. Versioned manifest history via insert-only table.

**Tech Stack:** Symfony 7, Doctrine ORM, SQLite, PHPUnit

**Spec:** `docs/superpowers/specs/2026-04-05-database-backed-skill-api-design.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|----------------|
| `src/Entity/Skill.php` | Modify | Add `iconData`, `shortcutData`, `compiledInfo` columns; remove `iconPath`, `bridgeShortcutFilePath` |
| `src/Entity/SkillManifest.php` | Create | Insert-only manifest entity |
| `src/Repository/SkillManifestRepository.php` | Create | `findLatest()` query |
| `src/Service/SkillCompiler.php` | Modify | Write to DB instead of filesystem |
| `src/Service/SkillSyncService.php` | Modify | Read files as base64, pass to entity; remove transient file path handling |
| `src/Controller/Api/SkillApiController.php` | Create | Catalog routes with ETag caching |
| `migrations/Version2026XXXXXXXXXX.php` | Create | Schema changes |
| `config/services.yaml` | Modify | Remove `$publicDir` binding |
| `.gitignore` | Modify | Remove `/public/api/` |
| `Makefile` | Modify | Remove `compile` target |
| `tests/Service/SkillCompilerTest.php` | Modify | Test DB writes instead of filesystem |
| `tests/Service/SkillSyncServiceTest.php` | Modify | Update for new entity properties |
| `tests/Controller/Api/SkillApiControllerTest.php` | Create | Test all catalog routes + ETag |

---

### Task 1: SkillManifest Entity and Repository

**Files:**
- Create: `src/Entity/SkillManifest.php`
- Create: `src/Repository/SkillManifestRepository.php`

- [ ] **Step 1: Write SkillManifest entity**

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SkillManifestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SkillManifestRepository::class)]
class SkillManifest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(length: 255)]
    private string $commitSha;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $content, string $commitSha)
    {
        $this->content = $content;
        $this->commitSha = $commitSha;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCommitSha(): string
    {
        return $this->commitSha;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
```

- [ ] **Step 2: Write SkillManifestRepository**

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SkillManifest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SkillManifest>
 */
class SkillManifestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SkillManifest::class);
    }

    public function findLatest(): ?SkillManifest
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function pruneOldManifests(int $keep = 50): int
    {
        $latest = $this->createQueryBuilder('m')
            ->select('m.id')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($keep)
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($latest)) {
            return 0;
        }

        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.id NOT IN (:ids)')
            ->setParameter('ids', $latest)
            ->getQuery()
            ->execute();
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Entity/SkillManifest.php src/Repository/SkillManifestRepository.php
git commit -m "feat: add SkillManifest entity and repository"
```

---

### Task 2: Modify Skill Entity

**Files:**
- Modify: `src/Entity/Skill.php`

- [ ] **Step 1: Add new columns, remove old ones**

Add three new properties after `iconPath` (line 65):

```php
#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $iconData = null;

#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $shortcutData = null;

#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $compiledInfo = null;
```

Remove:
- `iconPath` property (line 64-65) and its `#[ORM\Column]` attribute
- `bridgeShortcutFilePath` property (line 67-68) and its docblock
- `getIconPath()` / `setIconPath()` methods (lines 297-307)
- `getBridgeShortcutFilePath()` / `setBridgeShortcutFilePath()` methods (lines 309-319)

Add getters/setters for the three new properties:

```php
public function getIconData(): ?string
{
    return $this->iconData;
}

public function setIconData(?string $iconData): static
{
    $this->iconData = $iconData;

    return $this;
}

public function getShortcutData(): ?string
{
    return $this->shortcutData;
}

public function setShortcutData(?string $shortcutData): static
{
    $this->shortcutData = $shortcutData;

    return $this;
}

public function getCompiledInfo(): ?string
{
    return $this->compiledInfo;
}

public function setCompiledInfo(?string $compiledInfo): static
{
    $this->compiledInfo = $compiledInfo;

    return $this;
}
```

- [ ] **Step 2: Widen `bridgeShortcutShareUrl` column for absolute URLs**

Change line 55-56 from `length: 255` to `type: Types::TEXT` since absolute URLs can exceed 255 chars:

```php
#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $bridgeShortcutShareUrl = null;
```

- [ ] **Step 3: Run tests to check for breakage**

Run: `php bin/phpunit`
Expected: Some tests may fail due to removed methods — that's expected and will be fixed in later tasks.

- [ ] **Step 4: Commit**

```bash
git add src/Entity/Skill.php
git commit -m "feat: add iconData, shortcutData, compiledInfo to Skill; remove iconPath"
```

---

### Task 3: Database Migration

**Files:**
- Create: `migrations/VersionXXXX.php` (auto-generated)

- [ ] **Step 1: Generate migration**

Run: `php bin/console doctrine:migrations:diff`

This should generate a migration that:
- Adds `icon_data`, `shortcut_data`, `compiled_info` TEXT columns to `skill`
- Drops `icon_path` from `skill`
- Changes `bridge_shortcut_share_url` to TEXT
- Creates `skill_manifest` table with `id`, `content`, `commit_sha`, `created_at`

- [ ] **Step 2: Review the generated migration**

Verify the SQL is correct. For SQLite, column drops require table recreation — Doctrine handles this.

- [ ] **Step 3: Run migration**

Run: `php bin/console doctrine:migrations:migrate --no-interaction`

- [ ] **Step 4: Commit**

```bash
git add migrations/
git commit -m "feat: migration for database-backed skill API"
```

---

### Task 4: Modify SkillSyncService — Base64 Encode Files

**Files:**
- Modify: `src/Service/SkillSyncService.php`
- Modify: `tests/Service/SkillSyncServiceTest.php`

- [ ] **Step 1: Write failing test for icon base64 encoding**

In `SkillSyncServiceTest.php`, add a test that verifies icon data is base64-encoded on the Skill entity after sync. The valid fixture directory needs an `icon.png` file.

Create a fixture icon:
```bash
# Create a tiny valid PNG (1x1 pixel) for the test fixture
php -r "file_put_contents('tests/fixtures/skills-repo-valid/skills/tesla/icon.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));"
```

Add test:
```php
public function testSyncBase64EncodesIconData(): void
{
    $this->skillRepository->method('findOneBy')->willReturn(null);
    $this->skillRepository->method('findAll')->willReturn([]);

    $result = $this->service->sync('abc123def456', 'https://github.com/example/commit/abc123', null);

    self::assertSame('success', $result->getStatus());

    $skills = array_filter($this->persisted, fn(object $e) => $e instanceof Skill);
    $skill = reset($skills);

    self::assertNotNull($skill->getIconData());
    // Decoding should produce valid PNG bytes
    $decoded = base64_decode($skill->getIconData(), true);
    self::assertNotFalse($decoded);
    self::assertStringStartsWith("\x89PNG", $decoded);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Service/SkillSyncServiceTest.php --filter=testSyncBase64EncodesIconData`
Expected: FAIL — `getIconData()` returns null because sync doesn't set it yet.

- [ ] **Step 3: Write failing test for shortcut base64 encoding**

```php
public function testSyncBase64EncodesShortcutData(): void
{
    $this->skillRepository->method('findOneBy')->willReturn(null);
    $this->skillRepository->method('findAll')->willReturn([]);

    $result = $this->service->sync('abc123def456', 'https://github.com/example/commit/abc123', null);

    self::assertSame('success', $result->getStatus());

    $skills = array_filter($this->persisted, fn(object $e) => $e instanceof Skill);
    $skill = reset($skills);

    self::assertNotNull($skill->getShortcutData());
    $decoded = base64_decode($skill->getShortcutData(), true);
    self::assertNotFalse($decoded);
}
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php bin/phpunit tests/Service/SkillSyncServiceTest.php --filter=testSyncBase64EncodesShortcutData`
Expected: FAIL

- [ ] **Step 5: Implement base64 encoding in SkillSyncService**

In `processRepo()`, replace the icon/shortcut file path handling (lines 109-137) with base64 encoding:

Replace the `$parsedSkills[]` block (after the bridge_shortcut_source handling) with:
```php
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
```

Replace the entire upsert `foreach` body (lines 119-139) with:
```php
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
```

Remove the old `$iconPath` variable at line 77 and `$shortcutFilePath` initialization at line 78 — both are now handled in the new block above (though `$shortcutFilePath` is still set in the bridge_shortcut_source handling).

- [ ] **Step 6: Update existing tests**

Update `testSyncRewritesBridgeShortcutSource()` — replace the assertion on `getBridgeShortcutFilePath()`:
```php
// Old: self::assertNotNull($skill->getBridgeShortcutFilePath());
self::assertNotNull($skill->getShortcutData());
```

- [ ] **Step 7: Run all sync tests**

Run: `php bin/phpunit tests/Service/SkillSyncServiceTest.php`
Expected: ALL PASS

- [ ] **Step 8: Commit**

```bash
git add src/Service/SkillSyncService.php tests/Service/SkillSyncServiceTest.php tests/fixtures/skills-repo-valid/skills/tesla/icon.png
git commit -m "feat: base64 encode icon and shortcut data during sync"
```

---

### Task 5: Modify SkillCompiler — Write to DB Instead of Filesystem

**Files:**
- Modify: `src/Service/SkillCompiler.php`
- Modify: `tests/Service/SkillCompilerTest.php`
- Modify: `config/services.yaml`

- [ ] **Step 1: Write failing test for compiled_info storage**

Rewrite `SkillCompilerTest` to test DB writes. Replace filesystem assertions with entity state assertions.

```php
public function testCompileStoresCompiledInfoOnSkill(): void
{
    $skill = $this->createSkill('tesla', $this->fixtureYaml);

    $this->compiler->compile([$skill], 'abc123');

    self::assertNotNull($skill->getCompiledInfo());
    $info = json_decode($skill->getCompiledInfo(), true);
    self::assertSame('tesla', $info['skill_id']);
    self::assertSame('Tesla', $info['name']);
    self::assertCount(2, $info['actions']);
}
```

- [ ] **Step 2: Write failing test for manifest creation**

```php
public function testCompileCreatesManifest(): void
{
    $skill = $this->createSkill('tesla', $this->fixtureYaml);
    $this->skillRepository->method('findAll')->willReturn([$skill]);

    $this->compiler->compile([$skill], 'abc123');

    $manifests = array_filter($this->persisted, fn($e) => $e instanceof SkillManifest);
    self::assertCount(1, $manifests);
    $manifest = reset($manifests);
    self::assertSame('abc123', $manifest->getCommitSha());

    $index = json_decode($manifest->getContent(), true);
    self::assertSame(1, $index['version']);
    self::assertSame(1, $index['skill_count']);
    self::assertCount(1, $index['skills']);
    self::assertSame('tesla', $index['skills'][0]['skill_id']);
}
```

- [ ] **Step 3: Write failing test for absolute URLs in index**

```php
public function testCompileUsesAbsoluteUrls(): void
{
    $skill = $this->createSkill('tesla', $this->fixtureYaml);
    $skill->setIconData('dummybase64');

    $this->compiler->compile([$skill], 'abc123');

    $manifest = $this->persisted[0];
    $index = json_decode($manifest->getContent(), true);
    $entry = $index['skills'][0];

    self::assertStringStartsWith('https://', $entry['download_url']);
    self::assertStringStartsWith('https://', $entry['icon_url']);
}
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `php bin/phpunit tests/Service/SkillCompilerTest.php`
Expected: FAIL — compiler still writes to filesystem

- [ ] **Step 5: Rewrite SkillCompiler**

Replace constructor and all methods. Keep the existing `use LoggerTrait;` statement. New constructor:

```php
public function __construct(
    private EntityManagerInterface $em,
    private RouterInterface $router,
) {}
```

New `compile()` method — accepts a skills array instead of re-querying (important: orphan removals may not be flushed yet, so the compiler must not use `findAll()`):
```php
/** @param Skill[] $skills */
public function compile(array $skills, string $commitSha): void
{
    foreach ($skills as $skill) {
        $this->compileSkill($skill);
    }

    $this->compileIndex($skills, $commitSha);

    $this->logger?->info('Compilation complete', ['skillCount' => count($skills)]);
}
```

The `SkillRepository` dependency can be removed from the constructor since the compiler no longer queries skills itself.

New `compileSkill()` — store `compiled_info` on entity:
```php
private function compileSkill(Skill $skill): void
{
    $data = Yaml::parse($skill->getYamlContent());
    $info = $this->buildSkillInfo($skill, $data);
    $skill->setCompiledInfo(
        json_encode($info, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
    );
}
```

New `compileIndex()` — insert SkillManifest:
```php
/** @param Skill[] $skills */
private function compileIndex(array $skills, string $commitSha): void
{
    $index = [
        'version' => 1,
        'generated_at' => (new \DateTimeImmutable())->format('c'),
        'skill_count' => count($skills),
        'skills' => array_map($this->buildIndexEntry(...), $skills),
    ];

    $json = json_encode($index, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $manifest = new SkillManifest($json, $commitSha);
    $this->em->persist($manifest);
}
```

Update `buildIndexEntry()` — use absolute URLs, derive `icon_url` from `iconData`:
```php
private function buildIndexEntry(Skill $skill): array
{
    $ctx = $this->router->getContext();
    $baseUrl = $ctx->getScheme() . '://' . $ctx->getHost();

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
        'download_url' => $baseUrl . '/api/v1/skills/' . $skill->getSkillId() . '/download',
        'icon_url' => $skill->getIconData()
            ? $baseUrl . '/api/v1/skills/' . $skill->getSkillId() . '/icon.png'
            : null,
        'created_at' => $skill->getCreatedAt()->format('c'),
        'updated_at' => $skill->getUpdatedAt()->format('c'),
    ];
}
```

`buildSkillInfo()` stays the same (lines 115-145) — no changes needed.

Remove: `cleanupRemovedSkills()`, `Filesystem` import and property.

- [ ] **Step 6: Update SkillSyncService to pass commitSha to compile**

In `src/Service/SkillSyncService.php`, collect the upserted skills into an array and pass them to compile. Replace the flush-then-compile block (lines 149-152) with:
```php
// 7. Compile (writes compiled_info on each skill entity, creates SkillManifest — not flushed yet)
$this->compiler->compile($upsertedSkills, $commitSha);

// 8. Flush everything atomically (skills + manifest in one transaction)
$this->em->flush();
```

Build `$upsertedSkills` by collecting each skill during the upsert loop:
```php
$upsertedSkills = [];
foreach ($parsedSkills as $parsed) {
    // ... existing upsert logic ...
    $upsertedSkills[] = $skill;
}
```

- [ ] **Step 7: Update services.yaml**

Remove the `$publicDir` binding from `SkillCompiler` in `config/services.yaml` (lines 27-29). The compiler now autowires `EntityManagerInterface` and `RouterInterface`.

- [ ] **Step 8: Rewrite SkillCompilerTest**

Full rewrite needed. The test now stubs `EntityManagerInterface` and `RouterInterface` instead of checking filesystem. Set up:
```php
private array $persisted = [];
private SkillCompiler $compiler;
private string $fixtureYaml;

protected function setUp(): void
{
    $this->persisted = [];

    $em = $this->createStub(EntityManagerInterface::class);
    $em->method('persist')->willReturnCallback(function (object $entity): void {
        $this->persisted[] = $entity;
    });

    $context = new RequestContext();
    $context->setScheme('https');
    $context->setHost('app.opendispatch.org');
    $router = $this->createStub(RouterInterface::class);
    $router->method('getContext')->willReturn($context);

    $this->compiler = new SkillCompiler($em, $router);
    $this->fixtureYaml = file_get_contents(__DIR__ . '/../fixtures/skills-repo/skills/tesla/skill.yaml');
}
```

Remove `tearDown()`, `removeDirectory()`, and all filesystem-related assertions. Remove the `testCompileRemovesOrphanedSkillFiles` and `testCompileCopiesBridgeShortcutFile` tests — those concerns no longer exist in the compiler.

Keep and adapt: `testCompileCreatesIndexJson` (assert via manifest content), `testCompileCreatesInfoJson` (assert via `compiledInfo`), `testCompileWithEmptySkillListCreatesEmptyIndex`.

- [ ] **Step 9: Run all tests**

Run: `php bin/phpunit`
Expected: ALL PASS

- [ ] **Step 10: Commit**

```bash
git add src/Service/SkillCompiler.php src/Service/SkillSyncService.php tests/Service/SkillCompilerTest.php config/services.yaml
git commit -m "feat: SkillCompiler writes to DB instead of filesystem"
```

---

### Task 6: SkillApiController — Catalog Routes

**Files:**
- Create: `src/Controller/Api/SkillApiController.php`
- Create: `tests/Controller/Api/SkillApiControllerTest.php`

- [ ] **Step 1: Write failing test for index.json route**

Use Symfony's `WebTestCase` for functional testing. Since the project does not use `DAMA\DoctrineTestBundle`, each test must clean up its own data in `tearDown()` or use a fresh in-memory SQLite database. Configure `.env.test` with `DATABASE_URL="sqlite:///:memory:"` if not already set, so each test process starts with a clean database. Run migrations in a `setUp()` parent call or use `$kernel->getContainer()->get('doctrine')` to recreate the schema.

```php
<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\SkillManifest;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SkillApiControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    public function testIndexJson(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $manifest = new SkillManifest('{"version":1,"skills":[]}', 'abc123');
        $em->persist($manifest);
        $em->flush();

        $client->request('GET', '/api/v1/index.json');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(1, $data['version']);
    }

    public function testIndexJsonReturns404WhenNoManifest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/index.json');

        self::assertResponseStatusCodeSame(404);
    }

    public function testIndexJsonEtag(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $manifest = new SkillManifest('{"version":1}', 'abc123');
        $em->persist($manifest);
        $em->flush();

        $client->request('GET', '/api/v1/index.json');
        $etag = $client->getResponse()->headers->get('ETag');

        $client->request('GET', '/api/v1/index.json', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);
    }
}
```

- [ ] **Step 2: Write failing tests for skill info, yaml, icon, shortcut routes**

Add to the same test class. For skill routes, create a Skill entity in the test DB first. Test:
- `GET /api/v1/skills/tesla/info.json` — returns compiled_info JSON
- `GET /api/v1/skills/tesla/skill.yaml` — returns YAML with `Content-Type: text/yaml`
- `GET /api/v1/skills/tesla/icon.png` — returns decoded PNG with `Content-Type: image/png`
- `GET /api/v1/skills/tesla/MyShortcut.shortcut` — returns decoded binary with `Content-Disposition: attachment`
- `GET /api/v1/skills/tesla/WrongName.shortcut` — returns 404
- `GET /api/v1/skills/nonexistent/info.json` — returns 404
- ETag / 304 for each route

- [ ] **Step 3: Run tests to verify they fail**

Run: `php bin/phpunit tests/Controller/Api/SkillApiControllerTest.php`
Expected: FAIL — routes don't exist yet

- [ ] **Step 4: Implement SkillApiController**

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\SkillManifestRepository;
use App\Repository\SkillRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SkillApiController extends AbstractController
{
    #[Route('/api/v1/index.json', name: 'api_skill_index')]
    public function index(Request $request, SkillManifestRepository $manifestRepository): Response
    {
        $manifest = $manifestRepository->findLatest();
        if (!$manifest) {
            throw $this->createNotFoundException();
        }

        $response = new Response($manifest->getContent(), 200, [
            'Content-Type' => 'application/json',
        ]);
        $response->setEtag((string) $manifest->getCreatedAt()->getTimestamp());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    #[Route('/api/v1/skills/{skillId}/info.json', name: 'api_skill_info')]
    public function info(string $skillId, Request $request, SkillRepository $skillRepository): Response
    {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill || !$skill->getCompiledInfo()) {
            throw $this->createNotFoundException();
        }

        $response = new Response($skill->getCompiledInfo(), 200, [
            'Content-Type' => 'application/json',
        ]);
        $response->setEtag((string) $skill->getUpdatedAt()->getTimestamp());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    #[Route('/api/v1/skills/{skillId}/skill.yaml', name: 'api_skill_yaml')]
    public function yaml(string $skillId, Request $request, SkillRepository $skillRepository): Response
    {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill) {
            throw $this->createNotFoundException();
        }

        $response = new Response($skill->getYamlContent(), 200, [
            'Content-Type' => 'text/yaml',
            'Content-Disposition' => 'inline',
        ]);
        $response->setEtag((string) $skill->getUpdatedAt()->getTimestamp());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    #[Route('/api/v1/skills/{skillId}/icon.png', name: 'api_skill_icon')]
    public function icon(string $skillId, Request $request, SkillRepository $skillRepository): Response
    {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill || !$skill->getIconData()) {
            throw $this->createNotFoundException();
        }

        $response = new Response(base64_decode($skill->getIconData()), 200, [
            'Content-Type' => 'image/png',
        ]);
        $response->setEtag((string) $skill->getUpdatedAt()->getTimestamp());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    #[Route('/api/v1/skills/{skillId}/{filename}.shortcut', name: 'api_skill_shortcut')]
    public function shortcut(
        string $skillId,
        string $filename,
        Request $request,
        SkillRepository $skillRepository,
    ): Response {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill || !$skill->getShortcutData()) {
            throw $this->createNotFoundException();
        }

        // Validate filename matches the skill's bridge shortcut name
        if ($filename !== $skill->getBridgeShortcutName()) {
            throw $this->createNotFoundException();
        }

        $response = new Response(base64_decode($skill->getShortcutData()), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.shortcut"',
        ]);
        $response->setEtag((string) $skill->getUpdatedAt()->getTimestamp());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
```

- [ ] **Step 5: Run all tests**

Run: `php bin/phpunit`
Expected: ALL PASS

- [ ] **Step 6: Commit**

```bash
git add src/Controller/Api/SkillApiController.php tests/Controller/Api/SkillApiControllerTest.php
git commit -m "feat: add SkillApiController with ETag caching"
```

---

### Task 7: Manifest Pruning

**Files:**
- Modify: `src/Service/SkillSyncService.php`
- Modify: `tests/Service/SkillSyncServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
public function testSyncPrunesOldManifests(): void
{
    // Verify pruneOldManifests is called during sync
    // This is best tested via the SkillManifestRepository mock
    $this->skillRepository->method('findOneBy')->willReturn(null);
    $this->skillRepository->method('findAll')->willReturn([]);

    $result = $this->service->sync('abc123def456', 'https://github.com/example/commit/abc123', null);

    self::assertSame('success', $result->getStatus());
    self::assertTrue($this->manifestPruned, 'Manifest pruning should have been called');
}
```

Wire up `$this->manifestPruned` flag in setUp via a `SkillManifestRepository` stub.

- [ ] **Step 2: Implement pruning call**

In `SkillSyncService`, inject `SkillManifestRepository` and call `pruneOldManifests()` after the flush.

- [ ] **Step 3: Run tests**

Run: `php bin/phpunit`
Expected: ALL PASS

- [ ] **Step 4: Commit**

```bash
git add src/Service/SkillSyncService.php tests/Service/SkillSyncServiceTest.php
git commit -m "feat: prune old manifests during sync"
```

---

### Task 8: Cleanup

**Files:**
- Modify: `.gitignore`
- Modify: `Makefile`
- Remove: `public/api/` directory (if present locally)

- [ ] **Step 1: Remove `/public/api/` from .gitignore**

Remove the line `/public/api/` from `.gitignore`.

- [ ] **Step 2: Remove `compile` target from Makefile**

Remove the `compile:` target and its command. Keep `sync:` as-is.

- [ ] **Step 3: Remove local public/api/ directory if present**

```bash
rm -rf public/api/
```

- [ ] **Step 4: Run full test suite**

Run: `php bin/phpunit`
Expected: ALL PASS

- [ ] **Step 5: Commit**

```bash
git add .gitignore Makefile
git commit -m "chore: cleanup static file artifacts"
```

---

### Task 9: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `php bin/phpunit`
Expected: ALL PASS, no warnings

- [ ] **Step 2: Verify no references to removed code**

Search for `iconPath`, `bridgeShortcutFilePath`, `getIconPath`, `setBridgeShortcutFilePath`, `$publicDir` in PHP files — should find no matches.

Run: `grep -r 'iconPath\|bridgeShortcutFilePath\|getIconPath\|setBridgeShortcutFilePath' src/ tests/`
Expected: No matches

- [ ] **Step 3: Verify migration applies cleanly on fresh DB**

```bash
rm -f var/data/data.db
php bin/console doctrine:migrations:migrate --no-interaction
```

- [ ] **Step 4: Test a manual sync end-to-end**

Run: `php bin/console app:sync`
Verify it completes successfully and populates the database with compiled data.
