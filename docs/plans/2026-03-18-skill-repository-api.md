# Skill Repository API Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:executing-plans to implement this plan task-by-task.

**Goal:** Build a Symfony 8.0 backend that manages OpenDispatch skills — admin uploads YAML, compiles static API files served by Nginx.

**Architecture:** Nginx serves static compiled files for the public API (`public/api/v1/`). Symfony handles admin CRUD + compile. PostgreSQL stores skill data. Docker Compose orchestrates all services.

**Tech Stack:** Symfony 8.0, PHP 8.5, PostgreSQL 17, Twig + Symfony UX (Stimulus/Turbo/Twig Components), Docker, AssetMapper

**Design doc:** `docs/plans/2026-03-18-skill-repository-api-design.md`

**Project structure:**
```
OpenDispatch-web/
├── backend/                 # Symfony app
│   ├── config/
│   ├── migrations/
│   ├── public/
│   │   ├── api/v1/          # Compiled output (gitignored)
│   │   └── index.php
│   ├── src/
│   │   ├── Command/
│   │   ├── Controller/Admin/
│   │   ├── Entity/
│   │   ├── Form/
│   │   ├── Repository/
│   │   └── Service/
│   ├── templates/
│   ├── tests/
│   ├── var/icons/           # Uploaded icons storage
│   ├── .env
│   └── composer.json
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
├── docs/plans/
├── docker-compose.yml
└── Makefile
```

---

### Task 0: Scaffold Symfony Project

**Files:**
- Create: `backend/` (entire Symfony skeleton)
- Modify: `backend/.env`
- Modify: `backend/.gitignore`

**Step 1: Create Symfony skeleton**

Run (from project root):
```bash
composer create-project symfony/skeleton:^8.0 backend_tmp
mv backend/skill-repository-api-prd.md backend_tmp/
rm -rf backend
mv backend_tmp backend
```

Expected: fresh Symfony 8.0 skeleton in `backend/`.

**Step 2: Install required packages**

Run:
```bash
cd backend

# Core
composer require symfony/orm-pack
composer require symfony/security-bundle
composer require symfony/form
composer require symfony/validator
composer require symfony/twig-bundle
composer require symfony/uid
composer require symfony/asset-mapper
composer require symfony/mime

# Symfony UX
composer require symfony/stimulus-bundle
composer require symfony/ux-turbo
composer require symfony/ux-twig-component
composer require symfony/ux-live-component

# Dev
composer require --dev symfony/test-pack
composer require --dev symfony/maker-bundle
composer require --dev doctrine/doctrine-fixtures-bundle
```

**Step 3: Configure database connection**

Edit `backend/.env` — set the DATABASE_URL:
```
DATABASE_URL="postgresql://opendispatch:opendispatch@postgres:5432/opendispatch?serverVersion=17&charset=utf8"
```

**Step 4: Add compiled output to gitignore**

Append to `backend/.gitignore`:
```
/public/api/
/var/icons/
```

**Step 5: Commit**

```bash
git add backend/
git commit -m "feat: scaffold Symfony 8.0 project with dependencies"
```

---

### Task 1: Docker Setup

**Files:**
- Create: `docker/php/Dockerfile`
- Create: `docker/nginx/default.conf`
- Create: `docker-compose.yml`
- Create: `Makefile`

**Step 1: Create PHP Dockerfile**

Create `docker/php/Dockerfile`:
```dockerfile
FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    && docker-php-ext-install \
    pdo_pgsql \
    intl \
    opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
```

**Step 2: Create Nginx config**

Create `docker/nginx/default.conf`:
```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass php:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $document_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    error_log /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
}
```

Note: `/api/v1/*` files live in `public/api/v1/` so `try_files $uri` serves them directly as static files — no special location block needed.

**Step 3: Create docker-compose.yml**

Create `docker-compose.yml` at project root:
```yaml
services:
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    volumes:
      - ./backend:/var/www/html
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
      - ./backend/public:/var/www/html/public:ro
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

**Step 4: Create Makefile**

Create `Makefile` at project root:
```makefile
.PHONY: up down build shell migrate test compile console

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

console:
	docker compose exec php bin/console $(cmd)
```

**Step 5: Build and verify**

Run:
```bash
make build && make up
```

Expected: all three containers running. Verify:
```bash
docker compose ps
```

**Step 6: Install Composer dependencies inside container**

Run:
```bash
docker compose exec php composer install
```

**Step 7: Verify Symfony responds**

Run:
```bash
curl http://localhost:8080
```

Expected: Symfony welcome page or a 404 (since no routes defined yet). Either confirms Nginx→PHP-FPM is working.

**Step 8: Commit**

```bash
git add docker/ docker-compose.yml Makefile
git commit -m "feat: add Docker setup (nginx + php-fpm + postgres)"
```

---

### Task 2: Entities & Migrations

**Files:**
- Create: `backend/src/Entity/Skill.php`
- Create: `backend/src/Entity/AdminUser.php`
- Create: `backend/src/Repository/SkillRepository.php`
- Create: `backend/src/Repository/AdminUserRepository.php`
- Create: `backend/migrations/VersionXXX.php` (auto-generated)

**Step 1: Create Skill entity**

Create `backend/src/Entity/Skill.php`:
```php
<?php

namespace App\Entity;

