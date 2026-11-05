<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The server half of the storefront's AJAX layer.
 *
 * The storefront is plain Blade forms that work without JavaScript; resources/js/app.js
 * intercepts them and marks its requests with an `X-Fragment` header. A
 * controller that sees that header answers with JSON instead of a redirect:
 *
 *   {
 *     "message": "تمت إضافة المنتج إلى السلة.",   // shown as a toast
 *     "level":   "success" | "error",
 *     "cartCount": 3,                              // repaints the header badge
 *     "swap":    { "#cart-panel": "<html…>" },     // innerHTML per selector
 *     "append":  { "#catalog-grid": "<html…>" },   // added after what is there
 *     "url":     "https://…/products?page=2"       // pushed into history
 *   }
 *
 * `swap` and `append` differ only in what they do to the target, and a
 * response may carry both: a listing loading its next page appends the cards
 * and swaps the link that asked for them.
 *
 * Re-rendering a Blade partial server-side rather than patching the DOM in
 * JavaScript keeps one source of truth for money formatting, coupon rules and
 * stock warnings — the client never recomputes what the server already knows.
 */
trait SendsFragments
{
    /** Is the JS layer asking for a fragment rather than a whole page? */
    protected function wantsFragment(Request $request): bool
    {
        return $request->hasHeader('X-Fragment');
    }

    /**
     * Is this fragment meant to extend what is on screen rather than replace it?
     *
     * The storefront's product listings load as the shopper scrolls, and the
     * same URL that answers a filter change with a fresh grid has to answer the
     * scroll with twelve more cards. The mode is the only thing separating them.
     */
    protected function wantsAppend(Request $request): bool
    {
        return $request->header('X-Fragment-Mode') === 'append';
    }

    /**
     * @param  array<string, string>  $swap  CSS selector => replacement innerHTML
     * @param  array<string, string>  $append  CSS selector => innerHTML added at the end
     * @param  array<string, mixed>  $extra  merged in, for cartCount/url/flags
     */
    protected function fragment(
        array $swap = [],
        ?string $message = null,
        string $level = 'success',
        array $extra = [],
        array $append = [],
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'level' => $level,
            'swap' => $swap,
            'append' => $append,
        ] + $extra);
    }
}
