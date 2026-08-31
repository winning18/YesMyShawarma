<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Was a static public/robots.txt file — identical on every environment
 * since nginx's try_files serves a literal file before Laravel ever sees
 * the request, so staging (test/demo data, must never be indexed) was
 * getting the exact same "allow everything" file as production, plus a
 * hardcoded production domain in the Sitemap: line. This route replaces
 * it (the static file is deleted) so the two environments can actually
 * differ.
 */
class RobotsController extends Controller
{
    public function index(): Response
    {
        $lines = app()->environment('production')
            ? ["User-agent: *", "Disallow:", "", "Sitemap: ".route('sitemap')]
            : ["User-agent: *", "Disallow: /"];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
