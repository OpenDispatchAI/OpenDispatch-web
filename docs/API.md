# OpenDispatch Skill API

Base URL: `https://opendispatch.ai`

All catalog endpoints support ETag-based caching. Send `If-None-Match` with the ETag value from a previous response to receive `304 Not Modified` when the content hasn't changed.

## Endpoints

### GET /api/v1/index.json

Skill catalog index. Lists all available skills with summary metadata.

**Response:** `application/json`

```json
{
  "version": 1,
  "generated_at": "2026-04-05T12:00:00+00:00",
  "skill_count": 1,
  "skills": [
    {
      "skill_id": "tesla",
      "name": "Tesla",
      "version": "1.0.0",
      "description": "Control your Tesla",
      "author": "opendispatch",
      "author_url": "https://opendispatch.ai",
      "action_count": 2,
      "example_count": 3,
      "tags": ["automotive", "smart-home"],
      "languages": ["en"],
      "requires_bridge_shortcut": true,
      "bridge_shortcut_share_url": "https://opendispatch.ai/api/v1/skills/tesla/shortcut",
      "download_url": "https://opendispatch.ai/api/v1/skills/tesla/download",
      "icon": "iVBORw0KGgoAAAANSUhEUg...",
      "created_at": "2026-04-05T12:00:00+00:00",
      "updated_at": "2026-04-05T12:00:00+00:00"
    }
  ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `skill_id` | string | Unique identifier |
| `name` | string | Display name |
| `version` | string | Semver version |
| `description` | string | Short description |
| `author` | string | Author name |
| `author_url` | string? | Author website |
| `action_count` | int | Number of actions the skill provides |
| `example_count` | int | Number of example phrases |
| `tags` | string[] | Categorization tags |
| `languages` | string[] | Supported language codes |
| `requires_bridge_shortcut` | bool | Whether the skill needs an iOS Shortcut to function |
| `bridge_shortcut_share_url` | string? | Absolute URL to download the `.shortcut` file |
| `download_url` | string | Absolute URL to the download/install endpoint |
| `icon` | string? | Base64-encoded PNG icon, or `null` if none |
| `created_at` | string | ISO 8601 timestamp |
| `updated_at` | string | ISO 8601 timestamp |

---

### GET /api/v1/skills/{skillId}/info.json

Detailed skill information including action definitions.

**Response:** `application/json`

```json
{
  "skill_id": "tesla",
  "name": "Tesla",
  "version": "1.0.0",
  "description": "Control your Tesla",
  "author": "opendispatch",
  "author_url": "https://opendispatch.ai",
  "tags": ["automotive", "smart-home"],
  "languages": ["en"],
  "requires_bridge_shortcut": true,
  "bridge_shortcut": "OpenDispatch - Tesla V1",
  "bridge_shortcut_share_url": "https://opendispatch.ai/api/v1/skills/tesla/shortcut",
  "actions": [
    {
      "id": "vehicle.unlock",
      "title": "Unlock",
      "description": "Unlock the car doors",
      "example_count": 2,
      "has_parameters": false,
      "confirmation": null
    },
    {
      "id": "vehicle.climate.set_temperature",
      "title": "Set Temperature",
      "description": "Set the cabin temperature",
      "example_count": 1,
      "has_parameters": true,
      "confirmation": null
    }
  ],
  "created_at": "2026-04-05T12:00:00+00:00",
  "updated_at": "2026-04-05T12:00:00+00:00"
}
```

**Action fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Action identifier (e.g. `vehicle.unlock`) |
| `title` | string | Display title |
| `description` | string | What the action does |
| `example_count` | int | Number of example trigger phrases |
| `has_parameters` | bool | Whether the action accepts parameters |
| `confirmation` | string? | Confirmation prompt shown before executing, or `null` |

---

### GET /api/v1/skills/{skillId}/skill.yaml

Raw skill YAML definition. This is the full skill file the app needs for execution.

**Response:** `text/yaml`, `Content-Disposition: inline`

---

### GET /api/v1/skills/{skillId}/icon.png

Skill icon image. Returns `404` if the skill has no icon.

**Response:** `image/png`

---

### GET /api/v1/skills/{skillId}/shortcut

iOS Shortcut file for bridge skills. Returns `404` if the skill has no shortcut.

The response includes a `Content-Disposition` header with the correct filename derived from the skill's `bridge_shortcut_name`.

**Response:** `application/octet-stream`, `Content-Disposition: attachment; filename="{name}.shortcut"`

---

### GET /api/v1/skills/{skillId}/download

Install/download a skill. This is the **action endpoint** — it logs each download for analytics. Use this when the user explicitly installs a skill, not for browsing.

**Request headers:**

| Header | Required | Description |
|--------|----------|-------------|
| `X-OpenDispatch-Version` | No | App version string (e.g. `1.2.0`). Defaults to `web` if omitted. |

**Response:** `text/yaml`, `Content-Disposition: attachment; filename="skill.yaml"`

Returns the same YAML content as the `/skill.yaml` endpoint, but records the download.

## Caching

Catalog endpoints are served through Symfony's built-in HTTP reverse proxy (HttpCache). Responses include `ETag` and `Cache-Control: public` headers.

The proxy handles conditional requests automatically — if the client sends `If-None-Match` with a matching ETag, it returns `304 Not Modified` without hitting the database.

Cache lifetimes:
- Index and skill metadata: 1 hour (`s-maxage=3600`)
- Icons and shortcuts: 24 hours (`s-maxage=86400`)

Content is revalidated after a sync since ETags change when skills are updated.

The `/download` endpoint is not cached — it always hits PHP and logs the download.

## Errors

| Status | Meaning |
|--------|---------|
| `200` | Success |
| `304` | Not Modified (ETag match) |
| `404` | Skill not found, resource unavailable, or filename mismatch |
