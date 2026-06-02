<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $products = $query->paginate(10);
        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'size' => 'nullable|string|max:50',
            'quantity' => 'required|integer|min:0',
            'price' => 'nullable|numeric|min:0',
        ]);

        Product::create($request->all());
        return redirect()->route('admin.products.index')->with('success', 'Product added.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'size' => 'nullable|string|max:50',
            'quantity' => 'required|integer|min:0',
            'price' => 'nullable|numeric|min:0',
        ]);

        $product->update($request->all());
        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Product archived.');
    }

    public function archived(Request $request)
    {
        $query = Product::onlyTrashed()
            ->whereNotNull('deleted_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('deleted_at', 'desc')->paginate(10);
        return view('admin.products.archived', compact('products'));
    }

    public function restore($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
        return redirect()->route('admin.products.archived')->with('success', 'Product restored.');
    }
}
