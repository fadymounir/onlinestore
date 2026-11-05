<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        $methods = PaymentMethod::enabled();

        return view('shop.checkout', [
            'lines' => $this->cart->items(),
            'addresses' => $addresses,
            'address' => $address,
            'totals' => $this->checkout->totals($user, $address),
            'methods' => $methods,
            // Whatever a failed submit chose, else the first method on offer.
            'selectedMethod' => PaymentMethod::tryFrom((string) old('payment_method')) ?? ($methods[0] ?? null),
            'transferInstructions' => Setting::get('deposit_instructions'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'deposit_receipt' => [
                'nullable',
                Rule::requiredIf(
                    fn (): bool => $request->input('payment_method') === PaymentMethod::Deposit->value,
                ),
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
            'transfer_amount' => [
                'nullable',
                Rule::requiredIf(
                    fn (): bool => $request->input('payment_method') === PaymentMethod::Deposit->value,
                ),
                'integer',
            ],
            'transfer_reference' => ['nullable', 'string', 'max:100'],
        ], [
            'address_id.required' => 'اختر عنوان التوصيل.',
            'transfer_amount.required' => 'اكتب المبلغ الذي حوّلته.',
            'transfer_amount.integer' => 'اكتب المبلغ المحوَّل بالجنيه الصحيح، بدون كسور.',
            'deposit_receipt.required' => 'أرفق صورة إيصال التحويل لإتمام الطلب.',
            'deposit_receipt.mimes' => 'الإيصال يجب أن يكون صورة (JPG أو PNG أو WEBP) أو ملف PDF.',
            'deposit_receipt.max' => 'حجم الإيصال يجب ألا يزيد عن 5 ميجابايت.',
        ]);

        if ($shortfall = $this->shortfall()) {
            return back()->with('error', $shortfall);
        }

        $method = PaymentMethod::tryFrom((string) ($data['payment_method'] ?? ''))
            ?? PaymentMethod::CashOnDelivery;

        if (! $method->isEnabled()) {
            return back()->with('error', $method->disabledMessage());
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

        $transferAmount = null;

        if ($method->requiresReceipt()) {
            // Whole pounds, from the configured share up to the whole order: a
            // customer who would rather settle it all now may do so. Checked
            // before the receipt is filed, so a refusal leaves no orphan file.
            $total = $this->checkout->totals($user, $address)['total'];

            $transferAmount = $this->checkout->resolveDeposit($data['transfer_amount'] ?? null, $total);

            if ($transferAmount === null) {
                return back()->withInput()->withErrors([
                    'transfer_amount' => $this->checkout->depositRangeMessage($total),
                ]);
            }
        }

        $receiptPath = $method->requiresReceipt()
            ? $this->checkout->storeReceipt($request->file('deposit_receipt'))
            : null;

        try {
            $order = $this->checkout->place(
                $user,
                $address,
                $data['note'] ?? null,
                $method,
                $receiptPath,
                $data['transfer_reference'] ?? null,
                $transferAmount,
            );
        } catch (RuntimeException $e) {
            // The order never happened; don't leave its receipt behind.
            if ($receiptPath) {
                Storage::disk(Order::RECEIPT_DISK)->delete($receiptPath);
            }

            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('account.orders.show', $order)
            ->with('success', $this->confirmation($order));
    }

    /** What the customer reads after placing the order. */
    private function confirmation(Order $order): string
    {
        if (! $order->isDeposit()) {
            return 'تم استلام طلبك رقم '.$order->order_number.'. سنتواصل معك لتأكيده.';
        }

        if ($order->depositCoversTotal()) {
            return 'تم استلام طلبك رقم '.$order->order_number
                .' مع إيصال تحويل قيمة الطلب كاملة ('.number_format($order->depositAmount(), 2).' ج.م).'
                .' سنراجع التحويل ونؤكد الطلب، ولا شيء يُدفع عند الاستلام.';
        }

        return 'تم استلام طلبك رقم '.$order->order_number
            .' مع إيصال التحويل. سنراجع المبلغ المحوَّل ('.number_format($order->depositAmount(), 2).' ج.م)'
            .' ونؤكد الطلب، والمتبقي '
            .number_format(round((float) $order->grand_total - $order->depositAmount(), 2), 2)
            .' ج.م يُدفع عند الاستلام.';
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
