<?php

namespace App\Console\Commands;

use App\Services\Settings\SettingsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sets (or with --clear, removes) the customer website's own domain
 * (owner, 2026-11-24). Run by the add-website-domain server script once
 * the domain answers with its SSL certificate; the POS stays on its
 * pos.solusysdigital.com address.
 */
#[Signature('expresspos:website-domain {domain?} {--clear}')]
#[Description('Set the customer website\'s own domain (the POS keeps its own address)')]
class WebsiteDomainCommand extends Command
{
    public function handle(SettingsService $settings): int
    {
        if ($this->option('clear')) {
            $settings->set(SettingsService::WEBSITE_DOMAIN, null);
            $this->info('The website is back on the POS address.');

            return self::SUCCESS;
        }

        $domain = strtolower(trim((string) $this->argument('domain')));
        $domain = preg_replace('/^www\./', '', $domain);
        $posHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! preg_match('/^(?!-)[a-z0-9-]{1,63}(\.[a-z0-9-]{1,63})+$/', $domain) || str_ends_with($domain, 'solusysdigital.com') || $domain === $posHost) {
            $this->error('Give the restaurant\'s own domain, e.g. deboyeskitchen.com (not a solusysdigital.com address).');

            return self::FAILURE;
        }

        $settings->set(SettingsService::WEBSITE_DOMAIN, $domain);
        $this->info("The website now answers at https://{$domain}; the POS stays at {$posHost}.");

        return self::SUCCESS;
    }
}
