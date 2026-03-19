# OpenDispatch Skill Repository — V2 Design

Status: Approved
Date: 2026-03-19

## Overview

A Symfony 8.0 full-stack app serving as the public website, JSON API, and admin dashboard for the OpenDispatch skill repository. Skills are maintained as YAML files in a separate GitHub repo. A webhook triggers sync from GitHub into PostgreSQL, with static files compiled for the iOS app API.

## Architecture

```
GitHub Skills Repo (source of truth)
  │
  ├─ PR opened → CI validates YAMLs against tags.yaml
  ├─ PR merged → GitHub Actions calls webhook
  │
  ▼
Symfony App (single stack)
  ├─ Webhook endpoint → pulls repo, validates, upserts to DB, compiles static files
  ├─ Public pages → landing, skill browser (Live Component), skill detail, docs
  ├─ JSON API → static index.json/info.json + dynamic download endpoint with counter
  ├─ Admin dashboard → stats, featured flags, sync log with GitHub links
  └─ Static files → compiled to public/api/v1/ for iOS app
```

## Tech Stack

| Component | Choice |
|-----------|--------|
| Framework | Symfony 8.0 |
| Language | PHP 8.5 |
| Database | PostgreSQL 17 |
| Frontend | Twig + Symfony UX (Turbo, Live Components, Stimulus, Twig Components) |
| Assets | AssetMapper |
| Web server | Nginx + PHP-FPM |
| Containerization | Docker Compose |

## Data Model

### Skill

| Field | Type | Source |
|-------|------|--------|
| `id` | UUID | auto |
| `skillId` | string (unique) | YAML `skill_id` |
| `yamlContent` | text | raw YAML file |
| `name` | string | YAML |
| `version` | string (semver) | YAML |
| `description` | text | YAML |
| `author` | string | YAML |
| `authorUrl` | string, nullable | YAML |
| `tags` | json array | YAML (enforced against tags.yaml by CI) |
| `languages` | json array | YAML |
| `requiresBridgeShortcut` | boolean | YAML |
| `bridgeShortcutName` | string, nullable | YAML |
| `bridgeShortcutShareUrl` | string, nullable | YAML |
| `actionCount` | integer | computed from YAML |
| `exampleCount` | integer | computed from YAML |
| `iconPath` | string, nullable | from repo |
| `isFeatured` | boolean | admin-managed, survives sync |
| `syncedAt` | datetime | last sync |
| `createdAt` | datetime | auto |
| `updatedAt` | datetime | auto |

All fields except `isFeatured` are overwritten on sync. `isFeatured` is the only admin-managed field — preserves "GitHub is source of truth" principle.

### SkillDownload

| Field | Type |
|-------|------|
| `id` | UUID |
| `skill` | ManyToOne → Skill |
| `downloadedAt` | datetime |
| `appVersion` | string, nullable |

Time-series tracking. Enables future trending/charts without needing to backfill.

### SyncLog

| Field | Type |
|-------|------|
| `id` | UUID |
| `status` | string (success/failed) |
| `skillCount` | integer |
| `errorMessage` | text, nullable |
| `commitSha` | string |
| `commitUrl` | string |
| `actionRunUrl` | string, nullable |
| `syncedAt` | datetime |

### AdminUser

| Field | Type |
|-------|------|
| `id` | UUID |
| `email` | string (unique) |
| `password` | string (hashed) |
| `roles` | json array |

Single admin user, seeded via console command.

## Webhook Sync Flow

1. GitHub Actions POSTs to `/api/webhook/sync` with secret token in header, commit SHA, and action run URL in body
2. Symfony verifies the token
3. Pulls/clones the skills repo `main` branch to a temp directory
4. Reads `tags.yaml` for the allowed tags list
5. Iterates all skill YAML files, parses each:
   - Validates (syntax, required fields, semver, actions, tag allowlist)
   - If any YAML is invalid → abort entire sync, nothing changes, error returned
