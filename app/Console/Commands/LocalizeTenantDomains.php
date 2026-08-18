<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:localize-tenant-domains')]
#[Description('Hängt .test an alle Tenant-Domains, damit ein lokal eingespielter Prod-Dump unter Herd erreichbar ist (idempotent, nur lokal/Test).')]
class LocalizeTenantDomains extends Command
{
    public function handle(): int
    {
        if (! $this->getLaravel()->environment(['local', 'testing'])) {
            $this->error('Abgebrochen: Dieser Befehl darf nur lokal laufen (er verändert alle Tenant-Domains).');

            return self::FAILURE;
        }

        // Concatenated in PHP rather than with SQL CONCAT(): portable across
        // MariaDB (Herd) and SQLite (tests), and it goes through the query
        // builder, so no model events fire.
        $tenants = DB::table('tenants')
            ->whereNotNull('primary_domain')
            ->where('primary_domain', '!=', '')
            ->where('primary_domain', 'not like', '%.test')
            ->get(['id', 'primary_domain']);

        foreach ($tenants as $tenant) {
            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['primary_domain' => $tenant->primary_domain.'.test']);
        }

        $this->info("{$tenants->count()} Tenant-Domain(s) auf *.test umgeschrieben.");

        return self::SUCCESS;
    }
}
