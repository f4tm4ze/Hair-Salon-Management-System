<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role == 'customer') {
            return redirect()->route('home');
        } elseif ($user->role == 'frontdesk') {
            return redirect()->route('frontdesk.dashboard');
        } elseif ($user->role == 'tech') {
            return redirect()->route('tech.dashboard');
        } elseif ($user->role == 'manager') {
            return redirect()->route('admin.dashboard');
        }

        return redirect('/');
    }
}
