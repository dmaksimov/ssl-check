<?php

use Illuminate\Foundation\Inspiring;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Artisan::command('check-ssl', function () {
    $skippedCount = 0;
    if (! is_array(Cache::get('ssl-checked-sites'))) {
        Cache::put('ssl-checked-sites', [], 60);
    }

    foreach (Forge::sites() as $site) {
        if ($site->name == 'default') continue;
        if (in_array($site->name, Cache::get('ssl-checked-sites'))) {
            $skippedCount++;
            continue;
        }

        $this->info($site->name);
        try {
            $certificate = \Spatie\SslCertificate\SslCertificate::createForHostName($site->name);
            $this->info('Expires: ' . $certificate->expirationDate());
            if (! $certificate->isValid()) {
                $this->warn('Certificate is invalid');
            }
            if ($certificate->expirationDate()->lte(now())) {
                $this->warn('Certificate is expired');
            }
            $this->line('');

            Cache::put(
                'ssl-checked-sites',
                array_merge(Cache::get('ssl-checked-sites'), [$site->name]),
                60
            );
        } catch (\Exception $e) {
            $this->warn('Error getting certificate info.');
            $this->warn($e->getMessage());
            $this->line('');
        }
    }

    $this->info('Skipped ' . $skippedCount);
});
