<?php

namespace App\Console\Commands;

use App\Services\Setup\DemoDataService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Puts the "Express POS Demo" menu (and delivery areas) on an install with
 * no menu yet: Express POS Admin runs it for a test client onboarded with
 * "fill with the demo menu" (owner, 2026-11-25).
 */
#[Signature('expresspos:demo-menu')]
#[Description('Add the demo menu to an install that has no menu yet (test clients)')]
class DemoMenuCommand extends Command
{
    public function handle(DemoDataService $demo): int
    {
        try {
            $count = $demo->fillMenu();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Demo menu added: {$count} dishes, on sale at every branch.");

        return self::SUCCESS;
    }
}
