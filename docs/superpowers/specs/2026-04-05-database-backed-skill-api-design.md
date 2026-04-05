# Database-Backed Skill API

## Problem

The SkillCompiler writes static files (JSON, YAML, icons, shortcut binaries) to `public/api/`. This directory is gitignored and not shared across Deployer releases, so every deploy wipes the API output until the next sync. Shortcut binaries are especially problematic — they originate from the skills git repo which is only cloned temporarily during sync.

Additionally, the iOS app needs absolute URLs for shortcut files, and serving everything as static files through Nginx provides no error logging or observability.

## Solution

Move all compiled skill data into the database (SQLite in `var/data/`, which is shared across deploys). Serve the API through Symfony controller routes with ETag caching. Keep compilation at sync time so errors surface during sync rather than during live requests.

## Storage

### Skill entity changes

New columns:
- `icon_data` — `TEXT`, nullable. Base64-encoded PNG.
- `shortcut_data` — `TEXT`, nullable. Base64-encoded `.shortcut` binary.
- `compiled_info` — `TEXT`, nullable. Pre-compiled `info.json` content (JSON string).

Removed:
- `icon_path` column (currently stores filesystem path to icon)
- `bridgeShortcutFilePath` transient property (no longer needed)

### New entity: SkillManifest

Insert-only table for versioned manifest history.

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK | Auto-increment |
| content | TEXT | Compiled index.json content |
| commit_sha | VARCHAR(255) | Git commit or label (e.g. `'manual'`) that triggered this manifest |
| created_at | DATETIME | Timestamp of compilation |

The API always serves the most recent row. Old rows provide audit history and rollback capability. Pruning: keep the last 50 manifests, delete older rows during each sync.

## Sync Step Changes

`SkillSyncService` changes:
- Use Symfony Filesystem to read icon and shortcut files, base64 encode, store on Skill entity.
- Remove transient `bridgeShortcutFilePath` and `iconPath` handling.
- `bridge_shortcut_share_url` rewritten as absolute URL using `RouterInterface::getContext()` (already implemented).
- All URLs in compiled JSON (`icon_url`, `download_url`, `bridge_shortcut_share_url`) are absolute, using the same `RouterInterface::getContext()` base URL.

`SkillCompiler` changes:
- `compileSkill()` builds the `info.json` payload (reusing existing `buildSkillInfo()`) and stores it as `compiled_info` on the Skill entity instead of writing to disk.
- `compileIndex()` builds the `index.json` payload (reusing existing `buildIndexEntry()`) and inserts a new `SkillManifest` row instead of writing to disk. `icon_url` presence is derived from `icon_data` being non-null (replacing the old `iconPath` check).
- All filesystem writes removed: no more `file_put_contents`, no more directory creation in `public/`, no more `cleanupRemovedSkills()`.
- Constructor changes: replace `$publicDir` with `EntityManagerInterface` and `SkillManifestRepository`.
- Manifest insert and skill upserts happen in the same `flush()` call for atomic consistency.

## API Routes

### New controller: SkillApiController

Catalog routes (cacheable, ETag-backed):

| Route | Response | ETag source |
|-------|----------|-------------|
| `GET /api/v1/index.json` | Latest SkillManifest content | `manifest.createdAt` |
| `GET /api/v1/skills/{skillId}/info.json` | Skill `compiled_info` | `skill.updatedAt` |
| `GET /api/v1/skills/{skillId}/skill.yaml` | Skill YAML content | `skill.updatedAt` |
| `GET /api/v1/skills/{skillId}/icon.png` | Base64-decoded `icon_data` as `image/png` | `skill.updatedAt` |
| `GET /api/v1/skills/{skillId}/{filename}.shortcut` | Base64-decoded `shortcut_data` as `application/octet-stream`. Controller validates `{filename}` against `skill.bridgeShortcutName` and returns 404 on mismatch. | `skill.updatedAt` |

All routes return `304 Not Modified` when the client sends a matching `If-None-Match` header, using Symfony's `Response::isNotModified()`. ETag values use the Unix timestamp of the relevant `updatedAt`/`createdAt` field.

YAML responses use `Content-Type: text/yaml` with `Content-Disposition: inline`. Shortcut responses use `Content-Disposition: attachment`.

### Existing controller: DownloadController

Stays as-is at `GET /api/v1/skills/{skillId}/download`. This is the "action" endpoint that logs each install with app version info. Always hits PHP, no caching.

## Cleanup

- Remove filesystem writes from SkillCompiler (it becomes a DB writer).
- Remove `icon_path` column from Skill entity.
- Remove transient `bridgeShortcutFilePath` and `iconPath` properties.
- Remove `/public/api/` from `.gitignore`.
- Remove `compile` target from Makefile (compilation is part of sync, not a standalone step).
- Remove SkillCompiler's `$publicDir` service binding from `services.yaml`.
- Migration: add `icon_data`, `shortcut_data`, `compiled_info` to `skill` table; create `skill_manifest` table; drop `icon_path` from `skill`.

## Observability

- Catalog routes go through Symfony, so errors are logged and visible in Symfony's error handling.
- Download/install actions are explicitly logged via `SkillDownload` entity in `DownloadController`.
- Sync errors surface at compile time (during sync), not at request time.
- Manifest versioning provides audit trail of what was served when.
