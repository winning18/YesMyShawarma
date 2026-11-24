<?php

namespace App\Console\Commands;

use App\Services\Setup\ClientSetupService;
use App\Services\Setup\DemoDataService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Fills the shared staging install with "Express POS Demo" (owner,
 * 2026-11-24). --fresh empties the database first, for a clean demo again.
 * Never on a live install: refused unless APP_ENV is staging or local.
 */
#[Signature('expresspos:demo-data {--fresh : Empty the database first (a clean demo again)}')]
#[Description('Fill this staging install with the made-up "Express POS Demo" restaurant')]
class DemoDataCommand extends Command
{
    public function handle(DemoDataService $demo, ClientSetupService $setup): int
    {
        if (! app()->environment(['staging', 'local', 'testing'])) {
            $this->error('Demo data only goes on a staging install (APP_ENV=staging). This one is '.app()->environment().'.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        } elseif (! $setup->isFreshInstall()) {
            $this->error('This install already has data. Use --fresh to empty it and start the demo again.');

            return self::FAILURE;
        }

        $accounts = $demo->fill();

        $this->info('Express POS Demo is ready.');
        $this->newLine();
        $this->line('Sign-in details (shown once; each must set a new password on first sign-in):');
        $this->table(
            ['Role', 'Email', 'Temporary password', 'Staff ID', 'PIN'],
            array_map(fn (array $a) => [$a['role'], $a['email'], $a['temporary_password'], $a['staff_code'] ?? '', $a['pin'] ?? ''], $accounts),
        );

        return self::SUCCESS;
    }
}
