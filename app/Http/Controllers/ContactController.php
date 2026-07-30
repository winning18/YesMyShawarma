<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        return view('contact', [
            'branches' => Branch::where('is_active', true)->get(),
        ]);
    }
}
