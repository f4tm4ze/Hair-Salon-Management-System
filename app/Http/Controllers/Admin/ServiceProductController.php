<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Product;
use Illuminate\Http\Request;

class ServiceProductController extends Controller
{
    public function attach(Request $request, Service $service)
    {
        $validator = validator($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity_used' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if product already attached
        if ($service->products()->where('product_id', $request->product_id)->exists()) {
            return response()->json([
                'success' => false,
                'errors' => ['product_id' => ['This product is already attached to this service.']]
            ], 422);
        }

        $service->products()->attach($request->product_id, ['quantity_used' => $request->quantity_used]);

        // Flash success message to session (will be displayed after page reload)
        session()->flash('success', 'Product added.');

        return response()->json(['success' => true]);
    }

    public function updateQuantity(Request $request, Service $service, Product $product)
    {
        $request->validate([
            'quantity_used' => 'required|integer|min:1',
        ]);

        $service->products()->updateExistingPivot($product->id, ['quantity_used' => $request->quantity_used]);

        return redirect()->back()->with('success', 'Quantity updated.');
    }

    public function detach(Service $service, Product $product)
    {
        $service->products()->detach($product->id);
        return redirect()->back()->with('success', 'Product removed.');
    }
}
