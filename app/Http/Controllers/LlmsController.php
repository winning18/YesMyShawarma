<?php

namespace App\Http\Controllers;

use App\Services\Content\SitePageService;
use Illuminate\Http\Response;

/**
 * /llms.txt — a plain-text summary for AI assistants (see
 * .claude/rules/aeo.md). Written under Website content, or built from the
 * business profile and the pages that exist.
 */
class LlmsController extends Controller
{
    public function __invoke(SitePageService $pages): Response
    {
        return response($pages->llmsText(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
