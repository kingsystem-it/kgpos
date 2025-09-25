<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $req)
    {
        $perPage = (int) min(max($req->get('per_page', 20), 1), 100);
        $q = Category::query();

        if ($s = $req->get('search')) {
            $q->where('name','like',"%{$s}%");
        }

        $q->orderBy($req->get('sort','sort_order'),'asc');

        return response()->json($q->paginate($perPage));
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'color_hex' => ['nullable','string','max:7','regex:/^#?[0-9A-Fa-f]{6}$/'],
            'parent_id' => 'nullable|integer|exists:categories,id',
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:140|unique:categories,slug',
            'sort_order' => 'nullable|integer',
            'color_hex' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'visible_pos' => 'boolean',
            'visible_online' => 'boolean',
            'printer_route_id' => 'nullable|integer',
            'image_path' => 'nullable|string',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $cat = Category::create($data);
        return response()->json($cat, 201);
    }

    public function show(Category $category) { return response()->json($category); }

    public function update(Request $req, Category $category)
    {
        $data = $req->validate([
            'color_hex' => ['nullable','string','max:7','regex:/^#?[0-9A-Fa-f]{6}$/'],
            'parent_id' => 'nullable|integer|exists:categories,id',
            'name' => 'sometimes|required|string|max:120',
            'slug' => 'nullable|string|max:140|unique:categories,slug,'.$category->id,
            'sort_order' => 'nullable|integer',
            'color_hex' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'visible_pos' => 'boolean',
            'visible_online' => 'boolean',
            'printer_route_id' => 'nullable|integer',
            'image_path' => 'nullable|string',
            'parent_id' => ['nullable','integer','exists:categories,id',Rule::notIn([$category->id]), // impede ser pai de si mesma
],
        ]);

        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        
],
        $category->update($data);
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        if ($category->children()->exists() || $category->products()->exists()) {
            return response()->json(['error'=>'Category in use'], 422);
        }
        $category->delete();
        return response()->json(['ok'=>true]);
    }
}
