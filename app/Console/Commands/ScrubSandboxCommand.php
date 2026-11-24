<?php

namespace App\Console\Commands;

use App\Services\Setup\SandboxScrubber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Makes a client's sandbox copy safe (SandboxScrubber): customers' personal
 * data replaced, locations blurred, the website domain disconnected. Run by
 * expresspos-sandbox right after copying the client's data. Refused on a
 * live install (APP_ENV=production), whatever is asked.
 */
#[Signature('expresspos:scrub-sandbox {--json : Print the result as one line of JSON}')]
#[Description('Replace customers\' personal data in this sandbox copy')]
class ScrubSandboxCommand extends Command
{
    public function handle(SandboxScrubber $scrubber): int
    {
        if (app()->environment('production')) {
            $this->error('This is a live install (APP_ENV=production). Customer data is only scrubbed in a sandbox.');

            return self::FAILURE;
        }

        $done = $scrubber->scrub();

        if ($this->option('json')) {
            $this->line(json_encode(['ok' => true, 'changed' => $done]));
        } else {
            $this->info('Customer data in this sandbox has been replaced.');
            $this->table(['Table', 'Rows'], collect($done)->map(fn (int $n, string $t) => [$t, $n])->values()->all());
        }

        return self::SUCCESS;
    }
}