6. If all valid: upsert into DB by `skill_id`, overwrite all YAML-derived fields, preserve `isFeatured`
7. Delete any DB skills whose `skill_id` no longer exists in the repo
8. Compile static files to `public/api/v1/` (index.json, per-skill YAML + info.json)
9. Log result to SyncLog (with commit SHA, URLs)
10. Return 200 with summary (or 500 with errors)

## Public Pages

- **Landing** (`/`) — what is OpenDispatch, how it works, browse skills CTA
- **Skill browser** (`/skills`) — Live Component with tag chips + debounced text search, sortable by name/popularity/newest. Skill cards show name, description, tags, download count.
- **Skill detail** (`/skills/{skill_id}`) — full info, action list with descriptions, tags, download count (as popularity signal). No download button — users install from within the iOS app.
- **Docs** (`/docs/{slug}`) — markdown files from `content/docs/`, rendered via Twig with a markdown parser. Sidebar navigation.

All server-rendered (Twig), progressively enhanced with Turbo + Live Components.

## JSON API (for iOS app)

| Endpoint | Type | Description |
|----------|------|-------------|
| `GET /api/v1/index.json` | static file | Full skill catalog |
| `GET /api/v1/skills/{skill_id}/info.json` | static file | Detailed skill metadata |
| `GET /api/v1/skills/{skill_id}/download` | dynamic | Returns YAML, increments download counter, captures app version |
| `POST /api/webhook/sync` | dynamic | Webhook endpoint, authenticated with secret |

Static files served directly by Nginx. The download endpoint goes through Symfony to track downloads.

## Admin Dashboard

- **Login** (`/admin/login`) — form login
- **Dashboard** (`/admin`) — last sync timestamp, skill count, total downloads, resync button
- **Skill list** (`/admin/skills`) — table with name, tags, download count, featured toggle (Live Component)
- **Sync log** (`/admin/sync-log`) — history with status, skill count, errors, links to GitHub commit and action run

## Docs System

Markdown files stored in `content/docs/` within the Symfony project. Rendered to HTML via Twig with a markdown parser. Sidebar navigation derived from filesystem structure. Readable both on the web and directly in the repo.

## Tags

Allowed tags defined in a `tags.yaml` file in the skills repo. CI validates that skill YAMLs only use allowed tags. The web skill browser uses these tags for filtering (tag chips). No admin-managed tags — keeps everything in the source of truth.

## Project Structure

Single Symfony app at the repo root (no `backend/`/`frontend/` split):

```
OpenDispatch-web/
├── config/
├── content/docs/          # Markdown docs
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
├── migrations/
├── public/
│   ├── api/v1/            # Compiled static files (gitignored)
│   └── index.php
├── src/
│   ├── Command/
│   ├── Controller/
│   │   ├── Admin/
│   │   ├── Api/
│   │   └── Public/
│   ├── Entity/
│   ├── Form/
│   ├── Repository/
│   ├── Service/
│   └── Twig/Components/
├── templates/
├── tests/
├── docker-compose.yml
├── Makefile
└── composer.json
```

## Decisions & Trade-offs

| Decision | Rationale |
|----------|-----------|
| Single Symfony stack (no Next.js) | Twig + Symfony UX gives SSR by default + reactive UX. Simpler deployment, stronger portfolio piece. |
| GitHub as source of truth | Skills already reviewed via PRs. No need to duplicate CRUD in admin. |
| Upsert sync (not nuke & rebuild) | Preserves admin-managed fields (isFeatured) |
| Strict sync validation | CI catches errors before merge. If webhook gets invalid YAML, something is wrong — abort. |
| Time-series download tracking | Can't backfill history. Collect now, query later. |
| No download button on web | Installation happens in the iOS app. Web is for discovery. |
| Tags enforced via YAML allowlist | Keeps tags in source of truth, transparent to contributors. |
| Docs as markdown files | OSS-friendly, readable in repo and on web. |
