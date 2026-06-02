<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::whereNull('deleted_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $services = $query->paginate(12);
        $categories = Service::whereNull('deleted_at')->distinct()->pluck('category');

        return view('customer.services.index', compact('services', 'categories'));
    }

    public function show(Service $service)
    {
        // Get random services excluding the current one
        $relatedServices = Service::whereNull('deleted_at')
            ->where('id', '!=', $service->id)
            ->inRandomOrder()
            ->limit(3)
            ->get();

        return view('customer.services.show', compact('service', 'relatedServices'));
    }
}
