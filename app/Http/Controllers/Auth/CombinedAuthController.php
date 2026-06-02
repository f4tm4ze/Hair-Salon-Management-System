<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CombinedAuthController extends Controller
{
    public function show(Request $request)
    {
        // Determine which form to show initially based on the route name
        $initial = $request->routeIs('login') ? 'login' : 'register';
        return view('auth.sliding', compact('initial'));
    }
}
