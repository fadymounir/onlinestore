<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Keeps products.total_stock true.
 *
 * Hooks created/updated/deleted rather than saved: Eloquent's increment() and
 * decrement() fire updating/updated only, never saved — and those are exactly
 * the calls checkout and Order::restoreStock() use to move stock, so a saved()
 * observer would miss every sale. created + updated also means one recount per
 * write rather than two.
 *
 * A raw query-builder update on product_variants still bypasses all of this;
 * call Product::recountStock() after one of those.
 */
class ProductVariantObserver
{
    public function created(ProductVariant $variant): void
    {
        Product::recountStock([$variant->product_id]);
    }

    public function updated(ProductVariant $variant): void
    {
        // A variant moved between products leaves the old one to recount too.
        $previous = $variant->getOriginal('product_id');

        Product::recountStock(array_values(array_filter([
            $variant->product_id,
            $previous !== $variant->product_id ? $previous : null,
        ])));
    }

    public function deleted(ProductVariant $variant): void
    {
        Product::recountStock([$variant->product_id]);
    }
}
