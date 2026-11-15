<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class Dashboard extends Controller
{
    /**
     * Send the user to the home page of their primary panel.
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->homeRouteName());
    }
}
