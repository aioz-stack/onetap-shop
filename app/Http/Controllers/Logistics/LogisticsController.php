<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsPartner;
use Illuminate\Http\Request;

/**
 * Base for Logistics Panel controllers: the current partner comes from EnsureLogisticsPartner.
 */
abstract class LogisticsController extends Controller
{
    protected function partner(Request $request): LogisticsPartner
    {
        return $request->attributes->get('partner');
    }
}
