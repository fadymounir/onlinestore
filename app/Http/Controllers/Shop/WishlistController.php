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

    /**
     * The saved products the shop can still sell.
     *
     * `published()` rather than a stock check of its own: the wishlist is a
     * listing like any other, and the one scope decides what every listing is
     * allowed to show. It also takes out anything the dashboard has since
     * deactivated or unpublished — cards that led to a 404 before, because
     * ProductController refuses those.
     *
     * What was saved is untouched. A product hidden here is still in the
     * wishlist, and comes back by itself the day it is restocked, so a
     * customer never loses the thing they asked to be reminded of.
     */
    public function index(Request $request): View|JsonResponse
    {
        $saved = Auth::user()->wishlist();

        $products = (clone $saved)
            ->published()
            ->with('primaryImage', 'images', 'brand', 'variants')
            ->paginate(12);

        // Scrolled to the end of the grid: the next twelve cards, added to the
        // ones on screen, and a fresh link to the page after that. Same shape
        // as the catalogue's, because it is the same grid and the same partial.
        if ($this->wantsFragment($request) && $this->wantsAppend($request)) {
            return $this->fragment(
                swap: ['#wishlist-more' => view('shop.partials.load-more', [
                    'products' => $products,
                    'grid' => '#wishlist-grid',
                ])->render()],
                append: ['#wishlist-grid' => view('shop.partials.product-cards', ['products' => $products])->render()],
            );
        }

        return view('shop.wishlist', [
            'products' => $products,
            // Told to the customer only when it is the whole explanation for
            // an empty page: "you saved nothing" and "none of it is in stock"
            // are different things, and the second must not read as the first.
            'hidden' => $products->total() === 0 ? (clone $saved)->count() : 0,
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
