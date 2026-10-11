<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Request $request, Product $product): View
    {
        abort_unless($product->is_active, 404);

        $product->load([
            'images',
            'brand',
            'category.parent',
            'attributes.values',
            'variants.attributeValues',
            'approvedReviews.user',
        ]);

        // Variant choice travels in the query string (?v=12&v=18) so the page
        // stays shareable and works without JavaScript.
        $chosen = collect($request->query('v', []))->map(fn ($id) => (int) $id)->filter();

        $selected = $this->matchVariant($product, $chosen);

        return view('shop.product', [
            'product' => $product,
            'chosen' => $chosen,
            'selected' => $selected,
            'related' => Product::published()
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->with('primaryImage', 'brand', 'variants')
                ->take(4)
                ->get(),
        ]);
    }

    /** The variant whose value set matches every chosen value, if any. */
    private function matchVariant(Product $product, $chosen): ?ProductVariant
    {
        if ($chosen->isEmpty()) {
            return $product->variants->firstWhere(fn (ProductVariant $v) => $v->is_active && $v->stock_quantity > 0)
                ?? $product->variants->first();
        }

        return $product->variants->first(function (ProductVariant $variant) use ($chosen): bool {
            $ids = $variant->attributeValues->pluck('id');

            return $ids->count() === $chosen->count()
                && $chosen->diff($ids)->isEmpty();
        });
    }
}