use App\Repository\SkillRepository;
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iconPath = null;

    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSkillId(): string
    {
        return $this->skillId;
    }

    public function setSkillId(string $skillId): static
    {
        $this->skillId = $skillId;
        return $this;
    }

    public function getYamlContent(): string
    {
        return $this->yamlContent;
    }

    public function setYamlContent(string $yamlContent): static
    {
        $this->yamlContent = $yamlContent;
        return $this;
    }

    public function getIconPath(): ?string
    {
        return $this->iconPath;
    }

    public function setIconPath(?string $iconPath): static
    {
        $this->iconPath = $iconPath;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->publishedAt !== null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

**Step 2: Create SkillRepository**

Create `backend/src/Repository/SkillRepository.php`:
```php
<?php

namespace App\Repository;

use App\Entity\Skill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Skill>
 */
class SkillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Skill::class);
    }

    /**
     * @return Skill[]
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.publishedAt IS NOT NULL')
            ->orderBy('s.skillId', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
```

**Step 3: Create AdminUser entity**

Create `backend/src/Entity/AdminUser.php`:
```php
<?php

namespace App\Entity;

use App\Repository\AdminUserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AdminUserRepository::class)]
class AdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }
}
```

**Step 4: Create AdminUserRepository**

Create `backend/src/Repository/AdminUserRepository.php`:
```php
<?php

namespace App\Repository;

use App\Entity\AdminUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminUser>
 */
class AdminUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUser::class);
    }
}
```

**Step 5: Generate and run migration**

Run:
```bash
docker compose exec php bin/console doctrine:migrations:diff
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

Expected: migration creates `skill` and `admin_user` tables.

**Step 6: Verify tables exist**

Run:
```bash
docker compose exec postgres psql -U opendispatch -c "\dt"
```

Expected: `skill`, `admin_user`, `doctrine_migration_versions` tables listed.

**Step 7: Commit**

```bash
git add backend/src/Entity/ backend/src/Repository/ backend/migrations/
git commit -m "feat: add Skill and AdminUser entities with migration"
```

---

### Task 3: Authentication

**Files:**
- Modify: `backend/config/packages/security.yaml`
- Create: `backend/src/Controller/Admin/LoginController.php`
- Create: `backend/templates/admin/login.html.twig`
- Create: `backend/src/Command/CreateAdminCommand.php`

**Step 1: Configure security**

Replace `backend/config/packages/security.yaml`:
```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    providers:
        admin_user_provider:
            entity:
                class: App\Entity\AdminUser
                property: email

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        main:
            lazy: true
            provider: admin_user_provider
            form_login:
                login_path: app_login
                check_path: app_login
                default_target_path: app_admin_skill_list
            logout:
                path: app_logout

    access_control:
        - { path: ^/admin/login, roles: PUBLIC_ACCESS }
        - { path: ^/admin, roles: ROLE_ADMIN }
```

**Step 2: Create LoginController**

Create `backend/src/Controller/Admin/LoginController.php`:
```php
<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/admin/login', name: 'app_login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_admin_skill_list');
        }

        return $this->render('admin/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error' => $authUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/admin/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the security firewall.');
    }
}
```

**Step 3: Create login template**

Create `backend/templates/admin/login.html.twig`:
```twig
{% extends 'base.html.twig' %}

{% block title %}Login — OpenDispatch Admin{% endblock %}

{% block body %}
<div class="login-container">
    <h1>OpenDispatch Admin</h1>

    {% if error %}
        <div class="alert alert-danger">{{ error.messageKey|trans(error.messageData, 'security') }}</div>
    {% endif %}

    <form method="post">
        <div class="form-group">
            <label for="username">Email</label>
            <input type="email" id="username" name="_username" value="{{ last_username }}" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="_password" required>
        </div>
        <button type="submit">Log in</button>
    </form>
</div>
{% endblock %}
```

**Step 4: Create CreateAdminCommand**

Create `backend/src/Command/CreateAdminCommand.php`:
```php
<?php

namespace App\Command;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email');
        $password = $io->askHidden('Password');

        $user = new AdminUser();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success("Admin user '{$email}' created.");

        return Command::SUCCESS;
    }
}
```

**Step 5: Create admin user and verify login**

Run:
```bash
docker compose exec php bin/console app:create-admin
```

Enter email and password when prompted. Then open `http://localhost:8080/admin/login` in a browser and verify login works and redirects to `/admin/skills` (which will 404 for now — that's expected).

**Step 6: Commit**

```bash
git add backend/config/packages/security.yaml backend/src/Controller/Admin/LoginController.php backend/templates/admin/login.html.twig backend/src/Command/CreateAdminCommand.php
git commit -m "feat: add admin authentication with form login"
```

---

### Task 4: YAML Validation Service (TDD)

**Files:**
- Create: `backend/tests/Service/SkillYamlValidatorTest.php`
- Create: `backend/src/Service/SkillYamlValidator.php`
- Create: `backend/tests/fixtures/valid-skill.yaml`
- Create: `backend/tests/fixtures/invalid-skill-no-actions.yaml`

**Step 1: Create test fixtures**

Create `backend/tests/fixtures/valid-skill.yaml`:
```yaml
skill_id: tesla
name: Tesla
version: 1.0.0
description: "Control your Tesla"
author: opendispatch
author_url: https://opendispatch.ai
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

Create `backend/tests/fixtures/invalid-skill-no-actions.yaml`:
```yaml
skill_id: broken
name: Broken Skill
version: 1.0.0
```

**Step 2: Write failing tests**

Create `backend/tests/Service/SkillYamlValidatorTest.php`:
```php
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
```

**Step 3: Run tests to verify they fail**

Run:
```bash
docker compose exec php bin/phpunit tests/Service/SkillYamlValidatorTest.php
```

Expected: FAIL — class `App\Service\SkillYamlValidator` not found.

**Step 4: Implement SkillYamlValidator**

Create `backend/src/Service/SkillYamlValidator.php`:
```php
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
```

**Step 5: Run tests to verify they pass**

Run:
```bash
docker compose exec php bin/phpunit tests/Service/SkillYamlValidatorTest.php
```

Expected: all 11 tests PASS.

**Step 6: Commit**

```bash
git add backend/src/Service/SkillYamlValidator.php backend/tests/Service/SkillYamlValidatorTest.php backend/tests/fixtures/
git commit -m "feat: add YAML skill validator with tests"
```

---

### Task 5: Compile Service (TDD)

**Files:**
- Create: `backend/tests/Service/SkillCompilerTest.php`
- Create: `backend/src/Service/SkillCompiler.php`

**Step 1: Write failing tests**

Create `backend/tests/Service/SkillCompilerTest.php`:
```php
<?php

