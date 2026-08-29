<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * The four static "Policy" footer pages — grouped under one controller
 * since each is a plain content view with no dynamic data, same as
 * FaqController/AboutController's simplicity, just four of them under
 * one footer nav group rather than four near-empty controller classes.
 */
class PolicyController extends Controller
{
    public function terms(): View
    {
        return view('policy.terms');
    }

    public function refunds(): View
    {
        return view('policy.refunds');
    }

    public function privacy(): View
    {
        return view('policy.privacy');
    }

    public function cookies(): View
    {
        return view('policy.cookies');
    }
}
