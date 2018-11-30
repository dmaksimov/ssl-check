<?php

namespace App\Console\Commands;

use Cache;
use Forge;
use Illuminate\Console\Command;

class CheckSsl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check-ssl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check SSL of all sites';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
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
                    $this->notifyError($site, 'invalid');
                }
                if ($certificate->expirationDate()->lte(now())) {
                    $this->warn('Certificate is expired');
                    $this->notifyError($site, 'expired');
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
                $this->notifyError($site, 'error', $e->getMessage());
            }
        }

        $this->info('Skipped ' . $skippedCount);
    }

    protected function notifyError($site, string $reason, $errorMessage = null)
    {
        switch ($reason) {
            case 'invalid':
                $message = 'Certificate is invalid';
                break;
            case 'expired':
                $message = 'Certificate is expired';
                break;
            default:
                $message = 'Error with certificate';
        }

        if ($errorMessage) {
            $message .= "\n" . "Error Message: " . $errorMessage;
        }

        \Mail::raw($message, function ($message) use($site) {
            $message->to('david@cmsmax.com');
            $message->subject('SSL issue on ' . $site->name);
        });
    }
}