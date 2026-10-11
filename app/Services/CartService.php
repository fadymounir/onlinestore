<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * One cart, two backings. A signed-in customer gets the `carts` row the schema
 * expects; a guest gets a session basket so they can shop before registering
 * (checkout itself stays accounts-only). Signing in merges the two.
 */
class CartService
{
    private const SESSION_KEY = 'cart';

    private const COUPON_KEY = 'cart_coupon';

    public function cart(): ?Cart
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    /** @return Collection<int, array{variant: ProductVariant, quantity: int}> */
    public function items(): Collection
    {
        if ($cart = $this->cart()) {
            return $cart->items()
                ->with('variant.product.primaryImage', 'variant.attributeValues')
                ->get()
                ->filter(fn ($item) => $item->variant !== null)
                ->map(fn ($item) => ['variant' => $item->variant, 'quantity' => $item->quantity])
                ->values();
        }

        $basket = Session::get(self::SESSION_KEY, []);

        if ($basket === []) {
            return collect();
        }

        $variants = ProductVariant::with('product.primaryImage', 'attributeValues')
            ->whereIn('id', array_keys($basket))
            ->get()
            ->keyBy('id');

        return collect($basket)
            ->map(fn (int $quantity, int $variantId) => $variants->has($variantId)
                ? ['variant' => $variants[$variantId], 'quantity' => $quantity]
                : null)
            ->filter()
            ->values();
    }

    public function add(ProductVariant $variant, int $quantity = 1): void
    {
        $quantity = max(1, $quantity);

        if ($cart = $this->cart()) {
            $item = $cart->items()->firstOrNew(['product_variant_id' => $variant->id]);

            // A new CartItem already carries the model's default quantity of 1,
            // so start from zero unless the row really exists.
            $current = $item->exists ? $item->quantity : 0;

            $item->quantity = min($variant->stock_quantity, $current + $quantity);
            $item->save();

            return;
        }

        $basket = Session::get(self::SESSION_KEY, []);
        $basket[$variant->id] = min($variant->stock_quantity, ($basket[$variant->id] ?? 0) + $quantity);

        Session::put(self::SESSION_KEY, $basket);
    }

    public function update(ProductVariant $variant, int $quantity): void
    {
        if ($quantity < 1) {
            $this->remove($variant);

            return;
        }

        // Never allow more than is on the shelf.
        $quantity = min($quantity, $variant->stock_quantity);

        if ($cart = $this->cart()) {
            $cart->items()->where('product_variant_id', $variant->id)->update(['quantity' => $quantity]);

            return;
        }

        $basket = Session::get(self::SESSION_KEY, []);

        if (isset($basket[$variant->id])) {
            $basket[$variant->id] = $quantity;
            Session::put(self::SESSION_KEY, $basket);
        }
    }

    public function remove(ProductVariant $variant): void
    {
        if ($cart = $this->cart()) {
            $cart->items()->where('product_variant_id', $variant->id)->delete();

            return;
        }

        $basket = Session::get(self::SESSION_KEY, []);
        unset($basket[$variant->id]);
        Session::put(self::SESSION_KEY, $basket);
    }

    public function clear(): void
    {
        $this->cart()?->items()->delete();

        Session::forget(self::SESSION_KEY);
        Session::forget(self::COUPON_KEY);
    }

    public function count(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function subtotal(): float
    {
        return (float) $this->items()->sum(
            fn (array $line): float => (float) $line['variant']->price * $line['quantity'],
        );
    }

    public function isEmpty(): bool
    {
        return $this->items()->isEmpty();
    }

    /** Folds a guest's session basket into their cart right after signing in. */
    public function mergeSessionIntoAccount(): void
    {
        $basket = Session::get(self::SESSION_KEY, []);

        if ($basket === [] || ! ($cart = $this->cart())) {
            return;
        }

        $variants = ProductVariant::whereIn('id', array_keys($basket))->get()->keyBy('id');

        foreach ($basket as $variantId => $quantity) {
            if (! $variants->has($variantId)) {
                continue;
            }

            $item = $cart->items()->firstOrNew(['product_variant_id' => $variantId]);

            $current = $item->exists ? $item->quantity : 0;

            $item->quantity = min(
                $variants[$variantId]->stock_quantity,
                $current + $quantity,
            );
            $item->save();
        }

        Session::forget(self::SESSION_KEY);
    }

    public function couponCode(): ?string
    {
        return Session::get(self::COUPON_KEY);
    }

    public function putCouponCode(string $code): void
    {
        Session::put(self::COUPON_KEY, strtoupper($code));
    }

    public function forgetCoupon(): void
    {
        Session::forget(self::COUPON_KEY);
    }
}
