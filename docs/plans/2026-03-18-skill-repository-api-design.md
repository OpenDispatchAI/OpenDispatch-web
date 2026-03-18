# OpenDispatch Skill Repository API — Design

Status: Approved
Date: 2026-03-18

## Overview

A Symfony 8.0 (PHP 8.5) backend that manages a catalog of OpenDispatch skills. The admin uploads YAML skill files, validates them, and compiles a set of static JSON/YAML files served directly by Nginx. The Symfony app only handles the admin interface — the public API is entirely static files.

## Architecture

```
┌─────────────────────────────────────────────────┐
│ Nginx                                           │
│  ├─ /api/v1/*  → serves from public/api/v1/    │
│  │              (static files, no PHP)          │
│  └─ /admin/*   → proxy to PHP-FPM              │
│                  (dynamic, Symfony handles)      │
└─────────────────────────────────────────────────┘
         │                        │
    Static files            Symfony App
    (compiled)              (admin only)
         │                        │
  public/api/v1/            PostgreSQL
  ├─ index.json
  └─ skills/
     └─ {skill_id}/
        ├─ skill.yaml
        ├─ icon.png
        └─ info.json
```

The public API is entirely static. Nginx serves compiled files from `public/api/v1/`. PHP is never involved in serving API requests.

## Tech Stack

| Component | Choice |
|-----------|--------|
| Framework | Symfony 8.0 |
| Language | PHP 8.5 |
| Database | PostgreSQL |
| Admin frontend | Twig + Symfony UX (Stimulus, Turbo, Twig Components, Live Components) |
| Web server | Nginx + PHP-FPM |
| Containerization | Docker Compose |

## Data Model

### Skill Entity

Minimal — the YAML is the source of truth for skill metadata.

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | Primary key |
| `skillId` | string (unique) | Extracted from YAML on upload |
| `yamlContent` | text | Raw YAML file content |
| `iconPath` | string, nullable | Path to uploaded icon file |
| `tags` | json array | Managed by admin, e.g. `["automotive", "smart-home"]` |
| `publishedAt` | datetime, nullable | `null` = unpublished, timestamp = published |
| `createdAt` | datetime | |
| `updatedAt` | datetime | |

All other metadata (name, version, description, author, action count, etc.) is parsed from `yamlContent` during the compile step and written into the static JSON files.

### Admin User Entity

| Field | Type |
|-------|------|
| `id` | UUID |
| `email` | string (unique) |
| `password` | string (hashed) |
| `roles` | json array |

Single admin user, seeded via console command.

## Static API Files

All served by Nginx from `public/api/v1/`.

### `index.json`

Full skill catalog. The app fetches this and handles filtering/search client-side.

```json
{
  "version": 1,
  "generated_at": "2026-03-18T12:00:00Z",
  "skill_count": 42,
  "skills": [
    {
      "skill_id": "tesla",
      "name": "Tesla",
      "version": "1.0.0",
      "description": "Control your Tesla",
      "author": "opendispatch",
      "author_url": "https://opendispatch.ai",
      "action_count": 16,
      "example_count": 66,
      "tags": ["automotive", "smart-home"],
      "languages": ["en"],
      "requires_bridge_shortcut": true,
      "bridge_shortcut_share_url": "https://www.icloud.com/shortcuts/abc123",
      "download_url": "/api/v1/skills/tesla/skill.yaml",
      "icon_url": "/api/v1/skills/tesla/icon.png",
      "created_at": "2026-03-18T10:00:00Z",
      "updated_at": "2026-03-18T10:00:00Z"
    }
  ]
}
```

### `skills/{skill_id}/info.json`

Detailed skill metadata with full action list.

### `skills/{skill_id}/skill.yaml`

Raw YAML file copied from DB.

### `skills/{skill_id}/icon.png`

Skill icon (256x256, PNG), copied from upload storage.

## Admin Interface

Built with Twig + Symfony UX (Stimulus/Turbo/Twig Components/Live Components).

### Pages

- **Login** — form login
- **Skill list** — table showing name, version, tags, publish status
- **Skill create/edit** — upload YAML, upload icon, manage tags
- **Compile** — button that regenerates all static files

### Upload Flow

1. Admin uploads YAML file
2. Server validates: valid YAML syntax, `skill_id` present, `name` present, valid semver `version`, `actions` non-empty, each action has `id` and `examples`, action IDs match `word.word` pattern
3. Extracts `skillId` from YAML, stores in DB
4. Optionally upload icon
5. Set tags
6. Save (not yet public until `publishedAt` is set)

### Compile Flow

1. Admin triggers compile (button in admin or CLI command)
2. System iterates all skills where `publishedAt` is not null
3. Parses each skill's YAML content
4. Builds `index.json` with metadata extracted from all published skills
5. Builds per-skill `info.json` with action details
6. Copies YAML content to `public/api/v1/skills/{skill_id}/skill.yaml`
7. Copies icons to `public/api/v1/skills/{skill_id}/icon.png`
8. Writes `public/api/v1/index.json`

## Console Commands

- `app:create-admin` — creates the admin user (prompts for email + password)
- `app:compile` — triggers the compile flow from CLI

## Docker Setup

```yaml
services:
  nginx:    # Serves static files + proxies /admin to PHP
  php:      # PHP 8.5-FPM, Symfony app
  postgres: # PostgreSQL database
```

### Nginx Config

- `/api/v1/*` → serve directly from `public/api/v1/` (static files)
- `/admin/*` → proxy to PHP-FPM (Symfony handles)
- Standard Symfony `index.php` fallback for admin routes

## Authentication

- Form login with single admin user
- Admin user created via `app:create-admin` console command
- Symfony security component with standard password hashing
- All `/admin/*` routes require authentication

## Decisions & Trade-offs

| Decision | Rationale |
|----------|-----------|
| Static files over dynamic API | Nginx handles caching natively, PHP not involved in serving |
| YAML in DB, compiled to filesystem | DB is source of truth, static files are the fast serving layer |
| Tags over categories | Simple JSON array, no extra entity, filtering happens in-app |
| No dynamic query params | App fetches full index, handles search/filter client-side |
| Single admin user | Only one person manages the catalog, community submits via GitHub PRs |
| Custom admin over EasyAdmin | Portfolio/CV value, demonstrates full Symfony UX stack |

## Dropped from PRD

- Category entity and `categories.json` endpoint (replaced by tags)
- `?category=`, `?search=`, `?updated_since=` query parameters (filtering in-app)
- Community submission flow (handled via GitHub PRs instead)
- Client version negotiation (can add later if needed)
