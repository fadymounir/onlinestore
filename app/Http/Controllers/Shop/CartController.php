<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutService $checkout,
    ) {}

    public function index(): View
    {
        $user = Auth::user();
        $subtotal = $this->cart->subtotal();

        return view('shop.cart', [
            'lines' => $this->cart->items(),
            'subtotal' => $subtotal,
            'coupon' => $user ? $this->checkout->resolveCoupon($user, $subtotal) : null,
            'couponCode' => $this->cart->couponCode(),
        ]);
    }

    public function add(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        if (! $variant->is_active || $variant->stock_quantity < 1) {
            return back()->with('error', 'هذا المقاس غير متاح حالياً.');
        }

        $this->cart->add($variant, $data['quantity'] ?? 1);

        return redirect()
            ->route('cart.index')
            ->with('success', 'تمت إضافة المنتج إلى السلة.');
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $this->cart->update($variant, $data['quantity']);

        return back()->with('success', 'تم تحديث السلة.');
    }

    public function remove(ProductVariant $variant): RedirectResponse
    {
        $this->cart->remove($variant);

        return back()->with('success', 'تمت إزالة المنتج من السلة.');
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $user = Auth::user();

        if (! $user) {
            return back()->with('error', 'سجّل الدخول أولاً لاستخدام الكوبون.');
        }

        $coupon = Coupon::where('code', strtoupper($data['code']))->first();

        if (! $coupon || ! $coupon->isValidFor($user, $this->cart->subtotal())) {
            return back()->with('error', 'الكوبون غير صالح أو لا ينطبق على طلبك.');
        }

        $this->cart->putCouponCode($coupon->code);

        return back()->with('success', 'تم تطبيق الكوبون «'.$coupon->code.'».');
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->forgetCoupon();

        return back()->with('success', 'تم إلغاء الكوبون.');
    }
}
