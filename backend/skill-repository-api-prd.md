# OpenDispatch Skill Repository API — PRD

Version: 0.1
Status: Draft

## Overview

A REST API serving a browseable catalog of OpenDispatch skills. The app fetches a skill index, displays available skills, and downloads individual skill YAML files for local compilation.

Built with Symfony (PHP). Designed for simplicity — the API is primarily a static catalog with cached JSON responses.

## Core Endpoints

### `GET /api/v1/index.json`

The main catalog endpoint. Returns all available skills with metadata.

Cached aggressively — regenerated only when skills are added, updated, or removed.

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
      "description": "Control your Tesla — lock, unlock, climate, charging, sentry mode, windows",
      "author": "opendispatch",
      "author_url": "https://opendispatch.ai",
      "action_count": 16,
      "example_count": 66,
      "categories": ["automotive", "smart-home"],
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

Response headers:
- `Cache-Control: public, max-age=300` (5 minutes)
- `ETag` for conditional requests
- `Last-Modified` for conditional requests

### `GET /api/v1/skills/{skill_id}/skill.yaml`

Download the raw YAML skill file. This is what the app saves locally and compiles.

Response headers:
- `Content-Type: text/yaml`
- `Content-Disposition: attachment; filename="skill.yaml"`
- `ETag` based on skill version

### `GET /api/v1/skills/{skill_id}/icon.png`

Optional skill icon (256x256, PNG). Used in the app's skill browser.

### `GET /api/v1/skills/{skill_id}/info.json`

Detailed skill metadata, including full action list and example counts per action. Used for the skill detail view before installing.

```json
{
  "skill_id": "tesla",
  "name": "Tesla",
  "version": "1.0.0",
  "description": "Control your Tesla — lock, unlock, climate, charging, sentry mode, windows",
  "author": "opendispatch",
  "author_url": "https://opendispatch.ai",
  "categories": ["automotive", "smart-home"],
  "languages": ["en"],
  "requires_bridge_shortcut": true,
  "bridge_shortcut_name": "OpenDispatch - Tesla V1",
  "bridge_shortcut_share_url": "https://www.icloud.com/shortcuts/abc123",
  "actions": [
    {
      "id": "vehicle.unlock",
      "title": "Unlock",
      "description": "Unlock the car doors so you can get in",
      "example_count": 5,
      "has_parameters": false,
      "confirmation": "none"
    },
    {
      "id": "vehicle.climate.set_temperature",
      "title": "Set Temperature",
      "description": "Set the Tesla cabin temperature",
      "example_count": 4,
      "has_parameters": true,
      "confirmation": null
    }
  ],
  "created_at": "2026-03-18T10:00:00Z",
  "updated_at": "2026-03-18T10:00:00Z"
}
```

### `GET /api/v1/categories.json`

List of available categories for filtering.

```json
{
  "categories": [
    {"id": "automotive", "name": "Automotive", "skill_count": 3},
    {"id": "smart-home", "name": "Smart Home", "skill_count": 8},
    {"id": "productivity", "name": "Productivity", "skill_count": 12},
    {"id": "health", "name": "Health & Fitness", "skill_count": 5}
  ]
}
```

### `GET /api/v1/index.json?category={category_id}`

Filtered index by category.

### `GET /api/v1/index.json?search={query}`

Full-text search across skill names, descriptions, and action titles.

### `GET /api/v1/index.json?updated_since={ISO8601}`

Incremental updates — returns only skills updated since the given timestamp. The app stores the last fetch timestamp and uses this to avoid re-downloading the full index.

## Versioning

### API versioning

All endpoints are prefixed with `/api/v1/`. Future breaking changes go to `/api/v2/`.

Non-breaking additions (new fields in JSON) are added to the current version without bumping.

Breaking changes that require a version bump:
- Removing or renaming fields
- Changing the structure of existing fields
- Changing the YAML skill format in ways that require app updates

### Skill versioning

