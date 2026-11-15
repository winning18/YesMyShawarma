<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Express POS step 2a: what used to be written into the code for Yes My
     * Shawarma becomes its Business profile settings, so nothing on its
     * site changes when the code stops carrying it.
     *
     * Only on an install that already has orders — that's Yes My Shawarma
     * (and its staging). A new client's install migrates with no orders, so
     * it starts neutral instead of inheriting a shawarma shop's details.
     * Never overwrites a setting that's already there. Also grants the new
     * owner-only business.manage permission (deploys never run seeders).
     */
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();

        if (! DB::table('orders')->exists()) {
            return;
        }

        $values = [
            'business_name' => config('app.name'),
            'business_legal_name' => 'Yes! My Grill',
            'business_tagline' => 'Shawarma, burgers and more. Order online for pickup from our Accra branches.',
            'business_public_emails' => 'yesmyshawarma@gmail.com, yesmygrill@gmail.com',
            'business_contact_recipients' => 'yesmyshawarma@gmail.com, yesmygrill@gmail.com, info@yesmyshawarma.com',
            'business_instagram_url' => 'https://www.instagram.com/yesmygrill_shawarma',
            'business_facebook_url' => 'https://www.facebook.com/share/18ving6kE3/',
            'business_tiktok_url' => 'https://www.tiktok.com/@ymgrillnshawarma',
            'business_logo_path' => $this->copyImage('logo-web.png', 'business/logo-yes-my-shawarma.png'),
            'business_icon_path' => $this->copyImage('favicon.png', 'business/icon-yes-my-shawarma.png'),
            'business_main_branch_id' => (string) DB::table('branches')->where('slug', 'ga-odumase')->value('id'),
            'brand_primary' => '#fffa00',
            'brand_primary_light' => '#fff9b3',
            'brand_primary_dark' => '#ccc800',
            'brand_accent' => '#ff0000',
            'brand_accent_light' => '#ffe6e6',
            'brand_accent_dark' => '#9a0200',
            'order_reference_prefix_pos' => 'YMGS-POS',
            'order_reference_prefix_web' => 'YMGS-WEB',
            'order_reference_prefix_bolt_food' => 'YMGS-BOLT',
            'order_reference_prefix_whatsapp' => 'YMGS-WA',
            'order_reference_prefix_freebie' => 'YMGS-FREE',
        ];

        foreach (array_filter($values, fn ($value) => $value !== null && $value !== '') as $key => $value) {
            if (! DB::table('settings')->where('key', $key)->whereNotNull('value')->exists()) {
                DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }

    private function copyImage(string $from, string $to): ?string
    {
        $source = public_path('images/'.$from);

        if (! is_file($source)) {
            return null;
        }

        Storage::disk('public')->put($to, file_get_contents($source));

        return $to;
    }
};
