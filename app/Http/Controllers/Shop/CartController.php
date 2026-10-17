<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Concerns\SendsFragments;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CartController extends Controller
{
    use SendsFragments;

    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutService $checkout,
    ) {}

    public function index(): View
    {
        return view('shop.cart', $this->panelData());
    }

    public function add(Request $request, ProductVariant $variant): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        if (! $variant->is_active || $variant->stock_quantity < 1) {
            return $this->wantsFragment($request)
                ? $this->fragment(message: 'هذا المقاس غير متاح حالياً.', level: 'error')
                : back()->with('error', 'هذا المقاس غير متاح حالياً.');
        }

        $this->cart->add($variant, $data['quantity'] ?? 1);

        $message = 'تمت إضافة المنتج إلى السلة.';

        // Over AJAX the customer stays on the product page — a toast and the
        // header badge are the whole feedback, so no redirect to the cart.
        return $this->wantsFragment($request)
            ? $this->fragment(message: $message, extra: ['cartCount' => $this->cart->count()])
            : redirect()->route('cart.index')->with('success', $message);
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $this->cart->update($variant, $data['quantity']);

        return $this->respond($request, 'تم تحديث السلة.');
    }

    public function remove(Request $request, ProductVariant $variant): RedirectResponse|JsonResponse
    {
        $this->cart->remove($variant);

        return $this->respond($request, 'تمت إزالة المنتج من السلة.');
    }

    public function applyCoupon(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $user = Auth::user();

        if (! $user) {
            return $this->respond($request, 'سجّل الدخول أولاً لاستخدام الكوبون.', 'error');
        }

        $coupon = Coupon::where('code', strtoupper($data['code']))->first();

        if (! $coupon || ! $coupon->isValidFor($user, $this->cart->subtotal())) {
            return $this->respond($request, 'الكوبون غير صالح أو لا ينطبق على طلبك.', 'error');
        }

        $this->cart->putCouponCode($coupon->code);

        return $this->respond($request, 'تم تطبيق الكوبون «'.$coupon->code.'».');
    }

    public function removeCoupon(Request $request): RedirectResponse|JsonResponse
    {
        $this->cart->forgetCoupon();

        return $this->respond($request, 'تم إلغاء الكوبون.');
    }

    /**
     * Answer a cart mutation made from the cart page itself.
     *
     * Everything visible there — line totals, the subtotal, the discount row,
     * the coupon box — is re-rendered from the one partial and handed back as a
     * single swap, so the page cannot drift out of step with the server.
     */
    private function respond(Request $request, string $message, string $level = 'success'): RedirectResponse|JsonResponse
    {
        if (! $this->wantsFragment($request)) {
            return back()->with($level, $message);
        }

        return $this->fragment(
            swap: ['#cart-panel' => view('shop.partials.cart-panel', $this->panelData())->render()],
            message: $message,
            level: $level,
            extra: ['cartCount' => $this->cart->count()],
        );
    }

    /** @return array<string, mixed> the cart panel's view data */
    private function panelData(): array
    {
        $user = Auth::user();
        $subtotal = $this->cart->subtotal();

        return [
            'lines' => $this->cart->items(),
            'subtotal' => $subtotal,
            'coupon' => $user ? $this->checkout->resolveCoupon($user, $subtotal) : null,
            'couponCode' => $this->cart->couponCode(),
        ];
    }
}
