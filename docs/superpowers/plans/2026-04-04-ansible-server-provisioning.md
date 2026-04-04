# Ansible Server Provisioning Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers-extended-cc:subagent-driven-development (if subagents available) or superpowers-extended-cc:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provision a Hetzner CX22 VPS with PHP 8.5-FPM, nginx, certbot, and hardened SSH for hosting the OpenDispatch Symfony app.

**Architecture:** Five Ansible roles (base, users, php, nginx, certbot) orchestrated by a single playbook. Variables with placeholder values keep personal data out of the repo. The `infra/` directory lives inside `OpenDispatch-web/`.

**Tech Stack:** Ansible, Ubuntu 24.04, PHP 8.5-FPM, nginx, certbot, UFW, fail2ban

**Spec:** `docs/superpowers/specs/2026-04-04-ansible-server-provisioning-design.md`

---

## File Structure

```
infra/
  .gitignore
  ansible.cfg
  requirements.yml
  inventory/
    hosts.yml
  group_vars/
    all.yml
  playbook.yml
  roles/
    base/
      tasks/main.yml
    users/
      tasks/main.yml
      handlers/main.yml
    php/
      tasks/main.yml
    nginx/
      tasks/main.yml
      templates/vhost.conf.j2
      handlers/main.yml
    certbot/
      tasks/main.yml
```

---

### Task 0: Scaffold directory structure and config files

**Files:**
- Create: `infra/.gitignore`
- Create: `infra/ansible.cfg`
- Create: `infra/requirements.yml`
- Create: `infra/inventory/hosts.yml`
- Create: `infra/group_vars/all.yml`
- Create: `infra/playbook.yml`

- [ ] **Step 1: Create `infra/.gitignore`**

```gitignore
# Local inventory overrides
inventory/local*.yml

# SSH key files
*.pub
*.pem
```

- [ ] **Step 2: Create `infra/ansible.cfg`**

```ini
[defaults]
inventory = inventory/hosts.yml
roles_path = roles
```

- [ ] **Step 3: Create `infra/requirements.yml`**

```yaml
---
collections:
  - name: ansible.posix
  - name: community.general
```

- [ ] **Step 4: Install Ansible collections**

```bash
cd infra && ansible-galaxy collection install -r requirements.yml
```

- [ ] **Step 6: Create `infra/inventory/hosts.yml`**

```yaml
all:
  hosts:
    opendispatch:
      ansible_host: changeme
      ansible_user: "{{ admin_user }}"
      ansible_become: true
```

- [ ] **Step 7: Create `infra/group_vars/all.yml`**

```yaml
admin_user: changeme
admin_public_key: "ssh-ed25519 AAAA... changeme"
deploy_public_key: "ssh-ed25519 AAAA... changeme"
domain: opendispatch.ai
php_version: "8.5"
certbot_email: changeme@example.com
```

- [ ] **Step 8: Create `infra/playbook.yml`**

```yaml
---
- name: Provision OpenDispatch server
  hosts: all

  roles:
    - base
    - users
    - php
    - nginx
    - certbot
```

- [ ] **Step 9: Verify syntax**

Run from `infra/`:
```bash
ansible-playbook playbook.yml --syntax-check
```

