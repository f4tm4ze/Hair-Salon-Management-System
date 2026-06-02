<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $query = Discount::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) { // changed from 'status'
            $query->where('type', $request->type);
        }

        $discounts = $query->paginate(10);
        return view('admin.discounts.index', compact('discounts'));
    }

    public function create()
    {
        $discount = new Discount(); // empty model
        return view('admin.discounts.create', compact('discount'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:discounts,code',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'usage_limit' => 'nullable|integer|min:0',
        ]);

        Discount::create($request->all() + ['status' => 'active']);
        return redirect()->route('admin.discounts.index')->with('success', 'Discount created.');
    }

    public function edit(Discount $discount)
    {
        return view('admin.discounts.edit', compact('discount'));
    }

    public function update(Request $request, Discount $discount)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:discounts,code,' . $discount->id,
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'usage_limit' => 'nullable|integer|min:0',
        ]);

        $discount->update($request->all());
        return redirect()->route('admin.discounts.index')->with('success', 'Discount updated.');
    }

    public function destroy(Discount $discount)
    {
        $discount->delete();
        return redirect()->route('admin.discounts.index')->with('success', 'Discount archived.');
    }

    public function archived(Request $request)
    {
        $query = Discount::onlyTrashed()
            ->whereNotNull('deleted_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $discounts = $query->orderBy('deleted_at', 'desc')->paginate(10);
        return view('admin.discounts.archived', compact('discounts'));
    }

    public function restore($id)
    {
        $discount = Discount::withTrashed()->findOrFail($id);
        $discount->restore();
        return redirect()->route('admin.discounts.archived')->with('success', 'Discount restored.');
    }
}
