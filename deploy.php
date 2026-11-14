<?php

/**
 * In-place git deploy on Plesk / managed hosting, plus snapshot-based DB and
 * storage pulls for local development. Everything mechanical lives in
 * mmoollllee/laravel-deployer; this file is host + project configuration.
 *
 *   dep deploy          publish (git pull, composer, assets, migrate, optimize)
 *   dep deploy:quick    code-only (git pull + optimize) — blade/config changes
 *   dep setup           non-destructive first-time bootstrap of a fresh remote
 *   dep pull:db-refresh copy the db_pull_include tables into the local DB
 *   dep pull:files      mirror the remote storage folders
 *   dep shell           SSH into the webroot with deploy key + pinned PHP
 *   dep list            everything else (git:*, composer:*, logs*, artisan:*)
 *
 * `dep` itself must be Deployer 8: composer global require deployer/deployer:^8.0
 */

namespace Deployer;

require 'vendor/mmoollllee/laravel-deployer/recipe/app.php';

// Host — adjust per project.
host('example.com')
    ->set('remote_user', 'deploy')
    ->set('deploy_path', '~/example.com')
    // Plesk: pin the PHP binary the site runs on (optional; defaults to `php`).
    // ->set('remote_php', '/opt/plesk/php/8.3/bin/php')
    // Deploy key for `git pull` (optional; omit to use ~/.ssh/config).
    ->set('git_ssh_key', '~/.ssh/example_ed25519');

// Tables copied by `dep pull:db-refresh` — editorial content, no user accounts
// and no media binaries. Uncomment the media/versions tables once the library is
// in use and you want the local copy to match production; they are large, and
// the files themselves come from `dep pull:files`.
set('db_pull_include', [
    'contents',
    'fragments',
    // 'filament_media_library',
    // 'filament_media_library_folders',
    'layout_presets',
    // 'media',
    'menu_items',
    'menu_locations',
    'menus',
    'migrations',
    'redirects',
    'tenant_user',
    'tenants',
    // 'users',
    // 'versions',
]);

// Storage folders synced by `dep pull:files` / `dep push:files`.
set('files', [
    'storage/app/public/',
]);

// Post-deploy steps. Queue workers otherwise keep pre-deploy code (and job
// FQCNs) in memory.
set('deploy_cache_clear', true);
set('deploy_queue_restart', true);

// Rewrite tenant domains to *.test after every DB pull, so a production dump is
// reachable under Herd.
desc('Lokale Tenant-Domains auf *.test umschreiben');
task('pull:localize-domains', function () {
    runLocally('php artisan app:localize-tenant-domains');
    // The host → tenant lookup and the pages are cached forever — drop the pulled
    // tenants' caches, or the local copy keeps serving the pre-pull rows.
    runLocally('php artisan cms:clear-tenant-cache');
});
after('pull:db-refresh', 'pull:localize-domains');
after('pull:db-full', 'pull:localize-domains');
