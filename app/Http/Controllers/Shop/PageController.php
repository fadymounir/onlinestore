<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Page $page): View
    {
        // Switching a page off has to hide it, not just unlist it — the URL
        // outlives the footer link, in bookmarks and in search results.
        abort_unless($page->is_active, 404);

        return view('shop.page', ['page' => $page]);
    }
}
