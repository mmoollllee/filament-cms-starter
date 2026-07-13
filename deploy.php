<?php

namespace Deployer;

require 'recipe/laravel.php';
require 'contrib/rsync.php';

// Tables included in `dep pull:db-refresh` (content, no users/media binaries).
set('db_pull_include', [
    'contents',
    'fragments',
    'layout_presets',
    'menu_items',
    'menu_locations',
    'menus',
    'migrations',
    'redirects',
    'tenant_user',
    'tenants',
    // 'users',
]);
set('files', [
    'storage/app/public/',
]);

set('allow_anonymous_stats', false);

// Hosts — adjust per project.
host('example.com')
    ->set('remote_user', 'deploy')
    ->set('deploy_path', '~/example.com');

desc('DB pull snapshot (remote → local)');
task('pull:db-refresh', function () {
    $tables = get('db_pull_include');
    if (empty($tables)) {
        writeln('<error>No tables defined for pull. Set db_pull_include in deploy.php</error>');

        return;
    }

    $longRunning = [
        'timeout' => null,
        'real_time_output' => true,
    ];

    cd('{{deploy_path}}');
    run('php artisan snapshot:create --table='.implode(' --table=', $tables), $longRunning);

    // Find newest snapshot file on remote, download, keep remote clean.
    $remote = run('ls -1t {{deploy_path}}/database/dumps | head -n1');

    download("{{deploy_path}}/database/dumps/{$remote}", "database/dumps/{$remote}");
    run('php artisan snapshot:cleanup --keep=1');

    $dump = str_replace('.sql.gz', '', $remote);

    runLocally("php artisan snapshot:load {$dump} --drop-tables=0 --stream --force", $longRunning);
    runLocally(
        'php -r "require \'vendor/autoload.php\'; \$app = require \'bootstrap/app.php\'; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); Illuminate\\Support\\Facades\\DB::statement(\"UPDATE tenants SET primary_domain = CONCAT(primary_domain, \'.test\') WHERE primary_domain NOT LIKE \'%.test\' AND primary_domain IS NOT NULL AND primary_domain != \'\'\");"'
    );
    runLocally('php artisan optimize');
    runLocally('php artisan cache:clear');
    writeln('<info>✓ DB refresh pulled via snapshots.</info>');
});

task('pull:db-full', function () {
    $longRunning = [
        'timeout' => null,
        'real_time_output' => true,
    ];

    cd('{{deploy_path}}');
    run('php artisan snapshot:create', $longRunning);

    $remote = run('ls -1t {{deploy_path}}/database/dumps | head -n1');

    download("{{deploy_path}}/database/dumps/{$remote}", "database/dumps/{$remote}");
    run('php artisan snapshot:cleanup --keep=1');

    $dump = str_replace('.sql.gz', '', $remote);

    runLocally("php artisan snapshot:load {$dump} --stream --force", $longRunning);
    runLocally(
        'php -r "require \'vendor/autoload.php\'; \$app = require \'bootstrap/app.php\'; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); Illuminate\\Support\\Facades\\DB::statement(\"UPDATE tenants SET primary_domain = CONCAT(primary_domain, \'.test\') WHERE primary_domain NOT LIKE \'%.test\' AND primary_domain IS NOT NULL AND primary_domain != \'\'\");"'
    );
    runLocally('php artisan optimize');
    runLocally('php artisan cache:clear');
    writeln('<info>✓ Full DB pulled via snapshots.</info>');
});

desc('Pull Storage (remote → local)');
task('pull:files', function () {
    foreach (get('files') as $folder) {
        writeln("<info>Downloading {$folder}:</info>");
        download("{{deploy_path}}/{$folder}", $folder, ['options' => ['--delete']]);
    }
    writeln('<info>✓ Files pulled.</info>');
});

Deployer::get()->tasks->remove('deploy');
desc('Publish code on remote (git pull, composer install, npm i & build, migrate, optimize)');
task('deploy', function () {
    cd('{{deploy_path}}');
    run('git pull');
    run('composer install --no-dev --optimize-autoloader');
    run('npm i');
    run('npm run build');
    run('php artisan migrate --force');
    run('php artisan optimize');
    run('php artisan cache:clear');
    writeln('<info>✓ Git Pull, Composer Install, NPM Install & Build, Migrate, Optimize.</info>');
});

desc('Reset remote repository');
task('reset:hard', function () {
    cd('{{deploy_path}}');
    run('git reset --hard');
    writeln('<info>✓ Git Reset hard.</info>');
});