namespace App\Tests\Service;

use App\Entity\Skill;
use App\Repository\SkillRepository;
use App\Service\SkillCompiler;
use PHPUnit\Framework\TestCase;

class SkillCompilerTest extends TestCase
{
    private string $outputDir;
    private SkillCompiler $compiler;
    private SkillRepository $skillRepository;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/opendispatch_test_' . uniqid();
        mkdir($this->outputDir, 0755, true);

        $this->skillRepository = $this->createMock(SkillRepository::class);
        $this->compiler = new SkillCompiler($this->skillRepository, $this->outputDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->outputDir);
    }

    public function testCompileCreatesIndexJson(): void
    {
        $skill = $this->createSkill('tesla', file_get_contents(__DIR__ . '/../fixtures/valid-skill.yaml'));

        $this->skillRepository->method('findPublished')->willReturn([$skill]);

        $this->compiler->compile();

        $indexPath = $this->outputDir . '/api/v1/index.json';
        $this->assertFileExists($indexPath);

        $index = json_decode(file_get_contents($indexPath), true);
        $this->assertSame(1, $index['version']);
        $this->assertSame(1, $index['skill_count']);
        $this->assertSame('tesla', $index['skills'][0]['skill_id']);
        $this->assertSame('Tesla', $index['skills'][0]['name']);
        $this->assertSame('1.0.0', $index['skills'][0]['version']);
        $this->assertSame(2, $index['skills'][0]['action_count']);
        $this->assertSame(3, $index['skills'][0]['example_count']);
        $this->assertSame(['test-tag'], $index['skills'][0]['tags']);
    }

    public function testCompileCreatesSkillYaml(): void
    {
        $yamlContent = file_get_contents(__DIR__ . '/../fixtures/valid-skill.yaml');
        $skill = $this->createSkill('tesla', $yamlContent);

        $this->skillRepository->method('findPublished')->willReturn([$skill]);

        $this->compiler->compile();

        $yamlPath = $this->outputDir . '/api/v1/skills/tesla/skill.yaml';
        $this->assertFileExists($yamlPath);
        $this->assertSame($yamlContent, file_get_contents($yamlPath));
    }

    public function testCompileCreatesInfoJson(): void
    {
        $skill = $this->createSkill('tesla', file_get_contents(__DIR__ . '/../fixtures/valid-skill.yaml'));

        $this->skillRepository->method('findPublished')->willReturn([$skill]);

        $this->compiler->compile();

        $infoPath = $this->outputDir . '/api/v1/skills/tesla/info.json';
        $this->assertFileExists($infoPath);

        $info = json_decode(file_get_contents($infoPath), true);
        $this->assertSame('tesla', $info['skill_id']);
        $this->assertCount(2, $info['actions']);
        $this->assertSame('vehicle.unlock', $info['actions'][0]['id']);
        $this->assertSame(2, $info['actions'][0]['example_count']);
        $this->assertFalse($info['actions'][0]['has_parameters']);
        $this->assertTrue($info['actions'][1]['has_parameters']);
    }

    public function testCompileRemovesUnpublishedSkillFiles(): void
    {
        // Create a skill directory that should be removed
        $oldDir = $this->outputDir . '/api/v1/skills/old_skill';
        mkdir($oldDir, 0755, true);
        file_put_contents($oldDir . '/skill.yaml', 'old');

        $this->skillRepository->method('findPublished')->willReturn([]);

        $this->compiler->compile();

        $this->assertDirectoryDoesNotExist($oldDir);
    }

    public function testCompileWithEmptySkillListCreatesEmptyIndex(): void
    {
        $this->skillRepository->method('findPublished')->willReturn([]);

        $this->compiler->compile();

        $indexPath = $this->outputDir . '/api/v1/index.json';
        $this->assertFileExists($indexPath);

        $index = json_decode(file_get_contents($indexPath), true);
        $this->assertSame(0, $index['skill_count']);
        $this->assertEmpty($index['skills']);
    }

    private function createSkill(string $skillId, string $yamlContent): Skill
    {
        $skill = new Skill();
        $skill->setSkillId($skillId);
        $skill->setYamlContent($yamlContent);
        $skill->setTags(['test-tag']);
        $skill->setPublishedAt(new \DateTimeImmutable());
        return $skill;
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (new \DirectoryIterator($path) as $item) {
            if ($item->isDot()) continue;
            if ($item->isDir()) {
                $this->removeDir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
```

**Step 2: Run tests to verify they fail**

Run:
```bash
docker compose exec php bin/phpunit tests/Service/SkillCompilerTest.php
```

Expected: FAIL — class `App\Service\SkillCompiler` not found.

**Step 3: Implement SkillCompiler**

Create `backend/src/Service/SkillCompiler.php`:
```php
<?php

namespace App\Service;

use App\Entity\Skill;
use App\Repository\SkillRepository;
use Symfony\Component\Yaml\Yaml;

class SkillCompiler
{
    public function __construct(
        private SkillRepository $skillRepository,
        private string $publicDir,
    ) {}

    public function compile(): void
    {
        $skills = $this->skillRepository->findPublished();

        $this->compileIndex($skills);

        foreach ($skills as $skill) {
            $this->compileSkill($skill);
        }

        $this->cleanupRemovedSkills($skills);
    }

    /**
     * @param Skill[] $skills
     */
    private function compileIndex(array $skills): void
    {
        $index = [
            'version' => 1,
            'generated_at' => (new \DateTimeImmutable())->format('c'),
            'skill_count' => count($skills),
            'skills' => array_map($this->buildIndexEntry(...), $skills),
        ];

        $dir = $this->publicDir . '/api/v1';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir . '/index.json',
            json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function compileSkill(Skill $skill): void
    {
        $data = Yaml::parse($skill->getYamlContent());
        $skillDir = $this->publicDir . '/api/v1/skills/' . $skill->getSkillId();

        if (!is_dir($skillDir)) {
            mkdir($skillDir, 0755, true);
        }

        file_put_contents($skillDir . '/skill.yaml', $skill->getYamlContent());

        $info = $this->buildSkillInfo($skill, $data);
        file_put_contents(
            $skillDir . '/info.json',
            json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if ($skill->getIconPath() && file_exists($skill->getIconPath())) {
            copy($skill->getIconPath(), $skillDir . '/icon.png');
        }
    }

    private function buildIndexEntry(Skill $skill): array
    {
        $data = Yaml::parse($skill->getYamlContent());
        $actions = $data['actions'] ?? [];

        $exampleCount = 0;
        foreach ($actions as $action) {
            $exampleCount += count($action['examples'] ?? []);
        }

        return [
            'skill_id' => $skill->getSkillId(),
            'name' => $data['name'] ?? '',
            'version' => $data['version'] ?? '',
            'description' => $data['description'] ?? '',
            'author' => $data['author'] ?? '',
            'author_url' => $data['author_url'] ?? null,
            'action_count' => count($actions),
            'example_count' => $exampleCount,
            'tags' => $skill->getTags(),
            'languages' => $data['languages'] ?? [],
            'requires_bridge_shortcut' => isset($data['bridge_shortcut']),
            'bridge_shortcut_share_url' => $data['bridge_shortcut']['share_url'] ?? null,
            'download_url' => '/api/v1/skills/' . $skill->getSkillId() . '/skill.yaml',
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
            'name' => $data['name'] ?? '',
            'version' => $data['version'] ?? '',
            'description' => $data['description'] ?? '',
            'author' => $data['author'] ?? '',
            'author_url' => $data['author_url'] ?? null,
            'tags' => $skill->getTags(),
            'languages' => $data['languages'] ?? [],
            'requires_bridge_shortcut' => isset($data['bridge_shortcut']),
            'bridge_shortcut_name' => $data['bridge_shortcut']['name'] ?? null,
            'bridge_shortcut_share_url' => $data['bridge_shortcut']['share_url'] ?? null,
            'actions' => $actions,
            'created_at' => $skill->getCreatedAt()->format('c'),
            'updated_at' => $skill->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * @param Skill[] $publishedSkills
     */
    private function cleanupRemovedSkills(array $publishedSkills): void
    {
        $skillsDir = $this->publicDir . '/api/v1/skills';
        if (!is_dir($skillsDir)) {
            return;
        }

        $publishedIds = array_map(fn(Skill $s) => $s->getSkillId(), $publishedSkills);

        foreach (new \DirectoryIterator($skillsDir) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }
            if (!in_array($item->getFilename(), $publishedIds, true)) {
                $this->removeDirectory($item->getPathname());
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        foreach (new \DirectoryIterator($path) as $item) {
            if ($item->isDot()) continue;
            if ($item->isDir()) {
                $this->removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
```

**Step 4: Register service with publicDir parameter**

Add to `backend/config/services.yaml` under `services:`:
```yaml
    App\Service\SkillCompiler:
        arguments:
            $publicDir: '%kernel.project_dir%/public'
```

**Step 5: Create CompileCommand**

Create `backend/src/Command/CompileCommand.php`:
```php
<?php

namespace App\Command;

use App\Service\SkillCompiler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compile',
    description: 'Compile all published skills into static API files',
)]
class CompileCommand extends Command
{
    public function __construct(
        private SkillCompiler $compiler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->compiler->compile();

        $io->success('Skills compiled to public/api/v1/');

        return Command::SUCCESS;
    }
}
```

**Step 6: Run tests to verify they pass**

Run:
```bash
docker compose exec php bin/phpunit tests/Service/SkillCompilerTest.php
```

Expected: all 5 tests PASS.

**Step 7: Commit**

```bash
git add backend/src/Service/SkillCompiler.php backend/src/Command/CompileCommand.php backend/tests/Service/SkillCompilerTest.php backend/config/services.yaml
git commit -m "feat: add skill compiler with static file generation"
```

---

### Task 6: Skill CRUD

**Files:**
- Create: `backend/src/Controller/Admin/SkillController.php`
- Create: `backend/src/Form/SkillType.php`
- Create: `backend/src/Form/DataTransformer/TagsTransformer.php`
- Create: `backend/tests/Controller/Admin/SkillControllerTest.php`

**Step 1: Write functional tests**

Create `backend/tests/Controller/Admin/SkillControllerTest.php`:
```php
<?php

namespace App\Tests\Controller\Admin;

use App\Entity\AdminUser;
use App\Entity\Skill;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SkillControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Create and login as admin
        $admin = new AdminUser();
        $admin->setEmail('test@test.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin->setPassword($hasher->hashPassword($admin, 'password'));
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);
    }

    protected function tearDown(): void
    {
        // Clean up database
        $this->em->createQuery('DELETE FROM App\Entity\Skill')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\AdminUser')->execute();
        parent::tearDown();
    }

    public function testSkillListPageLoads(): void
    {
        $this->client->request('GET', '/admin/skills');
        $this->assertResponseIsSuccessful();
    }

    public function testCreateSkillWithValidYaml(): void
    {
        $yamlPath = __DIR__ . '/../../fixtures/valid-skill.yaml';
        $uploadedFile = new UploadedFile($yamlPath, 'skill.yaml', 'text/yaml', null, true);

        $this->client->request('GET', '/admin/skills/new');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'skill[yamlFile]' => $uploadedFile,
            'skill[tags]' => 'automotive, smart-home',
        ]);

        $this->assertResponseRedirects('/admin/skills');

        $skill = $this->em->getRepository(Skill::class)->findOneBy(['skillId' => 'tesla']);
        $this->assertNotNull($skill);
        $this->assertSame(['automotive', 'smart-home'], $skill->getTags());
    }

    public function testCreateSkillWithInvalidYamlShowsErrors(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'yaml');
        file_put_contents($tmpFile, "skill_id: broken\nname: Broken");
        $uploadedFile = new UploadedFile($tmpFile, 'skill.yaml', 'text/yaml', null, true);

        $this->client->request('GET', '/admin/skills/new');
        $this->client->submitForm('Save', [
            'skill[yamlFile]' => $uploadedFile,
        ]);

        // Should stay on the form page with errors
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testPublishAndUnpublishSkill(): void
    {
        $skill = new Skill();
        $skill->setSkillId('test');
        $skill->setYamlContent(file_get_contents(__DIR__ . '/../../fixtures/valid-skill.yaml'));
        $this->em->persist($skill);
        $this->em->flush();

        // Publish
        $this->client->request('POST', '/admin/skills/' . $skill->getId() . '/publish');
        $this->assertResponseRedirects('/admin/skills');

        $this->em->refresh($skill);
        $this->assertNotNull($skill->getPublishedAt());

        // Unpublish
        $this->client->request('POST', '/admin/skills/' . $skill->getId() . '/unpublish');
        $this->assertResponseRedirects('/admin/skills');

        $this->em->refresh($skill);
        $this->assertNull($skill->getPublishedAt());
    }

    public function testDeleteSkill(): void
    {
        $skill = new Skill();
        $skill->setSkillId('deleteme');
        $skill->setYamlContent(file_get_contents(__DIR__ . '/../../fixtures/valid-skill.yaml'));
        $this->em->persist($skill);
        $this->em->flush();
        $id = $skill->getId();

        $this->client->request('POST', '/admin/skills/' . $id . '/delete', [
            '_token' => static::getContainer()->get('security.csrf.token_manager')
                ->getToken('delete' . $id)->getValue(),
        ]);

        $this->assertResponseRedirects('/admin/skills');
        $this->assertNull($this->em->getRepository(Skill::class)->find($id));
    }
}
```

**Step 2: Run tests to verify they fail**

Run:
```bash
docker compose exec php bin/phpunit tests/Controller/Admin/SkillControllerTest.php
```

Expected: FAIL — controller/routes/templates don't exist yet.

**Step 3: Create TagsTransformer**

Create `backend/src/Form/DataTransformer/TagsTransformer.php`:
```php
<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * Transforms between array of tags and comma-separated string.
 *
 * @implements DataTransformerInterface<array, string>
 */
class TagsTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if (!is_array($value)) {
            return '';
        }

        return implode(', ', $value);
    }

    public function reverseTransform(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
```

**Step 4: Create SkillType form**

Create `backend/src/Form/SkillType.php`:
```php
<?php

namespace App\Form;

use App\Entity\Skill;
use App\Form\DataTransformer\TagsTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class SkillType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('yamlFile', FileType::class, [
                'label' => 'YAML File',
                'mapped' => false,
                'required' => $options['is_create'],
                'constraints' => [
                    new File(
                        mimeTypes: ['text/yaml', 'text/plain', 'application/x-yaml', 'application/yaml'],
                        mimeTypesMessage: 'Please upload a valid YAML file',
                    ),
                ],
            ])
            ->add('iconFile', FileType::class, [
                'label' => 'Icon (256x256 PNG)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        mimeTypes: ['image/png'],
                        mimeTypesMessage: 'Please upload a PNG image',
                    ),
                ],
            ])
            ->add('tags', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'automotive, smart-home, productivity'],
            ]);

        $builder->get('tags')->addModelTransformer(new TagsTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Skill::class,
            'is_create' => true,
        ]);
    }
}
```

**Step 5: Create SkillController**

Create `backend/src/Controller/Admin/SkillController.php`:
```php
<?php

