<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use App\Models\ClothStore\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::withCount('products');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Ordered by name rather than newest: a category list is something you
        // scan for a known name, not a feed.
        $categories = $query->orderBy('name')->get();

        return view('cloth-store.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:cs_categories,name',
            'icon'        => 'nullable|string|max:50',
            'color_bg'    => 'nullable|string|max:50',
            'color_text'  => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($validated);
        
        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:cs_categories,name,'.$id,
            'icon'        => 'nullable|string|max:50',
            'color_bg'    => 'nullable|string|max:50',
            'color_text'  => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $category->update($validated);
        
        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with associated products.'
            ], 422);
        }

        $category->delete();
        
        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }
}
