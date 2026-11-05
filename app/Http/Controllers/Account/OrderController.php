<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        return view('account.orders', [
            'orders' => Auth::user()
                ->orders()
                ->withCount('items')
                ->latest('placed_at')
                ->paginate(10),
        ]);
    }

    public function show(Order $order, CheckoutService $checkout): View
    {
        $this->authorizeOrder($order);

        $order->load('items', 'statusHistories');

        return view('account.order', [
            'order' => $order,
            // The whole-pound window a replacement transfer has to fall in,
            // from the setting as it stands today.
            'transferWindow' => $checkout->depositWindow((float) $order->grand_total),
        ]);
    }

    /**
     * Replaces the transfer receipt on a deposit order — the way back for a
     * customer whose first upload was rejected (wrong amount, unreadable
     * screenshot). Allowed until the deposit has been accepted; after that the
     * figures are settled and only the admin may touch them.
     */
    public function uploadReceipt(Request $request, Order $order, CheckoutService $checkout): RedirectResponse
    {
        $this->authorizeOrder($order);

        if (! $order->canReplaceDepositReceipt()) {
            return back()->with('error', 'لا يمكن تعديل إيصال التحويل لهذا الطلب.');
        }

        $data = $request->validate([
            'deposit_receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'transfer_amount' => ['required', 'integer'],
            'transfer_reference' => ['nullable', 'string', 'max:100'],
        ], [
            'deposit_receipt.required' => 'أرفق صورة إيصال التحويل.',
            'deposit_receipt.mimes' => 'الإيصال يجب أن يكون صورة (JPG أو PNG أو WEBP) أو ملف PDF.',
            'deposit_receipt.max' => 'حجم الإيصال يجب ألا يزيد عن 5 ميجابايت.',
            'transfer_amount.required' => 'اكتب المبلغ الذي حوّلته.',
            'transfer_amount.integer' => 'اكتب المبلغ المحوَّل بالجنيه الصحيح، بدون كسور.',
        ]);

        // A second transfer may be for a different figure — the first one was
        // very likely rejected over its amount.
        $total = round((float) $order->grand_total, 2);
        $amount = $checkout->resolveDeposit($data['transfer_amount'], $total);

        if ($amount === null) {
            return back()->withInput()->withErrors([
                'transfer_amount' => $checkout->depositRangeMessage($total),
            ]);
        }

        $order->attachDepositReceipt(
            $checkout->storeReceipt($request->file('deposit_receipt')),
            $data['transfer_reference'] ?? null,
            $amount,
        );

        return back()->with('success', 'تم استلام إيصال التحويل وهو الآن قيد المراجعة.');
    }

    /** A customer may cancel only while the order has not been shipped. */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($order);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'من فضلك اذكر سبب الإلغاء.',
        ]);

        if (! $order->status->canMoveTo(OrderStatus::Cancelled)) {
            return back()->with('error', 'لا يمكن إلغاء الطلب في هذه المرحلة. تواصل معنا.');
        }

        $order->transitionTo(OrderStatus::Cancelled, $data['reason']);
        $order->restoreStock();

        // Say so at once if the store is holding money of theirs: the order
        // page carries the same line, but this is the moment they ask.
        return back()->with('success', $order->awaitingRefund()
            ? 'تم إلغاء الطلب، والمبلغ المدفوع '
                .number_format($order->refundableAmount(), 2).' ج.م سيُرد إليك.'
            : 'تم إلغاء الطلب.');
    }

    private function authorizeOrder(Order $order): void
    {
        abort_unless($order->user_id === Auth::id(), 404);
    }
}