namespace App\Controller\Admin;

use App\Entity\Skill;
use App\Form\SkillType;
use App\Repository\SkillRepository;
use App\Service\SkillCompiler;
use App\Service\SkillYamlValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class SkillController extends AbstractController
{
    #[Route('/skills', name: 'app_admin_skill_list')]
    public function list(SkillRepository $skillRepository): Response
    {
        return $this->render('admin/skill/list.html.twig', [
            'skills' => $skillRepository->findBy([], ['skillId' => 'ASC']),
        ]);
    }

    #[Route('/skills/new', name: 'app_admin_skill_create')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SkillYamlValidator $validator,
    ): Response {
        $skill = new Skill();
        $form = $this->createForm(SkillType::class, $skill, ['is_create' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $yamlFile = $form->get('yamlFile')->getData();
            if ($yamlFile) {
                $yamlContent = file_get_contents($yamlFile->getPathname());
                $errors = $validator->validate($yamlContent);

                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $this->addFlash('error', $error);
                    }
                    return $this->render('admin/skill/form.html.twig', [
                        'form' => $form,
                        'skill' => null,
                    ]);
                }

                $skill->setYamlContent($yamlContent);
                $skill->setSkillId($validator->extractSkillId($yamlContent));
            }

            $this->handleIconUpload($form, $skill);

            $em->persist($skill);
            $em->flush();

            $this->addFlash('success', 'Skill created.');
            return $this->redirectToRoute('app_admin_skill_list');
        }

        return $this->render('admin/skill/form.html.twig', [
            'form' => $form,
            'skill' => null,
        ]);
    }

    #[Route('/skills/{id}/edit', name: 'app_admin_skill_edit')]
    public function edit(
        Skill $skill,
        Request $request,
        EntityManagerInterface $em,
        SkillYamlValidator $validator,
    ): Response {
        $form = $this->createForm(SkillType::class, $skill, ['is_create' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $yamlFile = $form->get('yamlFile')->getData();
            if ($yamlFile) {
                $yamlContent = file_get_contents($yamlFile->getPathname());
                $errors = $validator->validate($yamlContent);

                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $this->addFlash('error', $error);
                    }
                    return $this->render('admin/skill/form.html.twig', [
                        'form' => $form,
                        'skill' => $skill,
                    ]);
                }

                $skill->setYamlContent($yamlContent);
                $skill->setSkillId($validator->extractSkillId($yamlContent));
            }

            $this->handleIconUpload($form, $skill);

            $em->flush();

            $this->addFlash('success', 'Skill updated.');
            return $this->redirectToRoute('app_admin_skill_list');
        }

        return $this->render('admin/skill/form.html.twig', [
            'form' => $form,
            'skill' => $skill,
        ]);
    }

    #[Route('/skills/{id}/delete', name: 'app_admin_skill_delete', methods: ['POST'])]
    public function delete(
        Skill $skill,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $skill->getId(), $request->request->get('_token'))) {
            $em->remove($skill);
            $em->flush();
            $this->addFlash('success', 'Skill deleted.');
        }

        return $this->redirectToRoute('app_admin_skill_list');
    }

    #[Route('/skills/{id}/publish', name: 'app_admin_skill_publish', methods: ['POST'])]
    public function publish(Skill $skill, EntityManagerInterface $em): Response
    {
        $skill->setPublishedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', "Skill '{$skill->getSkillId()}' published.");
        return $this->redirectToRoute('app_admin_skill_list');
    }

    #[Route('/skills/{id}/unpublish', name: 'app_admin_skill_unpublish', methods: ['POST'])]
    public function unpublish(Skill $skill, EntityManagerInterface $em): Response
    {
        $skill->setPublishedAt(null);
        $em->flush();

        $this->addFlash('success', "Skill '{$skill->getSkillId()}' unpublished.");
        return $this->redirectToRoute('app_admin_skill_list');
    }

    #[Route('/compile', name: 'app_admin_compile', methods: ['POST'])]
    public function compile(SkillCompiler $compiler): Response
    {
        $compiler->compile();

        $this->addFlash('success', 'Skills compiled successfully.');
        return $this->redirectToRoute('app_admin_skill_list');
    }

    private function handleIconUpload($form, Skill $skill): void
    {
        $iconFile = $form->get('iconFile')->getData();
        if ($iconFile) {
            $iconsDir = $this->getParameter('kernel.project_dir') . '/var/icons';
            if (!is_dir($iconsDir)) {
                mkdir($iconsDir, 0755, true);
            }
            $iconPath = $iconsDir . '/' . $skill->getSkillId() . '.png';
            $iconFile->move($iconsDir, $skill->getSkillId() . '.png');
            $skill->setIconPath($iconPath);
        }
    }
}
```

**Step 6: Create templates**

Create `backend/templates/base.html.twig`:
```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}OpenDispatch Admin{% endblock %}</title>
    {% block stylesheets %}{% endblock %}
