<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Content\WebsiteThemeService;
use App\Services\Modules\ModuleService;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Website looks (owner, 2026-11-24): Classic (the default), Photo-led and
 * One-page, chosen under Website content, open to every business.
 */
class WebsiteThemeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $wrap;

    private MenuItem $water;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $wraps = Category::create(['name' => 'Wraps', 'slug' => 'wraps', 'sort_order' => 1]);
        $drinks = Category::create(['name' => 'Drinks', 'slug' => 'drinks', 'sort_order' => 2, 'is_beverage' => true]);
        $this->wrap = MenuItem::create(['category_id' => $wraps->id, 'name' => 'Chicken Wrap', 'slug' => 'chicken-wrap', 'base_price' => 5000]);
        $this->water = MenuItem::create(['category_id' => $drinks->id, 'name' => 'Water', 'slug' => 'water', 'base_price' => 500]);
        $this->wrap->optionGroups()->create(['name' => 'Size', 'min_select' => 1, 'max_select' => 1, 'is_required' => true])
            ->options()->create(['name' => 'Large', 'price_delta' => 1000]);
        $this->branch->menuItems()->attach([$this->wrap->id => ['is_available' => true], $this->water->id => ['is_available' => true]]);
    }

    private function useTheme(string $theme): void
    {
        app(SettingsService::class)->set(SettingsService::WEBSITE_THEME, $theme);
    }

    private function owner(): User
    {
        $owner = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $owner->assignRole('owner');

        return $owner;
    }

    public function test_classic_is_the_default_and_an_unknown_value_falls_back_to_it(): void
    {
        $themes = app(WebsiteThemeService::class);
        $this->assertSame('classic', $themes->chosen());

        $this->useTheme('neon');
        $this->assertSame('classic', $themes->chosen());

        $this->get(route('home'))->assertOk()->assertSee('theme-classic', false)->assertDontSee('/fonts/fraunces', false);
    }

    public function test_every_theme_draws_the_home_menu_and_item_pages(): void
    {
        foreach (array_keys(WebsiteThemeService::THEMES) as $theme) {
            $this->useTheme($theme);

            $this->get(route('home'))->assertOk()->assertSee('theme-'.$theme, false);
            $this->get(route('menu.index'))->assertOk()->assertSee('Chicken Wrap')->assertSee('Water');
            $this->get(route('menu.show', $this->wrap))->assertOk()->assertSee('Chicken Wrap')->assertSee('Large');
        }
    }

    public function test_photo_led_and_one_page_have_their_own_pages(): void
    {
        $this->useTheme('photo');
        $this->get(route('home'))->assertSee('Find a branch')->assertSee('Explore the menu');
        $this->get(route('menu.index'))->assertSee('cat-wraps', false);

        $this->useTheme('onepage');
        $home = $this->get(route('home'));
        $home->assertSee('id="menu"', false)->assertSee('id="branches"', false)->assertSee('id="contact"', false)
            ->assertSee('Chicken Wrap')->assertSee('Osu');
        // The menu nav scrolls to the home page's sections.
        $home->assertSee(route('home').'#branches', false);
    }

    public function test_quick_add_only_for_items_without_options_and_it_returns_to_the_category(): void
    {
        $this->useTheme('photo');

        $page = $this->get(route('menu.index'))->getContent();
        $this->assertStringContainsString('value="cat-drinks"', $page, 'Water has a quick Add');
        $this->assertStringNotContainsString('value="cat-wraps"', $page, 'the wrap needs its size chosen first');

        $this->from(route('menu.index'))->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->water->id, 'quantity' => 1, 'return_anchor' => 'cat-drinks',
        ])->assertRedirect(route('menu.index').'#cat-drinks');

        $this->from(route('menu.index'))->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->water->id, 'quantity' => 1, 'return_anchor' => '"><script>',
        ])->assertSessionHasErrors('return_anchor');
    }

    public function test_the_owner_chooses_the_look(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('dashboard.website-look.edit'))->assertOk()->assertSee('Photo-led')->assertSee('One-page')->assertSee('Live now');

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branch->id])
            ->put(route('dashboard.website-look.update'), ['theme' => 'onepage'])->assertSessionHasNoErrors();
        $this->assertSame('onepage', app(WebsiteThemeService::class)->chosen());

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branch->id])
            ->put(route('dashboard.website-look.update'), ['theme' => 'neon'])->assertSessionHasErrors('theme');
    }

    public function test_a_manager_cannot_change_the_look(): void
    {
        $manager = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $manager->assignRole('manager');

        $this->actingAs($manager)->withSession(['current_branch_id' => $this->branch->id])
            ->put(route('dashboard.website-look.update'), ['theme' => 'photo'])->assertForbidden();
    }

    public function test_a_signed_preview_shows_another_look_in_this_browser_only(): void
    {
        $url = app(WebsiteThemeService::class)->previewUrl('photo');

        $this->get($url)->assertRedirect(route('home'));
        $this->get(route('home'))->assertSee('theme-photo', false)->assertSee('Previewing the Photo-led look');
        $this->assertSame('classic', app(WebsiteThemeService::class)->chosen(), 'customers still see Classic');

        $this->get(route('home', ['theme_preview' => 'off']))->assertRedirect(route('home'));
        $this->get(route('home'))->assertSee('theme-classic', false);
    }

    public function test_a_made_up_or_expired_preview_link_does_nothing(): void
    {
        $this->get(route('home', ['theme_preview' => 'photo', 'until' => now()->addHour()->timestamp, 'sig' => 'guess']));
        $this->get(route('home'))->assertSee('theme-classic', false);

        $this->travel(-2)->hours();
        $expired = app(WebsiteThemeService::class)->previewUrl('photo');
        $this->travelBack();
        $this->get($expired);
        $this->get(route('home'))->assertSee('theme-classic', false);
    }

    public function test_with_the_website_module_off_the_look_page_is_gone(): void
    {
        app(ModuleService::class)->set(ModuleService::WEBSITE, false);

        $this->actingAs($this->owner())->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('dashboard.website-look.edit'))->assertNotFound();
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_text_on_brand_colours_stays_readable(): void
    {
        app(SettingsService::class)->set('brand_primary', '#fcd116');
        app(SettingsService::class)->set('brand_accent', '#1a237e');

        $this->get(route('home'))
            ->assertSee('<style>html:root{--brand-primary:252 209 22;', false)
            ->assertSee('--brand-primary-ink:0 0 0;', false)
            ->assertSee('--brand-accent-ink:255 255 255;', false);
    }
}
