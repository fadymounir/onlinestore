<?php

namespace Tests\Feature\Shop;

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The footer's social row.
 *
 * Every network is a setting, so the row has to survive a shop that has filled
 * in none of them — and the WhatsApp link has to survive the number being
 * typed the way an Egyptian shop writes it rather than the way wa.me wants it.
 */
class FooterSocialLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_a_local_egyptian_number_becomes_an_international_wa_me_link(): void
    {
        Setting::put('store_whatsapp', '0100 123 4567');

        $this->get(route('home'))
            ->assertOk()
            // Spaces stripped, the national trunk 0 swapped for Egypt's 20.
            ->assertSee('https://wa.me/201001234567', false)
            ->assertSee('aria-label="واتساب"', false);
    }

    public function test_a_number_already_in_international_form_is_left_alone(): void
    {
        Setting::put('store_whatsapp', '+20 100 123 4567');

        $this->get(route('home'))->assertSee('https://wa.me/201001234567', false);
    }

    public function test_the_networks_an_admin_filled_in_are_the_ones_that_render(): void
    {
        Setting::put('facebook_url', 'https://facebook.com/shop');
        Setting::put('tiktok_url', 'https://tiktok.com/@shop');

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('https://facebook.com/shop', false)
            ->assertSee('aria-label="فيسبوك"', false)
            ->assertSee('aria-label="تيك توك"', false)
            // Instagram was never set, so it has no icon to click.
            ->assertDontSee('aria-label="إنستغرام"', false);
    }

    public function test_the_row_disappears_entirely_when_nothing_is_configured(): void
    {
        Setting::put('store_whatsapp', null);

        $response = $this->get(route('home'))->assertOk();

        $response->assertDontSee('aria-label="واتساب"', false)
            ->assertDontSee('wa.me', false)
            ->assertDontSee('aria-label="فيسبوك"', false);
    }
}