</head>
<body>
    {% block body %}{% endblock %}
    {% block javascripts %}{% endblock %}
</body>
</html>
```

Create `backend/templates/admin/skill/list.html.twig`:
```twig
{% extends 'base.html.twig' %}

{% block title %}Skills — OpenDispatch Admin{% endblock %}

{% block body %}
<h1>Skills</h1>

{% for flash in app.flashes('success') %}
    <div class="alert alert-success">{{ flash }}</div>
{% endfor %}
{% for flash in app.flashes('error') %}
    <div class="alert alert-danger">{{ flash }}</div>
{% endfor %}

<a href="{{ path('app_admin_skill_create') }}">Add Skill</a>

<form method="post" action="{{ path('app_admin_compile') }}" style="display:inline">
    <button type="submit">Compile</button>
</form>

<table>
    <thead>
        <tr>
            <th>Skill ID</th>
            <th>Tags</th>
            <th>Published</th>
            <th>Updated</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        {% for skill in skills %}
        <tr>
            <td>{{ skill.skillId }}</td>
            <td>{{ skill.tags|join(', ') }}</td>
            <td>{{ skill.publishedAt ? skill.publishedAt|date('Y-m-d H:i') : '—' }}</td>
            <td>{{ skill.updatedAt|date('Y-m-d H:i') }}</td>
            <td>
                <a href="{{ path('app_admin_skill_edit', {id: skill.id}) }}">Edit</a>

                {% if skill.published %}
                    <form method="post" action="{{ path('app_admin_skill_unpublish', {id: skill.id}) }}" style="display:inline">
                        <button type="submit">Unpublish</button>
                    </form>
                {% else %}
                    <form method="post" action="{{ path('app_admin_skill_publish', {id: skill.id}) }}" style="display:inline">
                        <button type="submit">Publish</button>
                    </form>
                {% endif %}

                <form method="post" action="{{ path('app_admin_skill_delete', {id: skill.id}) }}" style="display:inline"
                      onsubmit="return confirm('Delete this skill?')">
                    <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ skill.id) }}">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
        {% else %}
        <tr>
            <td colspan="5">No skills yet.</td>
        </tr>
        {% endfor %}
    </tbody>
