<?php

namespace App\Console\Commands;

use App\Services\Content\WebsiteThemeService;
use App\Services\Modules\ModuleService;
use App\Services\Setup\ClientSetupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

#[Signature('expresspos:new-client
    {--business= : Business name, as customers and staff see it}
    {--branch= : First branch name}
    {--branch-phone= : Branch phone, e.g. +233241234567}
    {--address= : Branch address}
    {--lat= : Branch latitude}
    {--lng= : Branch longitude}
    {--opens=08:00 : Opening time (HH:MM)}
    {--closes=22:00 : Closing time (HH:MM)}
    {--owner-name= : Owner\'s name}
    {--owner-email= : Owner\'s email (their sign-in)}
    {--owner-phone= : Owner\'s phone (optional)}
    {--support-email= : Solusys Digital support account email (optional)}
    {--modules= : Modules to switch on, comma-separated keys (none = POS only)}
    {--primary-colour= : Brand primary colour, #rrggbb (required, owner 2026-11-24)}
    {--accent-colour= : Brand accent colour, #rrggbb (required)}
    {--logo= : Path to the logo on this server: PNG, JPG or WebP, up to 2 MB (required)}
    {--theme=classic : Website look: classic, photo or onepage (with the Website module)}
    {--json : Print the result as one line of JSON (for the Express POS admin portal)}')]
#[Description('Set up a fresh Express POS install for a new client: business, first branch, owner account and modules')]
class NewClient extends Command
{
    public function handle(ClientSetupService $setup): int
    {
        if (! $setup->isFreshInstall()) {
            return $this->failWith('This install already has branches, users or orders. Run this only on a new, empty install.');
        }

        $ask = fn (string $option, string $label, string $placeholder = '', bool $required = true) => $this->option($option)
            ?? ($this->input->isInteractive() ? text($label, placeholder: $placeholder, required: $required) : null);

        $input = [
            'business' => $ask('business', 'Business name'),
            'branch' => [
                'name' => $ask('branch', 'First branch name', 'e.g. Osu'),
                'phone' => $ask('branch-phone', 'Branch phone', '+233241234567'),
                'address' => $ask('address', 'Branch address'),
                'lat' => $ask('lat', 'Branch latitude', '5.5560'),
                'lng' => $ask('lng', 'Branch longitude', '-0.1969'),
                'opens_at' => $this->option('opens'),
                'closes_at' => $this->option('closes'),
            ],
            'owner' => [
                'name' => $ask('owner-name', 'Owner\'s name'),
                'email' => $ask('owner-email', 'Owner\'s email (their sign-in)'),
                'phone' => $ask('owner-phone', 'Owner\'s phone (optional)', required: false) ?: null,
            ],
            'support_email' => $ask('support-email', 'Solusys Digital support email (optional)', required: false) ?: null,
            // Every client chooses its own brand: no default colours (owner, 2026-11-24).
            'brand' => [
                'primary' => strtolower((string) $ask('primary-colour', 'Brand primary colour (#rrggbb)', '#0b7a3a')),
                'accent' => strtolower((string) $ask('accent-colour', 'Brand accent colour (#rrggbb)', '#fcd116')),
                'logo' => $ask('logo', 'Path to the logo file (PNG, JPG or WebP, up to 2 MB)'),
            ],
            'modules' => $this->modules(),
            'theme' => $this->option('theme') ?: WebsiteThemeService::DEFAULT,
        ];

        $validator = Validator::make($input, [
            'business' => ['required', 'string', 'max:80'],
            'branch.name' => ['required', 'string', 'max:255'],
            'branch.phone' => ['required', 'string', 'max:255'],
            'branch.address' => ['required', 'string', 'max:255'],
            'branch.lat' => ['required', 'numeric', 'between:-90,90'],
            'branch.lng' => ['required', 'numeric', 'between:-180,180'],
            'branch.opens_at' => ['required', 'date_format:H:i'],
            'branch.closes_at' => ['required', 'date_format:H:i'],
            'owner.name' => ['required', 'string', 'max:255'],
            'owner.email' => ['required', 'email', 'max:255'],
            'owner.phone' => ['nullable', 'string', 'max:30'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'theme' => ['required', Rule::in(array_keys(WebsiteThemeService::THEMES))],
            'brand.primary' => ['required', 'regex:/^#[0-9a-f]{6}$/'],
            'brand.accent' => ['required', 'regex:/^#[0-9a-f]{6}$/'],
            'brand.logo' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                $info = is_string($value) && is_file($value) && filesize($value) <= 2 * 1024 * 1024 ? @getimagesize($value) : false;
                if (! in_array($info['mime'] ?? null, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                    $fail('The logo must be a PNG, JPG or WebP image of up to 2 MB.');
                }
            }],
        ], [
            'brand.primary.regex' => 'The primary colour must be #rrggbb.',
            'brand.accent.regex' => 'The accent colour must be #rrggbb.',
            'theme.in' => 'The website look must be classic, photo or onepage.',
        ]);

        if ($validator->fails()) {
            return $this->failWith(implode(' ', $validator->errors()->all()));
        }

        $input['branch']['lat'] = (float) $input['branch']['lat'];
        $input['branch']['lng'] = (float) $input['branch']['lng'];

        try {
            $result = $setup->setUp($input);
        } catch (RuntimeException $e) {
            return $this->failWith($e->getMessage());
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => true,
                'business' => $input['business'],
                'branch' => $result['branch']->name,
                'modules' => $input['modules'],
                'theme' => $input['theme'],
                'accounts' => array_values(array_filter([
                    ['role' => 'owner', 'email' => $result['owner']['user']->email, 'temporary_password' => $result['owner']['temporary_password']],
                    $result['support'] ? ['role' => 'support', 'email' => $result['support']['user']->email, 'temporary_password' => $result['support']['temporary_password']] : null,
                ])),
            ]));

            return self::SUCCESS;
        }

        $this->info("{$input['business']} is set up, with {$result['branch']->name} as its first branch.");
        $this->line('Modules on: '.($input['modules'] ? implode(', ', array_map(fn ($m) => ModuleService::MODULES[$m]['label'], $input['modules'])) : 'none (POS only)'));
        $this->newLine();
        $this->line('Sign-in details (shown once; each must set a new password on first sign-in):');
        $this->table(['Account', 'Email', 'Temporary password'], array_values(array_filter([
            ['Owner', $result['owner']['user']->email, $result['owner']['temporary_password']],
            $result['support'] ? ['Solusys Digital support', $result['support']['user']->email, $result['support']['temporary_password']] : null,
        ])));

        return self::SUCCESS;
    }

    /** An error, as text or (with --json) as JSON. */
    private function failWith(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['ok' => false, 'error' => $message]));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function modules(): array
    {
        if ($this->option('modules') !== null) {
            return array_values(array_filter(array_map('trim', explode(',', (string) $this->option('modules')))));
        }

        if (! $this->input->isInteractive()) {
            return [];
        }

        return array_values(multiselect(
            'Modules to switch on (none = POS only)',
            collect(ModuleService::MODULES)->mapWithKeys(fn (array $module, string $key) => [$key => $module['label']])->all(),
            hint: 'Space to tick, Enter to finish. All can be changed later on the Modules page.',
        ));
    }
}
