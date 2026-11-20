<?php

namespace App\Http\Controllers;

use App\Services\Settings\BusinessProfile;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Help (Express POS step 10): the staff quick-start, the owner guide and
 * the go-live checklist, written for this business (its name, its modules)
 * and printable from the browser. The staff guide is for everyone signed
 * in; the other two for those who run the settings (settings.manage).
 */
class HelpController extends Controller
{
    public const GUIDES = [
        'staff' => ['title' => 'Staff quick-start', 'summary' => 'Sign in, take an order, the kitchen board, printing, closing the till.', 'manager_only' => false],
        'owner' => ['title' => 'Owner guide', 'summary' => 'Setting up the menu, staff, counters, printers and tax, and reading the reports.', 'manager_only' => true],
        'go-live' => ['title' => 'Go-live checklist', 'summary' => 'Everything to check before the first real customer, and on the day.', 'manager_only' => true],
    ];

    public function index(): View
    {
        return view('dashboard.help.index', [
            'guides' => collect(self::GUIDES)->filter(fn (array $guide) => ! $guide['manager_only'] || Gate::allows('settings.manage')),
        ]);
    }

    public function show(string $guide, BusinessProfile $profile): View
    {
        abort_unless(isset(self::GUIDES[$guide]), 404);
        if (self::GUIDES[$guide]['manager_only']) {
            Gate::authorize('settings.manage');
        }

        return view('dashboard.help.'.$guide, [
            'guide' => self::GUIDES[$guide],
            'business' => $profile->name(),
        ]);
    }
}
