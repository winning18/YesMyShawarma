<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * The form posts to the same shared `profile.update` / `password.update`
     * routes staff use (see ProfileController / PasswordController) — this
     * exists only to give riders their own themed entry point, matching the
     * rest of the rider section. No separate update logic to duplicate.
     */
    public function edit(Request $request): View
    {
        return view('rider.profile.edit', [
            'user' => $request->user(),
        ]);
    }
}