Each skill has a `version` field (semver). When a skill is updated:
- The `version` field is bumped
- The `updated_at` timestamp changes
- The `download_url` always points to the latest version
- Previous versions are not retained (the app always gets the latest)

### Client version negotiation

The app sends its version in the request:

```
GET /api/v1/index.json
User-Agent: OpenDispatch/1.0.0 (iOS 26.0)
X-OpenDispatch-Version: 1.0.0
```

The server can use this to:
- Filter out skills that require a newer app version (`min_app_version` field)
- Return version-appropriate responses

## Data Model (Symfony)

### Entities

**Skill**
- `id` (UUID)
- `skill_id` (string, unique) — matches the YAML `skill_id`
- `name` (string)
- `version` (string, semver)
- `description` (text)
- `author` (string)
- `author_url` (string, nullable)
- `yaml_content` (text) — the raw YAML file
- `icon_path` (string, nullable)
- `categories` (ManyToMany → Category)
- `requires_bridge_shortcut` (boolean)
- `bridge_shortcut_name` (string, nullable)
- `bridge_shortcut_share_url` (string, nullable)
- `action_count` (integer) — computed on save
- `example_count` (integer) — computed on save
- `languages` (json array)
- `min_app_version` (string, nullable)
- `is_published` (boolean)
- `created_at` (datetime)
- `updated_at` (datetime)

**Category**
- `id` (UUID)
- `slug` (string, unique)
- `name` (string)
- `sort_order` (integer)

### Validation on skill upload

When a YAML file is uploaded, the server validates:
- Valid YAML syntax
- `skill_id` is present and non-empty
- `name` is present
- `version` is valid semver
- `actions` is non-empty
- Each action has `id` and at least one `examples` entry
- Capability IDs match the pattern `word.word` (underscores allowed)
- If `bridge_shortcut` is set, `bridge_shortcut_share_url` should be present

Validation errors are returned to the admin, the skill is not published until valid.

## Caching Strategy

### Server-side

- `index.json` is pre-built and cached to filesystem (or Redis) on any skill change
- Invalidation: regenerate cache on skill create, update, delete, publish, unpublish
- `info.json` per skill is cached individually
- YAML files are served directly from storage (no processing)

### Client-side

The app should:
- Cache `index.json` locally with the `ETag` / `Last-Modified` headers
- Use `If-None-Match` / `If-Modified-Since` for conditional fetches
- Store the `updated_since` timestamp to request incremental updates
- Cache downloaded `skill.yaml` files — they don't change unless the version bumps

## Admin Interface

A simple Symfony admin (EasyAdmin or custom) for:

- Upload new skill (YAML file + optional icon)
- Edit skill metadata (description, categories, author)
- Validate YAML on upload
- Publish / unpublish skills
- View download stats (optional)
- Manage categories
- Force cache regeneration

### Skill submission flow (future)

For community submissions:
1. User submits YAML via form or API
2. Skill enters "pending review" state
3. Admin reviews and approves
4. Skill is published and appears in the index

## Security

- All endpoints are read-only (no authentication required for GET)
- Admin endpoints require authentication (Symfony security)
- YAML files are validated on upload to prevent injection
- Rate limiting on API endpoints (Symfony rate limiter)
- CORS headers for web-based skill browsers

## Deployment

- Standard Symfony deployment (PHP 8.3+, PostgreSQL or MySQL)
- Nginx or Caddy as reverse proxy
- YAML files and icons stored on filesystem or S3
- `skills.opendispatch.ai` as the domain
- SSL via Let's Encrypt or Cloudflare

## Future Considerations

- Skill ratings and reviews
- Download/install counts
- Author accounts and self-service publishing
- Skill update notifications (push to app)
- Skill bundles ("Smart Home Starter Pack")
- Webhooks for CI/CD integration (auto-publish from GitHub repo)
- Localized skill descriptions
- Skill dependencies (skill A requires skill B)
