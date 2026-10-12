<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('shop.home', [
            'categories' => Category::tree(onlyActive: true),
            'featured' => Product::published()
                ->where('is_featured', true)
                ->with('primaryImage', 'brand', 'variants')
                ->latest('published_at')
                ->take(8)
                ->get(),
            'newest' => Product::published()
                ->with('primaryImage', 'brand', 'variants')
                ->latest('published_at')
                ->take(8)
                ->get(),
        ]);
    }
}
