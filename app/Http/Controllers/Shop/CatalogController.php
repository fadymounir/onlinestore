<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Concerns\SendsFragments;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    use SendsFragments;

    public function index(Request $request): View|JsonResponse
    {
        $products = $this->query($request)->paginate(12)->withQueryString();

        return $this->respond($request, $products, [
            'category' => null,
            'ancestors' => collect(),
            'heading' => $request->filled('q')
                ? 'نتائج البحث عن: '.$request->string('q')
                : 'كل المنتجات',
        ]);
    }

    public function category(Request $request, Category $category): View|JsonResponse
    {
        abort_unless($category->is_active, 404);

        // A switched-off branch is hidden whole, so a category under an
        // inactive ancestor is not reachable either.
        abort_if($category->ancestors()->where('is_active', false)->exists(), 404);

        $products = $this->query($request)
            // A branch shows everything beneath it, at any depth, not an
            // empty page — one nested-set query covers the whole subtree.
            ->whereIn('category_id', $category->subtreeIds())
            ->paginate(12)
            ->withQueryString();

        return $this->respond($request, $products, [
            'category' => $category,
            'ancestors' => $category->ancestors()->defaultOrder()->get(),
            'heading' => $category->name,
        ]);
    }

    /**
     * The whole page, or the part of it that moved.
     *
     * Three shapes come out of here, and which one depends on what the shopper
     * just did rather than on which route they are on.
     *
     *   Scrolled to the end of the grid. Twelve more cards, appended to what
     *   is already there, and a fresh link to the page after that. Nothing
     *   else on the page has changed, so nothing else is sent.
     *
     *   Changed a filter or the sort. The listing is now a different listing:
     *   the grid is replaced whole, the total is rewritten, and the chips come
     *   with it — they are the only place a filter can be removed from without
     *   opening the sidebar, so leaving them behind meant ticking a brand and
     *   having no way to untick it short of a reload. The URL is pushed so the
     *   result stays shareable and the back button works.
     *
     *   Asked for the page itself. The document, as it always was.
     *
     * @param  array<string, mixed>  $context
     */
    private function respond(Request $request, $products, array $context): View|JsonResponse
    {
        if ($this->wantsFragment($request) && $this->wantsAppend($request)) {
            return $this->fragment(
                swap: ['#catalog-more' => view('shop.partials.load-more', [
                    'products' => $products,
                    'grid' => '#catalog-grid',
                ])->render()],
                append: ['#catalog-grid' => view('shop.partials.product-cards', ['products' => $products])->render()],
            );
        }

        $brands = Brand::active()->orderBy('name')->get();

        if ($this->wantsFragment($request)) {
            return $this->fragment(
                swap: [
                    '#catalog-results' => view('shop.partials.catalog-results', ['products' => $products])->render(),
                    '#catalog-count' => $products->total().' منتج',
                    '#catalog-chips' => view('shop.partials.catalog-chips', [
                        'brands' => $brands,
                        'category' => $context['category'],
                    ])->render(),
                ],
                extra: ['url' => $request->fullUrl()],
            );
        }

        return view('shop.catalog', [
            'products' => $products,
            'categories' => Category::tree(onlyActive: true),
            'brands' => $brands,
            ...$context,
        ]);
    }

    /**
     * The listing itself.
     *
     * There is no "in stock only" filter among these: Product::published()
     * already has it, for every listing in the shop, so a checkbox offering it
     * would have been a control that could not change anything.
     */
    private function query(Request $request): Builder
    {
        return Product::published()
            ->with('primaryImage', 'images', 'brand', 'variants')
            ->when($request->string('q')->trim()->value(), fn (Builder $q, string $term) => $q
                ->where(fn (Builder $w) => $w
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('short_description', 'like', "%{$term}%")))
            ->when($request->input('brand'), fn (Builder $q, $brand) => $q->whereIn('brand_id', (array) $brand))
            ->when($request->input('min'), fn (Builder $q, $min) => $q->where('base_price', '>=', (float) $min))
            ->when($request->input('max'), fn (Builder $q, $max) => $q->where('base_price', '<=', (float) $max))
            ->when($request->input('sort'), function (Builder $q, string $sort) {
                return match ($sort) {
                    'price_asc' => $q->orderBy('base_price'),
                    'price_desc' => $q->orderByDesc('base_price'),
                    'newest' => $q->latest('published_at'),
                    default => $q->latest('published_at'),
                };
            }, fn (Builder $q) => $q->latest('published_at'));
    }
}
