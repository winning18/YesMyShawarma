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
    /**
     * Named explicitly rather than left to inherit the blanket
     * "User-agent: *" rule below — the business wants AI answer engines
     * citing/training on this site's menu, hours, and policies (see
     * public/llms.txt), so that has to be a visible, deliberate choice
     * here, not an accident of the wildcard rule happening to allow
     * everything today. If the wildcard rule ever tightens, these stay
     * allowed unless someone removes them on purpose.
     *
     * @var list<string>
     */
    private const AI_CRAWLERS = [
        'GPTBot', 'ChatGPT-User', 'OAI-SearchBot',
        'ClaudeBot', 'Claude-Web', 'anthropic-ai',
        'PerplexityBot', 'Perplexity-User',
        'Google-Extended', 'CCBot',
    ];

    public function index(): Response
    {
        if (! app()->environment('production')) {
            return response("User-agent: *\nDisallow: /\n")->header('Content-Type', 'text/plain');
        }

        $lines = [];

        foreach (self::AI_CRAWLERS as $bot) {
            $lines[] = "User-agent: {$bot}";
            $lines[] = "Disallow:";
            $lines[] = "";
        }

        $lines[] = "User-agent: *";
        $lines[] = "Disallow:";
        $lines[] = "";
        $lines[] = "Sitemap: ".route('sitemap');

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
