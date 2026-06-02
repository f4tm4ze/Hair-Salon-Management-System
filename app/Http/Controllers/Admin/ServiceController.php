<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $services = $query->paginate(10);
        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'category' => 'required|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('services', 'public');
            $data['image'] = $imagePath;
        }

        Service::create($data);

        return redirect()->route('admin.services.index')->with('success', 'Service created.');
    }

    public function edit(Service $service)
    {
        $allProducts = Product::orderBy('name')->get();
        return view('admin.services.edit', compact('service', 'allProducts'));
    }

    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'category' => 'required|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($service->image) {
                Storage::disk('public')->delete($service->image);
            }
            $imagePath = $request->file('image')->store('services', 'public');
            $data['image'] = $imagePath;
        }

        $service->update($data);

        return redirect()->route('admin.services.index')->with('success', 'Service updated.');
    }

    public function destroy(Service $service)
    {
        $service->delete(); // soft delete
        return redirect()->route('admin.services.index')->with('success', 'Service archived.');
    }

    public function archived(Request $request)
    {
        $query = Service::onlyTrashed()
            ->whereNotNull('deleted_at'); // extra safety

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $services = $query->orderBy('deleted_at', 'desc')->paginate(10);
        return view('admin.services.archived', compact('services'));
    }

    public function restore($id)
    {
        $service = Service::withTrashed()->findOrFail($id);
        $service->restore();
        return redirect()->route('admin.services.archived')->with('success', 'Service restored.');
    }

    public function show(Service $service)
    {
        $service->load('products');
        return view('admin.services.show', compact('service'));
    }
}