</table>
{% endblock %}
```

Create `backend/templates/admin/skill/form.html.twig`:
```twig
{% extends 'base.html.twig' %}

{% block title %}{{ skill ? 'Edit' : 'New' }} Skill — OpenDispatch Admin{% endblock %}

{% block body %}
<h1>{{ skill ? 'Edit Skill: ' ~ skill.skillId : 'New Skill' }}</h1>

{% for flash in app.flashes('error') %}
    <div class="alert alert-danger">{{ flash }}</div>
{% endfor %}

{{ form_start(form) }}
    {{ form_row(form.yamlFile) }}
    {{ form_row(form.iconFile) }}
    {{ form_row(form.tags) }}
    <button type="submit">Save</button>
{{ form_end(form) }}

<a href="{{ path('app_admin_skill_list') }}">Back to list</a>

{% if skill %}
    <h2>Current YAML</h2>
    <pre>{{ skill.yamlContent }}</pre>
{% endif %}
{% endblock %}
```

**Step 7: Configure test environment**

Create `backend/.env.test`:
```
DATABASE_URL="postgresql://opendispatch:opendispatch@postgres:5432/opendispatch_test?serverVersion=17&charset=utf8"
```

Create test database:
```bash
docker compose exec php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

**Step 8: Run tests to verify they pass**

