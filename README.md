# OpenDispatch Web

The official skill repository for [OpenDispatch](https://opendispatch.ai) — an iOS app that automates your iPhone with natural language.

This is a full-stack Symfony 8.0 app that serves as:

- **Public website** — skill browser with live search, skill detail pages, docs
- **JSON API** — skill catalog and download tracking for the iOS app
- **Admin dashboard** — sync management, featured skills, download stats

## Architecture

Skills are maintained as YAML files in a [separate GitHub repository](https://github.com/opendispatch/skills). When a PR is merged, GitHub Actions triggers a webhook that syncs the skills into this app's database and compiles static API files.

```
GitHub Skills Repo ──PR merged──> Webhook ──> Sync to DB ──> Compile static files
```

## Tech Stack

- PHP 8.5 / Symfony 8.0
- PostgreSQL 17
- Twig + Symfony UX (Turbo, Live Components, Stimulus)
- Docker (Nginx + PHP-FPM)

## Getting Started

```bash
# Build and start containers
make build
make up

# Install dependencies (first time)
docker compose exec php composer install

# Run migrations
make migrate

# Create an admin user
docker compose exec php bin/console app:create-admin

# Run tests
make test
```

The app is available at `http://localhost:8080`.

## Commands

| Command | Description |
|---------|-------------|
| `make up` | Start containers |
| `make down` | Stop containers |
| `make build` | Rebuild containers |
| `make shell` | Shell into PHP container |
| `make migrate` | Run database migrations |
| `make test` | Run test suite |
| `make sync` | Sync skills from GitHub repo |
| `make compile` | Compile static API files |

## Environment Variables

| Variable | Description |
|----------|-------------|
| `DATABASE_URL` | PostgreSQL connection string |
| `WEBHOOK_SECRET` | Secret token for the sync webhook |
| `SKILLS_REPO_URL` | URL of the GitHub skills repository |

## License

Apache 2.0
