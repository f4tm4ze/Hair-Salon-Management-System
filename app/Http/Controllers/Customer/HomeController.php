<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $featuredServices = Service::whereNull('deleted_at')->take(4)->get();
        return view('customer.home', compact('featuredServices'));
    }

    public function socials()
    {
        return view('customer.socials');
    }

    public function about()
    {
        return view('customer.about');
    }
}
