<?php

use App\Models\Tenant;
use Mmoollllee\Cms\Filament\Pages\Tenancy\EditTenantProfilePage;

it('has every tenant profile form field fillable on the Tenant model', function () {
    $fields = (fn (): array => $this->tenantFields())->call(new EditTenantProfilePage);

    // update() silently discards non-fillable attributes — a field missing from
    // Tenant::$fillable is "saved" by the panel without ever being persisted.
    // The package grows this form (mail logo, favicon, Umami), so the app-side
    // $fillable has to be checked against it rather than eyeballed.
    $tenant = new Tenant;

    foreach ($fields as $field) {
        expect($tenant->isFillable($field))->toBeTrue("'{$field}' fehlt in Tenant::\$fillable");
    }
});
