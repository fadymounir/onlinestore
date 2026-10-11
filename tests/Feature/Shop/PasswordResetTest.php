<?php

namespace Tests\Feature\Shop;

use App\Models\User;
use App\Notifications\CustomerResetPassword;
use Database\Seeders\SettingSeeder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    private function customer(bool $active = true): User
    {
        return User::create([
            'name' => 'منى عبد الرحمن',
            'email' => 'mona@example.com',
            'phone' => '01011234501',
            'password' => 'old-password',
            'is_active' => $active,
        ]);
    }

    public function test_the_pages_render_and_login_links_to_them(): void
    {
        $this->get('/login')->assertOk()->assertSee('نسيت كلمة المرور؟');

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('أرسل رابط إعادة التعيين');

        $this->get('/reset-password/some-token?email=mona@example.com')
            ->assertOk()
            ->assertSee('تعيين كلمة مرور جديدة')
            ->assertSee('mona@example.com');
    }

    public function test_requesting_a_link_sends_the_arabic_notification(): void
    {
        Notification::fake();
        $user = $this->customer();

        $this->post('/forgot-password', ['email' => 'mona@example.com'])
            ->assertSessionHas('success');

        Notification::assertSentTo($user, CustomerResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'mona@example.com']);
    }

    public function test_the_email_is_entirely_arabic(): void
    {
        $user = $this->customer();

        $mail = (new CustomerResetPassword('tok'))->toMail($user);
        $rendered = (string) $mail->render();

        $this->assertStringContainsString('إعادة تعيين كلمة المرور', $mail->subject);
        $this->assertStringContainsString('أهلاً منى عبد الرحمن', $rendered);
        $this->assertStringContainsString('إعادة تعيين كلمة المرور', $rendered);
        $this->assertStringContainsString('مع تحياتنا', $rendered);
        $this->assertStringContainsString('متجري', $rendered);

        // Nothing may fall back to Laravel's untranslated English strings.
        foreach (['Reset Password Notification', 'Regards', 'Hello!', 'trouble clicking'] as $english) {
            $this->assertStringNotContainsString($english, $rendered);
        }
    }

    public function test_an_unknown_email_gets_the_same_answer_so_accounts_cannot_be_probed(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_a_deactivated_account_is_refused_rather_than_silently_mailed(): void
    {
        Notification::fake();
        $this->customer(active: false);

        $this->post('/forgot-password', ['email' => 'mona@example.com'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_the_full_cycle_changes_the_password_and_lets_them_in(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->customer();

        $token = null;
        Notification::fake();
        $this->post('/forgot-password', ['email' => $user->email]);
        Notification::assertSentTo($user, CustomerResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->assertNotNull($token);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/login')->assertSessionHas('success');

        Event::assertDispatched(PasswordReset::class);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertFalse(Hash::check('old-password', $user->password));

        // The token is single-use.
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->post('/login', ['email' => $user->email, 'password' => 'new-password-123'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_used_or_forged_token_is_rejected(): void
    {
        $user = $this->customer();

        $this->post('/reset-password', [
            'token' => 'definitely-not-a-real-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $user = $this->customer();

        Notification::fake();
        $this->post('/forgot-password', ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, CustomerResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        // Age the token past config('auth.passwords.users.expire') minutes.
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(config('auth.passwords.users.expire') + 5)]);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_the_new_password_is_validated(): void
    {
        $user = $this->customer();

        $this->post('/reset-password', [
            'token' => 'x', 'email' => $user->email,
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post('/reset-password', [
            'token' => 'x', 'email' => $user->email,
            'password' => 'long-enough-123', 'password_confirmation' => 'different-123',
        ])->assertSessionHasErrors('password');
    }

    public function test_signed_in_customers_are_kept_off_these_pages(): void
    {
        $this->actingAs($this->customer());

        $this->get('/forgot-password')->assertRedirect();
        $this->get('/reset-password/tok')->assertRedirect();
    }

    public function test_requests_are_rate_limited(): void
    {
        $this->customer();

        // The route allows 6 per minute; the 7th must be throttled.
        foreach (range(1, 6) as $i) {
            $this->post('/forgot-password', ['email' => 'mona@example.com']);
        }

        $this->post('/forgot-password', ['email' => 'mona@example.com'])
            ->assertStatus(429);
    }
}
