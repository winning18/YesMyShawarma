<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One page of the customer website's own words (Express POS step 2c) —
 * see SitePageService::PAGES for the slugs and what each one holds.
 */
#[Fillable(['slug', 'title', 'meta_title', 'meta_description', 'body'])]
class SitePage extends Model
{
    //
}
