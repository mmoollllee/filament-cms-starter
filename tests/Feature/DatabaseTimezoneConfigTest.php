<?php

/*
 * The app runs on German time (APP_TIMEZONE, Europe/Berlin by default) and stores
 * every date as entered. TIMESTAMP columns convert through the MySQL session's
 * time zone, so the session runs on a fixed +00:00: it keeps the written value,
 * and a dump pulled from the server reads the same on a Mac, whose SYSTEM zone
 * would shift it by 1–2 hours.
 */

it('runs on german time and keeps it through a fixed session zone', function (string $connection) {
    expect(config('app.timezone'))->toBe('Europe/Berlin')
        ->and(config("database.connections.{$connection}.timezone"))->toBe('+00:00');
})->with(['mysql', 'mariadb']);
