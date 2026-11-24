<?php

namespace App\Console\Commands;

use App\Services\Settings\BusinessProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Changes the business's brand from Express POS Admin (owner, 2026-11-24):
 * its two colours, and its logo if a new one is given. The owner can do
 * the same on their Business profile page.
 */
#[Signature('expresspos:brand {--primary-colour=} {--accent-colour=} {--logo= : Path to a new logo on this server (optional)}')]
#[Description('Set the business\'s brand colours and, optionally, a new logo')]
class BrandCommand extends Command
{
    public function handle(BusinessProfile $profile): int
    {
        $primary = strtolower((string) $this->option('primary-colour'));
        $accent = strtolower((string) $this->option('accent-colour'));

        if (! preg_match('/^#[0-9a-f]{6}$/', $primary) || ! preg_match('/^#[0-9a-f]{6}$/', $accent)) {
            $this->error('Give both colours as #rrggbb.');

            return self::FAILURE;
        }

        try {
            if ($this->option('logo')) {
                $profile->setLogoFromFile((string) $this->option('logo'));
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $profile->setBrandColours($primary, $accent);
        $this->info('Brand updated.');

        return self::SUCCESS;
    }
}
