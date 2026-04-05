<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/symfony.php';

set('repository', 'https://github.com/OpenDispatchAI/OpenDispatch-web.git');

add('shared_files', ['.env.local']);
add('shared_dirs', ['var/data']);
add('writable_dirs', []);

host('opendispatch_prod')
    ->set('hostname', $_ENV['OPENDISPATCH_PROD_HOST'])
    ->set('remote_user', 'deploy')
    ->set('deploy_path', '/var/www/vhosts/opendispatch.ai');

task('php:fpm:reload', function () {
    run('sudo systemctl reload php8.5-fpm');
});

task('data:chown', function () {
    run('sudo chown -R www-data:www-data {{deploy_path}}/shared/var/data');
    run('sudo chmod -R g+w {{deploy_path}}/shared/var/data');
});

task('assets:install', function () {
    run('{{bin/console}} assets:install -e "${APP_ENV:-prod}"');
});

task('asset-map:compile', function () {
    run('{{bin/console}} asset-map:compile -e "${APP_ENV:-prod}"');
});

after('deploy:cache:clear', 'assets:install');
after('assets:install', 'asset-map:compile');
after('deploy:symlink', 'data:chown');
after('data:chown', 'database:migrate');
after('database:migrate', 'php:fpm:reload');
after('deploy:failed', 'deploy:unlock');