Run:
```bash
docker compose exec php bin/phpunit tests/Controller/Admin/SkillControllerTest.php
```

Expected: all 5 tests PASS.

**Step 9: Commit**

```bash
git add backend/src/Controller/Admin/SkillController.php backend/src/Form/ backend/templates/ backend/tests/Controller/ backend/.env.test
git commit -m "feat: add skill CRUD with validation, publish/unpublish, and compile"
```

---

### Task 7: Admin UI with Symfony UX

**Files:**
- Modify: `backend/templates/base.html.twig`
- Modify: `backend/templates/admin/login.html.twig`
- Modify: `backend/templates/admin/skill/list.html.twig`
- Modify: `backend/templates/admin/skill/form.html.twig`
- Create: `backend/assets/app.js`
- Create: `backend/assets/styles/app.css`
- Create: `backend/assets/controllers/confirm_controller.js`

This task replaces the plain HTML templates from Task 6 with styled, interactive versions using Symfony UX. The templates in Task 6 are functional scaffolding — this task is the UI polish.

**Step 1: Configure AssetMapper**

Run:
```bash
docker compose exec php bin/console importmap:install
```

Verify `backend/importmap.php` exists and includes `@hotwired/stimulus` and `@hotwired/turbo`.

**Step 2: Create base CSS**

Create `backend/assets/styles/app.css` with your preferred admin styling. Start minimal — a clean, readable layout:

```css
:root {
    --color-bg: #f8f9fa;
    --color-surface: #ffffff;
    --color-primary: #2563eb;
    --color-danger: #dc2626;
    --color-success: #16a34a;
    --color-text: #1f2937;
    --color-muted: #6b7280;
    --color-border: #e5e7eb;
    --radius: 8px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--color-bg);
    color: var(--color-text);
    line-height: 1.5;
}

.container { max-width: 960px; margin: 0 auto; padding: 2rem 1rem; }

nav {
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border);
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
nav a { color: var(--color-primary); text-decoration: none; font-weight: 500; }

h1 { margin-bottom: 1.5rem; }

.alert {
    padding: 0.75rem 1rem;
    border-radius: var(--radius);
    margin-bottom: 1rem;
}
.alert-success { background: #dcfce7; color: var(--color-success); }
.alert-danger { background: #fee2e2; color: var(--color-danger); }

table { width: 100%; border-collapse: collapse; background: var(--color-surface); border-radius: var(--radius); overflow: hidden; }
th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--color-border); }
th { background: var(--color-bg); font-weight: 600; font-size: 0.875rem; color: var(--color-muted); }

.btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
}
.btn-primary { background: var(--color-primary); color: white; }
.btn-danger { background: var(--color-danger); color: white; }
.btn-success { background: var(--color-success); color: white; }
.btn-outline { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }

.actions { display: flex; gap: 0.5rem; align-items: center; }
.actions form { display: inline; }

.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.875rem; }
.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"],
.form-group input[type="file"],
.form-group textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius);
    font-size: 0.875rem;
}

pre {
    background: var(--color-bg);
    padding: 1rem;
    border-radius: var(--radius);
    overflow-x: auto;
    font-size: 0.8125rem;
    border: 1px solid var(--color-border);
}

.toolbar { display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: center; }

.badge {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
}
.badge-success { background: #dcfce7; color: var(--color-success); border-color: var(--color-success); }

.login-container {
    max-width: 400px;
    margin: 4rem auto;
    background: var(--color-surface);
    padding: 2rem;
    border-radius: var(--radius);
    border: 1px solid var(--color-border);
}
.login-container h1 { text-align: center; margin-bottom: 2rem; }
```

**Step 3: Create app.js entry point**

Create `backend/assets/app.js`:
```js
import './styles/app.css';
```

**Step 4: Create a Stimulus confirm controller**

Create `backend/assets/controllers/confirm_controller.js`:
```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { message: { type: String, default: 'Are you sure?' } };

    confirm(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
```

**Step 5: Update base.html.twig with AssetMapper**

Replace `backend/templates/base.html.twig`:
```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}OpenDispatch Admin{% endblock %}</title>
    {% block stylesheets %}{% endblock %}
    {% block importmap %}{{ importmap('app') }}{% endblock %}
</head>
<body>
    {% if app.user %}
    <nav>
        <a href="{{ path('app_admin_skill_list') }}"><strong>OpenDispatch</strong></a>
        <div>
            <span>{{ app.user.email }}</span>
            <a href="{{ path('app_logout') }}">Logout</a>
        </div>
    </nav>
    {% endif %}

    <div class="container">
        {% block body %}{% endblock %}
    </div>

    {% block javascripts %}{% endblock %}
</body>
</html>
```

**Step 6: Update login template**

