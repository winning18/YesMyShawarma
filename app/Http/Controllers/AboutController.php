<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\StaffMember;
use Illuminate\View\View;

class AboutController extends Controller
{
    private const FOUNDING_YEAR = 2023;

    private const CUSTOMER_COUNT_LABEL = '5,000+';

    public function index(): View
    {
        return view('about', [
            'branchCount' => Branch::count(),
            'yearsOfOperation' => now()->year - self::FOUNDING_YEAR,
            'customerCountLabel' => self::CUSTOMER_COUNT_LABEL,
            'staffMembers' => StaffMember::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
