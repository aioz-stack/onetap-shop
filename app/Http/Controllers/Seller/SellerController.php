<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

/**
 * Base for Seller Center controllers: the current shop comes from EnsureSellerShop.
 */
abstract class SellerController extends Controller
{
    protected function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}
