<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutService $checkout,
    ) {}

    public function index(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'سلتك فارغة.');
        }

        if ($shortfall = $this->shortfall()) {
            return redirect()->route('cart.index')->with('error', $shortfall);
        }

        $user = Auth::user();
        $addresses = $user->addresses()->with('governorate.shippingZone', 'city')->get();
        $address = $addresses->firstWhere('is_default', true) ?? $addresses->first();

        return view('shop.checkout', [
            'lines' => $this->cart->items(),
            'addresses' => $addresses,
            'address' => $address,
            'totals' => $this->checkout->totals($user, $address),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'address_id.required' => 'اختر عنوان التوصيل.',
        ]);

        if ($shortfall = $this->shortfall()) {
            return back()->with('error', $shortfall);
        }

        if (! (bool) Setting::get('cod_enabled', '1')) {
            return back()->with('error', 'الدفع عند الاستلام متوقّف حالياً. تواصل معنا لإتمام الطلب.');
        }

        $user = Auth::user();

        /** @var Address|null $address */
        $address = $user->addresses()->with('governorate.shippingZone', 'city')->find($data['address_id']);

        if (! $address) {
            return back()->with('error', 'عنوان التوصيل غير صحيح.');
        }

        if (! $address->governorate?->shippingZone) {
            return back()->with('error', 'لا يوجد شحن متاح لهذه المحافظة حالياً. اختر عنواناً آخر.');
        }

        try {
            $order = $this->checkout->place($user, $address, $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('account.orders.show', $order)
            ->with('success', 'تم استلام طلبك رقم '.$order->order_number.'. سنتواصل معك لتأكيده.');
    }

    /** The `min_order_total` setting, as a message, or null when satisfied. */
    private function shortfall(): ?string
    {
        $minimum = (float) Setting::get('min_order_total', 0);

        if ($minimum <= 0 || $this->cart->subtotal() >= $minimum) {
            return null;
        }

        return 'الحد الأدنى للطلب '.number_format($minimum, 2).' ج.م. أضف منتجات أخرى لإتمام الشراء.';
    }
}
