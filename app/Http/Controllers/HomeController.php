<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home', [
            'branches' => Branch::where('is_active', true)->get(),
        ]);
    }
}
