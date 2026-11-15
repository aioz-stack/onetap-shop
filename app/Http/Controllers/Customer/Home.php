<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Inertia\Inertia;
use Inertia\Response;

class Home extends Controller
{
    /**
     * Storefront home page. Product sections are filled in during Phase 4.
     */
    public function index(): Response
    {
        return Inertia::render('Customer/Home', [
            'categories' => ProductCategory::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'icon']),
        ]);
    }
}