Replace `backend/templates/admin/login.html.twig`:
```twig
{% extends 'base.html.twig' %}

{% block title %}Login — OpenDispatch Admin{% endblock %}

{% block body %}
<div class="login-container">
    <h1>OpenDispatch</h1>

    {% if error %}
        <div class="alert alert-danger">{{ error.messageKey|trans(error.messageData, 'security') }}</div>
    {% endif %}

    <form method="post">
        <div class="form-group">
            <label for="username">Email</label>
            <input type="email" id="username" name="_username" value="{{ last_username }}" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="_password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Log in</button>
    </form>
</div>
{% endblock %}
```

**Step 7: Update skill list template with Turbo and styled components**

Replace `backend/templates/admin/skill/list.html.twig`:
```twig
{% extends 'base.html.twig' %}

{% block title %}Skills — OpenDispatch Admin{% endblock %}

{% block body %}
<h1>Skills</h1>

{% for flash in app.flashes('success') %}
    <div class="alert alert-success">{{ flash }}</div>
{% endfor %}

<div class="toolbar">
    <a href="{{ path('app_admin_skill_create') }}" class="btn btn-primary">Add Skill</a>
    <form method="post" action="{{ path('app_admin_compile') }}">
        <button type="submit" class="btn btn-outline">Compile &amp; Publish</button>
    </form>
</div>

<turbo-frame id="skill-list">
<table>
    <thead>
        <tr>
            <th>Skill ID</th>
            <th>Tags</th>
            <th>Status</th>
            <th>Updated</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        {% for skill in skills %}
        <tr>
            <td><strong>{{ skill.skillId }}</strong></td>
            <td>
                {% for tag in skill.tags %}
                    <span class="badge">{{ tag }}</span>
                {% endfor %}
            </td>
            <td>
                {% if skill.published %}
                    <span class="badge badge-success">Published</span>
                {% else %}
                    <span class="badge">Draft</span>
                {% endif %}
            </td>
            <td>{{ skill.updatedAt|date('Y-m-d H:i') }}</td>
            <td>
                <div class="actions">
                    <a href="{{ path('app_admin_skill_edit', {id: skill.id}) }}" class="btn btn-outline btn-sm">Edit</a>

                    {% if skill.published %}
                        <form method="post" action="{{ path('app_admin_skill_unpublish', {id: skill.id}) }}">
                            <button type="submit" class="btn btn-outline btn-sm">Unpublish</button>
                        </form>
                    {% else %}
                        <form method="post" action="{{ path('app_admin_skill_publish', {id: skill.id}) }}">
                            <button type="submit" class="btn btn-success btn-sm">Publish</button>
                        </form>
                    {% endif %}

                    <form method="post" action="{{ path('app_admin_skill_delete', {id: skill.id}) }}"
                          data-controller="confirm"
                          data-confirm-message-value="Delete skill '{{ skill.skillId }}'?"
                          data-action="submit->confirm#confirm">
                        <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ skill.id) }}">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        {% else %}
        <tr>
            <td colspan="5" style="text-align:center;color:var(--color-muted)">No skills yet. Upload your first one.</td>
        </tr>
        {% endfor %}
    </tbody>
</table>
</turbo-frame>
{% endblock %}
```

**Step 8: Update skill form template**

Replace `backend/templates/admin/skill/form.html.twig`:
```twig
{% extends 'base.html.twig' %}

{% block title %}{{ skill ? 'Edit' : 'New' }} Skill — OpenDispatch Admin{% endblock %}

{% block body %}
<h1>{{ skill ? 'Edit Skill: ' ~ skill.skillId : 'New Skill' }}</h1>

{% for flash in app.flashes('error') %}
    <div class="alert alert-danger">{{ flash }}</div>
{% endfor %}

{{ form_start(form) }}
    <div class="form-group">
        {{ form_label(form.yamlFile) }}
        {{ form_widget(form.yamlFile) }}
        {{ form_errors(form.yamlFile) }}
    </div>
    <div class="form-group">
        {{ form_label(form.iconFile) }}
        {{ form_widget(form.iconFile) }}
        {{ form_errors(form.iconFile) }}
    </div>
    <div class="form-group">
        {{ form_label(form.tags) }}
        {{ form_widget(form.tags) }}
        {{ form_errors(form.tags) }}
    </div>
    <div class="toolbar">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ path('app_admin_skill_list') }}" class="btn btn-outline">Cancel</a>
    </div>
{{ form_end(form) }}

{% if skill %}
    <h2 style="margin-top:2rem">Current YAML</h2>
    <pre>{{ skill.yamlContent }}</pre>
{% endif %}
{% endblock %}
```

**Step 9: Verify in browser**

Open `http://localhost:8080/admin/login`, log in, verify:
- Styled login page
- Skill list with toolbar, table, action buttons
- Create/edit form with file uploads
- Compile button works
- Delete confirmation uses Stimulus controller

**Step 10: Run all tests**

Run:
```bash
docker compose exec php bin/phpunit
```

Expected: all tests pass.

**Step 11: Commit**

```bash
git add backend/assets/ backend/templates/ backend/importmap.php
git commit -m "feat: add admin UI with Symfony UX (Stimulus, Turbo, AssetMapper)"
```

---

## Summary

| Task | Description | Key Deliverable |
|------|-------------|-----------------|
| 0 | Scaffold Symfony project | Working Symfony 8.0 app with all dependencies |
| 1 | Docker setup | Nginx + PHP-FPM + PostgreSQL running locally |
| 2 | Entities & migrations | Skill + AdminUser tables in PostgreSQL |
| 3 | Authentication | Form login, admin user creation command |
| 4 | YAML Validation (TDD) | SkillYamlValidator with 11 unit tests |
| 5 | Compile Service (TDD) | SkillCompiler with 5 unit tests + CLI command |
| 6 | Skill CRUD | Full admin CRUD with 5 functional tests |
| 7 | Admin UI | Styled templates with Stimulus + Turbo |
