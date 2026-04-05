# Deploying OpenDispatch

## Prerequisites

- Ansible installed locally
- PHP Deployer (`dep`) installed locally
- SSH access to the target server
- Two SSH key pairs: one for the admin user, one for the deploy user

## 1. Server Provisioning (Ansible)

Ansible provisions a fresh server with PHP, Nginx, and the required user accounts.

```bash
ansible-playbook \
    -e "admin_user=<your-username>" \
    -e "admin_public_key='$(cat <path-to-admin-public-key>)'" \
    -e "deploy_public_key='$(cat <path-to-deploy-public-key>)'" \
    -i "<server-ip>," \
    -b infra/playbook.yml
```

The trailing comma after the IP is required (ad-hoc inventory).

This runs four roles in order:

| Role | What it does |
|------|-------------|
| `base` | System packages, firewall, timezone |
| `users` | Creates admin + deploy users, configures sudoers |
| `php` | Installs PHP-FPM 8.5 with required extensions |
| `nginx` | Installs Nginx, configures vhost for the domain |

Default variables are in `infra/group_vars/all.yml`. Override them with `-e` flags as needed.

### Sudoers

The deploy user gets three passwordless sudo commands:

- `systemctl reload php8.5-fpm` -- reload PHP-FPM after deploy
- `chown -R www-data:www-data` on the shared data directory
- `chmod -R g+w` on the shared data directory

## 2. Application Deployment (Deployer)

Set the `OPENDISPATCH_PROD_HOST` environment variable to the server IP, then deploy:

```bash
export OPENDISPATCH_PROD_HOST=<server-ip>
dep deploy opendispatch_prod
```

### What deploy does

1. Clones the repo to a new release directory
2. Installs Composer dependencies
3. Clears and warms the Symfony cache
4. Installs assets and compiles the asset map
5. Symlinks the new release as `current`
6. Fixes data directory ownership and permissions (`data:chown`)
7. Runs database migrations
8. Reloads PHP-FPM

### Shared between releases

- `.env.local` -- environment-specific config (database, secrets)
- `var/data/` -- SQLite database

### First deploy

After the first deploy, SSH into the server and create `.env.local` in the shared directory:

```bash
ssh deploy@<server-ip>
cat > /var/www/vhosts/opendispatch.ai/shared/.env.local << 'EOF'
APP_ENV=prod
APP_SECRET=<generate-with-openssl-rand-hex-32>
DEFAULT_URI=https://opendispatch.ai
WEBHOOK_SECRET=<your-webhook-secret>
SKILLS_REPO_URL=https://github.com/your-org/opendispatch-skills.git
EOF
```

Then redeploy to pick up the config.
