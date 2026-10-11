<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(): View
    {
        return view('shop.wishlist', [
            'products' => Auth::user()
                ->wishlist()
                ->with('primaryImage', 'brand', 'variants')
                ->paginate(12),
        ]);
    }

    public function toggle(Product $product): RedirectResponse
    {
        $changes = Auth::user()->wishlist()->toggle($product->id);

        return back()->with(
            'success',
            $changes['attached'] !== [] ? 'أُضيف إلى المفضّلة.' : 'أُزيل من المفضّلة.',
        );
    }
}
