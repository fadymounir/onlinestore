<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], [
            'rating.required' => 'اختر عدد النجوم.',
        ]);

        $user = Auth::user();

        if ($user->reviews()->where('product_id', $product->id)->exists()) {
            return back()->with('error', 'لقد قيّمت هذا المنتج بالفعل.');
        }

        // Link the review to a delivered order so the storefront can show
        // "شراء موثّق" honestly.
        $order = $user->orders()
            ->where('status', OrderStatus::Delivered->value)
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->latest('delivered_at')
            ->first();

        // When moderation is switched off in the settings, publish immediately.
        $needsApproval = (bool) Setting::get('reviews_need_approval', '1');

        Review::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order?->id,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? null,
            'is_approved' => ! $needsApproval,
            'approved_at' => $needsApproval ? null : now(),
        ]);

        return back()->with('success', $needsApproval
            ? 'شكراً لك. سيظهر تقييمك بعد مراجعته.'
            : 'شكراً لك. تم نشر تقييمك.');
    }
}
