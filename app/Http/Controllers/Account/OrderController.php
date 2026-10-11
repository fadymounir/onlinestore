<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
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

    public function show(Order $order): View
    {
        $this->authorizeOrder($order);

        $order->load('items', 'statusHistories');

        return view('account.order', ['order' => $order]);
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

        return back()->with('success', 'تم إلغاء الطلب.');
    }

    private function authorizeOrder(Order $order): void
    {
        abort_unless($order->user_id === Auth::id(), 404);
    }
}
