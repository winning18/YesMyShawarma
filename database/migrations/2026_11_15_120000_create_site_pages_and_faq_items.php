<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Express POS step 2c: the website's words move out of the code.
     *
     *  - site_pages: one row per page (SitePageService::PAGES) holding its
     *    heading, search title, search description and text (Markdown).
     *  - faq_items: the FAQ page's questions, in order.
     *
     * Also adds the content.manage permission (owner, general manager) —
     * deploys never run seeders. Yes My Shawarma's current content
     * (database/seeders/data/site_content.php) is copied in only on an
     * install that already has orders, and never over anything already
     * there.
     */
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->longText('body')->nullable();
            $table->timestamps();
        });

        Schema::create('faq_items', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        (new RolesAndPermissionsSeeder)->run();

        if (! DB::table('orders')->exists()) {
            return;
        }

        $content = require database_path('seeders/data/site_content.php');
        $now = now();

        foreach ($content['pages'] as $slug => $page) {
            if (! DB::table('site_pages')->where('slug', $slug)->exists()) {
                DB::table('site_pages')->insert(['slug' => $slug, ...$page, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        if (! DB::table('faq_items')->exists()) {
            foreach ($content['faq'] as $i => $item) {
                DB::table('faq_items')->insert([...$item, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        foreach ($content['settings'] as $key => $value) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
        Schema::dropIfExists('site_pages');
        DB::table('settings')->whereIn('key', array_keys((require database_path('seeders/data/site_content.php'))['settings']))->delete();
    }
};
