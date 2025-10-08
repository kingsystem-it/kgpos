<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $req)
    {
        $perPage = max(1, min((int) $req->query('per_page', 100), 100));

        $q = Category::query();

        if ($s = $req->query('search')) {
            $q->where('name', 'like', "%{$s}%");
        }

        // evita sort arbitrário/injeção
        $allowedSorts = ['sort_order', 'name', 'created_at', 'id'];
        $sort = $req->query('sort', 'sort_order');
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'sort_order';
        }
        $q->orderBy($sort, 'asc');

        return response()->json($q->paginate($perPage));
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'parent_id'        => ['nullable', 'integer', 'exists:categories,id'],
            'name'             => ['required', 'string', 'max:120'],
            'slug'             => ['nullable', 'string', 'max:140', 'unique:categories,slug'],
            'sort_order'       => ['nullable', 'integer'],
            'color_hex'        => ['nullable', 'string', 'max:7', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'icon'             => ['nullable', 'string', 'max:50'],
            'visible_pos'      => ['boolean'],
            'visible_online'   => ['boolean'],
            'printer_route_id' => ['nullable', 'integer'],
            'image_path'       => ['nullable', 'string'],
        ]);

        if (empty($data['slug'] ?? null)) {
            $data['slug'] = Str::slug($data['name']);
        }

        $cat = Category::create($data);
        return response()->json($cat, 201);
    }

    public function show(Category $category)
    {
        return response()->json($category);
    }

    public function update(Request $req, Category $category)
    {
        $data = $req->validate([
            'parent_id'        => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$category->id])],
            'name'             => ['sometimes', 'required', 'string', 'max:120'],
            'slug'             => ['nullable', 'string', 'max:140', Rule::unique('categories', 'slug')->ignore($category->id)],
            'sort_order'       => ['nullable', 'integer'],
            'color_hex'        => ['nullable', 'string', 'max:7', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'icon'             => ['nullable', 'string', 'max:50'],
            'visible_pos'      => ['boolean'],
            'visible_online'   => ['boolean'],
            'printer_route_id' => ['nullable', 'integer'],
            'image_path'       => ['nullable', 'string'],
        ]);

        if (isset($data['name']) && empty($data['slug'] ?? null)) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        if (method_exists($category, 'children') && $category->children()->exists()) {
            return response()->json(['error' => 'Category in use (children)'], 422);
        }
        if (method_exists($category, 'products') && $category->products()->exists()) {
            return response()->json(['error' => 'Category in use (products)'], 422);
        }
        $category->delete();
        return response()->json(['ok' => true]);
    }
}
