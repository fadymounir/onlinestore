<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Concerns\SendsFragments;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WishlistController extends Controller
{
    use SendsFragments;

    public function index(): View
    {
        return view('shop.wishlist', [
            'products' => Auth::user()
                ->wishlist()
                ->with('primaryImage', 'brand', 'variants')
                ->paginate(12),
        ]);
    }

    public function toggle(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $changes = Auth::user()->wishlist()->toggle($product->id);
        $added = $changes['attached'] !== [];
        $message = $added ? 'أُضيف إلى المفضّلة.' : 'أُزيل من المفضّلة.';

        // `inWishlist` lets the heart fill or empty in place, so the card gives
        // feedback without a reload.
        return $this->wantsFragment($request)
            ? $this->fragment(message: $message, extra: ['inWishlist' => $added])
            : back()->with('success', $message);
    }
}
