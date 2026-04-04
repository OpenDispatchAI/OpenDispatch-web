# Ansible Server Provisioning

**Date:** 2026-04-04
**Status:** Draft
**Scope:** Server provisioning only. Code deployment handled separately by PHP Deployer.

## Context

OpenDispatch-web is a Symfony application that serves a skill API (static YAML/JSON) and processes webhook syncs from GitHub. The access pattern is read-heavy with a single write path (webhook). The app runs on SQLite, nginx, and PHP-FPM.

This spec covers an Ansible playbook to provision a Hetzner CX22 VPS (2 vCPU, 4GB RAM, Ubuntu 24.04 LTS) as a production server.

## Server Stack

- Ubuntu 24.04 LTS
- PHP 8.5-FPM (Ondrej PPA) with extensions: `pdo_sqlite`, `intl`, `mbstring`, `xml`, `curl`, `opcache`
- nginx
- SQLite (no database service)
- certbot (Let's Encrypt)
- UFW firewall
- fail2ban (SSH brute-force protection)

## Users

| User | SSH | Sudo | Groups | Purpose |
|------|-----|------|--------|---------|
| `{{ admin_user }}` | pubkey | yes | `sudo` | Personal admin account |
| `deploy` | pubkey | no | `www-data` | PHP Deployer connects as this user |
| `www-data` | no | no | -- | PHP-FPM process user (system default, untouched) |

- `admin_user` is a variable with a placeholder in the repo. Override via inventory or `-e` flag. No real names committed.
- Both SSH users get their public key from variables. No passwords.

## SSH Hardening

- `PermitRootLogin no`
- `PasswordAuthentication no`
- `PubkeyAuthentication yes`
- Restart `sshd` via handler, only after verifying the admin user exists and has an authorized key. This is the last task in the `users` role to prevent lockout if user creation fails partway through.

## Firewall (UFW)

Allow only:
- 22/tcp (SSH)
- 80/tcp (HTTP)
- 443/tcp (HTTPS)

Default deny incoming, allow outgoing.

## Directory Layout

```
/var/www/vhosts/
  {{ domain }}/           # Deployer manages contents
    current -> releases/...
    releases/
    shared/
```

Ansible creates the vhost root directory with ownership `deploy:www-data` and sets the setgid bit (`g+s`) so new files inherit the `www-data` group. Deployer creates the `current`, `releases`, and `shared` structure on first deploy.

The SQLite database file will live in `shared/var/` (Deployer's shared directory convention for Symfony's `var/`). Both the file and directory must be writable by `www-data` for SQLite WAL journal files. Ansible ensures the `shared/var/` directory exists with correct permissions (`deploy:www-data`, `0775`).

## nginx Configuration

HTTP vhost for `{{ domain }}`:
- `root /var/www/vhosts/{{ domain }}/current/public`
- Standard Symfony `try_files $uri /index.php$is_args$args`
- PHP-FPM via unix socket (`/run/php/php{{ php_version }}-fpm.sock`)
- Block direct `.php` access except `index.php`
- Security headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
- certbot handles HTTPS redirect and certificate installation

## Certbot

- Install certbot and the nginx plugin
- Run `certbot --nginx` for the domain with auto-agree and the configured email
- Auto-renewal via systemd timer (certbot's default on Ubuntu)
- Requires DNS to be pointing at the server before the playbook runs

## Playbook Structure

```
infra/
  inventory/
    hosts.yml
  group_vars/
    all.yml
  playbook.yml
  roles/
    .gitignore              # inventory overrides, key files
    base/
      tasks/main.yml      # apt update/upgrade, unattended-upgrades (security-only), UFW, fail2ban
    users/
      tasks/main.yml      # admin user, deploy user, SSH hardening
      files/.gitkeep       # public keys go here (gitignored) or use variables
    php/
      tasks/main.yml      # Ondrej PPA, php-fpm, extensions
    nginx/
      tasks/main.yml      # install nginx, deploy vhost config
      templates/vhost.conf.j2
    certbot/
      tasks/main.yml      # install certbot, obtain certificate
```

## Variables

Defined in `group_vars/all.yml` with placeholder values:

```yaml
admin_user: changeme
admin_public_key: "ssh-ed25519 AAAA..."
deploy_public_key: "ssh-ed25519 AAAA..."
domain: opendispatch.ai
php_version: "8.5"
certbot_email: changeme@example.com
```

All sensitive or personal values are placeholders. Override via:
- Local inventory file (gitignored)
- Command line: `ansible-playbook playbook.yml -e admin_user=yourname`

## Inventory

`inventory/hosts.yml` uses the admin user with `become` by default:

```yaml
all:
  hosts:
    opendispatch:
      ansible_host: <server-ip>
      ansible_user: "{{ admin_user }}"
      ansible_become: true
```

## Usage

```bash
# Initial provisioning (as root on a fresh server)
ansible-playbook -i infra/inventory/hosts.yml infra/playbook.yml --user root

# Subsequent runs (as admin user with sudo, uses inventory defaults)
ansible-playbook -i infra/inventory/hosts.yml infra/playbook.yml
```

The `--user root` override on first run avoids mutating the inventory file between runs.

## Role Execution Order

1. **base** -- apt update, install core packages, configure UFW, install fail2ban
2. **users** -- create admin + deploy users, harden SSH config
3. **php** -- add Ondrej PPA, install PHP-FPM + extensions
4. **nginx** -- install nginx, write vhost template, enable site
5. **certbot** -- install certbot, obtain and install certificate

## Out of Scope

- Code deployment (PHP Deployer)
- Database provisioning (SQLite is file-based, no service needed)
- CI/CD pipeline
- Monitoring/logging infrastructure
- Docker (production runs natively, Docker is dev-only)

## Dev Environment Impact

The dev Docker stack (`docker-compose.yml`) currently uses PostgreSQL. This should be migrated to SQLite to match production, but that is a separate task.
