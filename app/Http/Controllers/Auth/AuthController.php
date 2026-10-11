<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'هذا الحساب موقوف. تواصل معنا للمساعدة.',
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        // Carry anything they put in the basket before signing in.
        $this->cart->mergeSessionIntoAccount();

        return redirect()->intended(route('home'))
            ->with('success', 'أهلاً بك، '.$user->name.'.');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^01[0-2,5]\d{8}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'phone.regex' => 'أدخل رقم موبايل مصري صحيح، مثل 01012345678.',
            'email.unique' => 'هذا البريد مسجّل بالفعل. سجّل الدخول بدلاً من ذلك.',
            'phone.unique' => 'هذا الرقم مسجّل بالفعل.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
            'password.min' => 'كلمة المرور 8 أحرف على الأقل.',
        ]);

        $user = User::create($data);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $this->cart->mergeSessionIntoAccount();

        return redirect()->intended(route('home'))
            ->with('success', 'تم إنشاء حسابك. أهلاً بك في متجرنا.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'تم تسجيل الخروج.');
    }
}