Expected: `playbook: playbook.yml` (no errors — roles don't exist yet, but syntax is valid)

- [ ] **Step 10: Commit**

```bash
git add infra/
git commit -m "infra: scaffold Ansible directory structure and config"
```

---

### Task 1: Base role — apt, UFW, fail2ban, unattended-upgrades

**Files:**
- Create: `infra/roles/base/tasks/main.yml`

- [ ] **Step 1: Create `infra/roles/base/tasks/main.yml`**

```yaml
---
- name: Update apt cache
  ansible.builtin.apt:
    update_cache: true
    cache_valid_time: 3600

- name: Upgrade all packages
  ansible.builtin.apt:
    upgrade: safe

- name: Install base packages
  ansible.builtin.apt:
    name:
      - ufw
      - fail2ban
      - unattended-upgrades
      - apt-listchanges
      - git
      - curl
      - unzip
    state: present

- name: Configure unattended-upgrades (security only)
  ansible.builtin.copy:
    dest: /etc/apt/apt.conf.d/20auto-upgrades
    content: |
      APT::Periodic::Update-Package-Lists "1";
      APT::Periodic::Unattended-Upgrade "1";
    owner: root
    group: root
    mode: "0644"

- name: Set UFW default deny incoming
  community.general.ufw:
    direction: incoming
    policy: deny

- name: Set UFW default allow outgoing
  community.general.ufw:
    direction: outgoing
    policy: allow

- name: Allow SSH through UFW
  community.general.ufw:
    rule: allow
    port: "22"
    proto: tcp

- name: Allow HTTP through UFW
  community.general.ufw:
    rule: allow
    port: "80"
    proto: tcp

- name: Allow HTTPS through UFW
  community.general.ufw:
    rule: allow
    port: "443"
    proto: tcp

- name: Enable UFW
  community.general.ufw:
    state: enabled

- name: Enable and start fail2ban
  ansible.builtin.systemd:
    name: fail2ban
    enabled: true
    state: started
```

- [ ] **Step 2: Dry-run against the server**

```bash
ansible-playbook playbook.yml --user root --check --diff
```

Review output for expected changes. No actual changes applied in check mode.

- [ ] **Step 3: Run for real**

```bash
ansible-playbook playbook.yml --user root
```

Expected: all tasks green/changed on first run.

- [ ] **Step 4: Commit**

```bash
git add infra/roles/base/
git commit -m "infra: add base role — apt, UFW, fail2ban, unattended-upgrades"
```

---

### Task 2: Users role — admin user, deploy user, SSH hardening

**Files:**
- Create: `infra/roles/users/tasks/main.yml`
- Create: `infra/roles/users/handlers/main.yml`

- [ ] **Step 1: Create `infra/roles/users/tasks/main.yml`**

```yaml
---
- name: Create admin user
  ansible.builtin.user:
    name: "{{ admin_user }}"
    groups: sudo
    append: true
    shell: /bin/bash
    create_home: true

- name: Set authorized key for admin user
  ansible.posix.authorized_key:
    user: "{{ admin_user }}"
    key: "{{ admin_public_key }}"
    state: present
    exclusive: true

- name: Allow admin user passwordless sudo
  ansible.builtin.copy:
    dest: "/etc/sudoers.d/{{ admin_user }}"
    content: "{{ admin_user }} ALL=(ALL) NOPASSWD:ALL"
    owner: root
    group: root
    mode: "0440"
    validate: "visudo -cf %s"

- name: Create deploy user
  ansible.builtin.user:
    name: deploy
    groups: www-data
    append: true
    shell: /bin/bash
    create_home: true

- name: Set authorized key for deploy user
  ansible.posix.authorized_key:
    user: deploy
    key: "{{ deploy_public_key }}"
    state: present
    exclusive: true

- name: Verify admin user has authorized key before hardening SSH
  ansible.builtin.stat:
    path: "/home/{{ admin_user }}/.ssh/authorized_keys"
  register: admin_key_file
  failed_when: not admin_key_file.stat.exists

- name: Harden SSH config
  ansible.builtin.lineinfile:
    path: /etc/ssh/sshd_config
    regexp: "{{ item.regexp }}"
    line: "{{ item.line }}"
    state: present
    validate: "sshd -t -f %s"
  loop:
    - { regexp: "^#?PermitRootLogin", line: "PermitRootLogin no" }
    - { regexp: "^#?PasswordAuthentication", line: "PasswordAuthentication no" }
    - { regexp: "^#?PubkeyAuthentication", line: "PubkeyAuthentication yes" }
  notify: restart sshd
```

- [ ] **Step 2: Create `infra/roles/users/handlers/main.yml`**

```yaml
---
- name: restart sshd
  ansible.builtin.systemd:
    name: sshd
    state: restarted
```

- [ ] **Step 3: Dry-run**

```bash
ansible-playbook playbook.yml --user root --check --diff
```

Verify: admin user created, deploy user created, SSH config changes shown, handler triggered.

- [ ] **Step 4: Run for real**

```bash
ansible-playbook playbook.yml --user root
```

- [ ] **Step 5: Verify SSH access with the new admin user**

```bash
ssh <admin_user>@<server-ip> "whoami && sudo whoami"
```

Expected output:
```
<admin_user>
root
```

- [ ] **Step 6: Verify root login is blocked**

```bash
ssh root@<server-ip> 2>&1 || echo "Correctly denied"
```

Expected: connection refused or permission denied.

- [ ] **Step 7: Commit**

```bash
git add infra/roles/users/
git commit -m "infra: add users role — admin, deploy, SSH hardening"
```

---

### Task 3: PHP role — Ondrej PPA, PHP-FPM, extensions

**Files:**
- Create: `infra/roles/php/tasks/main.yml`

- [ ] **Step 1: Create `infra/roles/php/tasks/main.yml`**

```yaml
---
- name: Install prerequisites for Ondrej PPA
  ansible.builtin.apt:
    name:
      - software-properties-common
    state: present

- name: Add Ondrej PHP PPA
  ansible.builtin.apt_repository:
    repo: ppa:ondrej/php
    state: present
    update_cache: true

- name: Install PHP-FPM and extensions
  ansible.builtin.apt:
    name:
      - "php{{ php_version }}-fpm"
      - "php{{ php_version }}-cli"
      - "php{{ php_version }}-sqlite3"
      - "php{{ php_version }}-intl"
      - "php{{ php_version }}-mbstring"
      - "php{{ php_version }}-xml"
      - "php{{ php_version }}-curl"
      - "php{{ php_version }}-opcache"
    state: present

- name: Enable and start PHP-FPM
  ansible.builtin.systemd:
    name: "php{{ php_version }}-fpm"
    enabled: true
    state: started

- name: Download Composer installer
  ansible.builtin.get_url:
    url: https://getcomposer.org/installer
    dest: /tmp/composer-setup.php
    mode: "0755"

- name: Install Composer
  ansible.builtin.command:
    cmd: php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    creates: /usr/local/bin/composer

- name: Remove Composer installer
  ansible.builtin.file:
    path: /tmp/composer-setup.php
    state: absent
```

- [ ] **Step 2: Run against server (now as admin user since SSH is hardened)**

```bash
ansible-playbook playbook.yml
```

Expected: PPA added, PHP packages installed, FPM running.

- [ ] **Step 3: Verify on server**

```bash
ssh <admin_user>@<server-ip> "php -v && php -m | grep -E 'sqlite3|intl|mbstring|xml|curl|OPcache'"
```

Expected: PHP 8.5.x and all listed extensions.

- [ ] **Step 4: Commit**

```bash
git add infra/roles/php/
git commit -m "infra: add PHP role — Ondrej PPA, PHP 8.5-FPM, extensions, Composer"
```

---

### Task 4: nginx role — install, vhost template, directory setup

**Files:**
- Create: `infra/roles/nginx/tasks/main.yml`
- Create: `infra/roles/nginx/templates/vhost.conf.j2`
- Create: `infra/roles/nginx/handlers/main.yml`

- [ ] **Step 1: Create `infra/roles/nginx/templates/vhost.conf.j2`**

```nginx
server {
    listen 80;
    server_name {{ domain }};
    root /var/www/vhosts/{{ domain }}/current/public;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php{{ php_version }}-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $document_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    error_log /var/log/nginx/{{ domain }}.error.log;
    access_log /var/log/nginx/{{ domain }}.access.log;
}
```

- [ ] **Step 2: Create `infra/roles/nginx/tasks/main.yml`**

```yaml
---
- name: Install nginx
  ansible.builtin.apt:
    name: nginx
    state: present

- name: Create vhost root directory
  ansible.builtin.file:
    path: "/var/www/vhosts/{{ domain }}"
    state: directory
    owner: deploy
    group: www-data
    mode: "2775"

- name: Create shared/var directory for SQLite
  ansible.builtin.file:
    path: "/var/www/vhosts/{{ domain }}/shared/var"
    state: directory
    owner: deploy
    group: www-data
    mode: "0775"

- name: Deploy vhost config
  ansible.builtin.template:
    src: vhost.conf.j2
    dest: "/etc/nginx/sites-available/{{ domain }}.conf"
    owner: root
    group: root
    mode: "0644"
  notify: reload nginx

- name: Enable vhost
  ansible.builtin.file:
    src: "/etc/nginx/sites-available/{{ domain }}.conf"
    dest: "/etc/nginx/sites-enabled/{{ domain }}.conf"
    state: link
  notify: reload nginx

- name: Remove default nginx site
  ansible.builtin.file:
    path: /etc/nginx/sites-enabled/default
    state: absent
  notify: reload nginx

- name: Enable and start nginx
  ansible.builtin.systemd:
    name: nginx
    enabled: true
    state: started
```

- [ ] **Step 3: Create `infra/roles/nginx/handlers/main.yml`**

```yaml
---
- name: reload nginx
  ansible.builtin.systemd:
    name: nginx
    state: reloaded
```

- [ ] **Step 4: Run against server**

```bash
ansible-playbook playbook.yml
```

- [ ] **Step 5: Verify nginx is serving**

```bash
curl -I http://<server-ip>
```

Expected: HTTP response (likely 403 or 502 since no app is deployed yet, but nginx is listening).

- [ ] **Step 6: Verify directory structure on server**

```bash
ssh <admin_user>@<server-ip> "ls -la /var/www/vhosts/ && stat /var/www/vhosts/{{ domain }}/shared/var"
```

Expected: directory exists with `deploy:www-data` ownership and `2775` / `0775` permissions.

- [ ] **Step 7: Commit**

```bash
git add infra/roles/nginx/
git commit -m "infra: add nginx role — vhost, directory structure, security headers"
```

---

### Task 5: Certbot role — Let's Encrypt SSL

**Files:**
- Create: `infra/roles/certbot/tasks/main.yml`

**Prerequisite:** DNS for the domain must be pointing at the server's IP before running this.

- [ ] **Step 1: Create `infra/roles/certbot/tasks/main.yml`**

```yaml
---
- name: Install certbot and nginx plugin
  ansible.builtin.apt:
    name:
      - certbot
      - python3-certbot-nginx
    state: present

- name: Obtain SSL certificate
  ansible.builtin.command:
    cmd: >
      certbot --nginx
      --non-interactive
      --agree-tos
      --email {{ certbot_email }}
      -d {{ domain }}
    creates: "/etc/letsencrypt/live/{{ domain }}/fullchain.pem"

- name: Ensure certbot auto-renewal timer is enabled
  ansible.builtin.systemd:
    name: certbot.timer
    enabled: true
    state: started
```

- [ ] **Step 2: Run against server (only after DNS is configured)**

```bash
ansible-playbook playbook.yml
```

- [ ] **Step 3: Verify HTTPS**

```bash
curl -I https://{{ domain }}
```

Expected: HTTP/2 200 (or 403/502) with valid SSL certificate.

- [ ] **Step 4: Commit**

```bash
git add infra/roles/certbot/
git commit -m "infra: add certbot role — Let's Encrypt SSL"
```

---

### Task 6: End-to-end verification

- [ ] **Step 1: Run full playbook from scratch (idempotency check)**

```bash
ansible-playbook playbook.yml
```

Expected: all tasks show `ok` (green), no `changed` on second run. This confirms idempotency.

- [ ] **Step 2: Verify all services are running**

```bash
ssh <admin_user>@<server-ip> "systemctl is-active nginx php{{ php_version }}-fpm fail2ban ufw certbot.timer"
```

Expected: all `active`.

- [ ] **Step 3: Verify firewall rules**

```bash
ssh <admin_user>@<server-ip> "sudo ufw status verbose"
```

Expected: deny incoming, allow outgoing, rules for 22, 80, 443.

- [ ] **Step 4: Verify deploy user group membership**

```bash
ssh deploy@<server-ip> "id"
```

Expected: `groups=... www-data`

- [ ] **Step 5: Final commit if any adjustments were needed**

```bash
git add infra/
git commit -m "infra: fixes from end-to-end verification"
```
