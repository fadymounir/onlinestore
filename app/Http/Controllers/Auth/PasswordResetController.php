<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'أدخل بريدك الإلكتروني.',
            'email.email' => 'أدخل بريداً إلكترونياً صحيحاً.',
        ]);

        // A deactivated account must not be recoverable by its owner.
        $user = User::where('email', $data['email'])->first();

        if ($user && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'هذا الحساب موقوف. تواصل معنا للمساعدة.',
            ]);
        }

        $status = Password::sendResetLink($data);

        // Always answer the same way: whether an address is registered is not
        // something an anonymous visitor should be able to probe.
        if ($status === Password::RESET_THROTTLED) {
            return back()->with('error', 'لقد طلبت رابطاً منذ قليل. انتظر دقيقة ثم حاول مرة أخرى.');
        }

        return back()->with(
            'success',
            'إذا كان البريد مسجّلاً لدينا فستصلك رسالة بها رابط إعادة التعيين خلال دقائق. تفقّد صندوق البريد المزعج أيضاً.',
        );
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->value(),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'كلمة المرور 8 أحرف على الأقل.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'رابط إعادة التعيين غير صالح أو انتهت صلاحيته. اطلب رابطاً جديداً.',
            ]);
        }

        return redirect()
            ->route('login')
            ->with('success', 'تم تعيين كلمة المرور الجديدة. يمكنك الدخول الآن.');
    }
}
