<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $req)
    {
        $perPage = (int) min(max($req->get('per_page', 20), 1), 100);

        // allowlist de ordenação
        $sortable = ['name','price','created_at','id'];
        $sort = in_array($req->get('sort'), $sortable) ? $req->get('sort') : 'name';
        $dir  = strtolower($req->get('dir')) === 'desc' ? 'desc' : 'asc';

        $q = Product::query()
            ->with('category:id,name');

        if ($s = $req->get('search')) {
            $q->where(function($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%")
                  ->orWhere('barcode', 'like', "%{$s}%");
            });
        }

        if ($cid = $req->get('category_id')) {
            $q->where('category_id', $cid);
        }

        if ($req->has('active')) {
            $q->where('active', filter_var($req->get('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $q->orderBy($sort, $dir);

        return response()->json(
            $q->paginate($perPage)
        );
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'establishment_id' => 'nullable|integer',
            'category_id'      => 'nullable|integer|exists:categories,id',
            'name'             => 'required|string|max:160',
            'slug'             => 'nullable|string|max:180|unique:products,slug',
            'type'             => 'nullable|in:simple,composed',
            'active'           => 'boolean',
            'sku'              => 'nullable|string|max:80',
            'barcode'          => 'nullable|string|max:80',
            'price'            => 'nullable|numeric|min:0',
            'promo_price'      => 'nullable|numeric|min:0',
            'track_stock'      => 'boolean',
            'stock_qty'        => 'nullable|integer',
            'min_stock'        => 'nullable|integer',
            'visible_pos'      => 'boolean',
            'image_path'       => 'nullable|string',

            // composição opcional
            'components'                       => 'nullable|array',
            'components.*.child_product_id'    => 'required_with:components|integer|exists:products,id',
            'components.*.quantity'            => 'required_with:components|numeric|min:0.001|max:999999',
        ]);

        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']);

        return DB::transaction(function () use ($data) {
            // separa components do payload
            $components = $data['components'] ?? null;
            unset($data['components']);

            $p = Product::create($data);

            if ($components && $p->type === 'composed') {
                $this->syncComponents($p->id, $components);
            }

            // retorna já com categoria e, se composto, componentes
            $p->load('category:id,name', 'components:id,name,price');
            return response()->json($p, 201);
        });
    }

    public function show(Product $product)
    {
        $product->load('category:id,name', 'components:id,name,price');
        return response()->json($product);
    }

    public function update(Request $req, Product $product)
    {
        $data = $req->validate([
            'establishment_id' => 'nullable|integer',
            'category_id'      => 'nullable|integer|exists:categories,id',
            'name'             => 'sometimes|required|string|max:160',
            'slug'             => 'nullable|string|max:180|unique:products,slug,'.$product->id,
            'type'             => 'nullable|in:simple,composed',
            'active'           => 'boolean',
            'sku'              => 'nullable|string|max:80',
            'barcode'          => 'nullable|string|max:80',
            'price'            => 'nullable|numeric|min:0',
            'promo_price'      => 'nullable|numeric|min:0',
            'track_stock'      => 'boolean',
            'stock_qty'        => 'nullable|integer',
            'min_stock'        => 'nullable|integer',
            'visible_pos'      => 'boolean',
            'image_path'       => 'nullable|string',

            'components'                    => 'nullable|array',
            'components.*.child_product_id' => 'required_with:components|integer|exists:products,id|not_in:'.$product->id,
            'components.*.quantity'         => 'required_with:components|numeric|min:0.001|max:999999',
        ]);

        // se veio name mas não slug, gera; se veio slug, garante único
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['name'], $product->id);
        } elseif (isset($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $product->id);
        }

        return DB::transaction(function () use ($product, $data) {
            $components = $data['components'] ?? null;
            unset($data['components']);

            $product->update($data);

            if ($components !== null) {
                if (($data['type'] ?? $product->type) === 'composed') {
                    $this->syncComponents($product->id, $components);
                } else {
                    // se virou "simple", apaga componentes
                    ProductItem::where('product_id', $product->id)->delete();
                }
            }

            $product->load('category:id,name', 'components:id,name,price');
            return response()->json($product);
        });
    }

    public function destroy(Product $product)
    {
        // não apagar se for componente de outro produto
        $isComponentElsewhere = DB::table('product_items')
            ->where('child_product_id', $product->id)
            ->exists();

        if ($isComponentElsewhere) {
            return response()->json(['error' => 'Product is component of another product'], 422);
        }

        $product->delete(); // cascade remove seus próprios itens (FK ON DELETE CASCADE)
        return response()->json(['ok' => true]);
    }

    /* ---------------------- helpers ---------------------- */

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        $original = $slug; $i = 1;

        $exists = function ($s) use ($ignoreId) {
            $q = Product::where('slug', $s);
            if ($ignoreId) $q->where('id', '!=', $ignoreId);
            return $q->exists();
        };

        while ($exists($slug)) {
            $slug = $original.'-'.$i++;
        }
        return $slug;
    }

    private function syncComponents(int $productId, array $components): void
    {
        // normaliza: agrupa por child_product_id e soma quantidades
        $map = [];
        foreach ($components as $c) {
            $cid = (int) $c['child_product_id'];
            $qty = (float) $c['quantity'];
            if ($cid === $productId) continue; // evita auto-referência
            $map[$cid] = ($map[$cid] ?? 0) + $qty;
        }

        // upsert simples
        $keepIds = [];
        foreach ($map as $cid => $qty) {
            $row = ProductItem::updateOrCreate(
                ['product_id' => $productId, 'child_product_id' => $cid],
                ['quantity' => $qty]
            );
            $keepIds[] = $row->id;
        }

        // remove itens que não estão mais na lista
        ProductItem::where('product_id', $productId)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
