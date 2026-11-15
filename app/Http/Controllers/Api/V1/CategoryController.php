<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Active top-level categories with their active children.
     */
    public function index(): JsonResponse
    {
        $categories = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'icon', 'parent_id']);

        return response()->json(['data' => $categories]);
    }
}
