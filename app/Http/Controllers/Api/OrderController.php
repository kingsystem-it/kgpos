<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $req)
    {
        $data = $req->validate([
            'anchor' => 'nullable|string|max:50',
        ]);
        $o = Order::create($data + ['status' => 'open']);
        DB::table('order_logs')->insert([
            'order_id' => $o->id,
            'user_id' => optional($req->user())->id,
            'action' => 'created',
            'context' => json_encode($data),
            'created_at' => now('UTC'),
        ]);
        return response()->json($o, 201);
    }

    public function addItem(Request $req, Order $order)
    {
        $data = $req->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'nullable|numeric|min:1|max:99',
        ]);

        $p   = Product::findOrFail($data['product_id']);
        $qty = (int) ($data['quantity'] ?? 1);
        if ($qty < 1) {
            return response()->json(['message' => 'Quantity must be >= 1'], 422);
        }

        return DB::transaction(function () use ($req, $order, $p, $qty) {
            // Consolida "draft" mesmo produto + mesmo preço
            $existing = OrderItem::where('order_id', $order->id)
                ->where('product_id', $p->id)
                ->where('status', 'draft')
                ->where('price_snapshot', $p->price)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // incremento atômico
                $existing->update([
                    'quantity' => DB::raw('quantity + ' . $qty),
                ]);
                $item = $existing->fresh();
            } else {
                $item = OrderItem::create([
                    'order_id'         => $order->id,
                    'product_id'       => $p->id,
                    'name_snapshot'    => $p->name,
                    'price_snapshot'   => $p->price,
                    'quantity'         => $qty,
                    'status'           => 'draft',
                    'route_id_snapshot' => $p->category?->printer_route_id,
                ]);
            }

            DB::table('order_logs')->insert([
                'order_id'  => $order->id,
                'user_id'   => optional($req->user())->id,
                'action'    => 'item_added',
                'context'   => json_encode([
                    'item_id'    => $item->id,
                    'product_id' => $p->id,
                    'qty'        => $qty,
                ]),
                'created_at' => now('UTC'),
            ]);

            $this->recalc($order->id);

            return response()->json($item, $existing ? 200 : 201);
        });
    }


    public function send(Request $req, Order $order)
    {
        $affected = OrderItem::where('order_id', $order->id)
            ->where('status', 'draft')
            ->update(['status' => 'sent', 'sent_at' => now('UTC')]);

        if ($affected > 0) {
            DB::table('order_logs')->insert([
                'order_id' => $order->id,
                'user_id' => optional($req->user())->id,
                'action' => 'sent',
                'context' => json_encode(['count' => $affected]),
                'created_at' => now('UTC'),
            ]);
        }

        return response()->json(['ok' => true, 'sent_items' => $affected]);
    }

    public function show(Order $order)
    {
        $order->load('items');
        return response()->json($order);
    }

    private function recalc(int $orderId): void
    {
        $sum = OrderItem::where('order_id', $orderId)
            ->selectRaw('SUM(price_snapshot * quantity) as s')->value('s') ?? 0;
        Order::where('id', $orderId)->update(['subtotal' => $sum, 'total' => $sum]);
    }
    public function open(\Illuminate\Http\Request $req)
    {
        $limit = max(1, min((int)$req->query('limit', 30), 100));

        $rows = DB::table('orders as o')
            ->leftJoin('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->whereNull('o.closed_at')
            ->whereNull('o.canceled_at')
            ->selectRaw("
            o.id,
            o.anchor,
            o.created_at,
            COALESCE(o.total, 0) as total,
            SUM(CASE WHEN oi.status = 'draft' THEN 1 ELSE 0 END) as drafts,
            SUM(CASE WHEN oi.status = 'sent'  THEN 1 ELSE 0 END) as sents
        ")
            ->groupBy('o.id', 'o.anchor', 'o.created_at', 'o.total')
            ->orderByDesc('o.created_at')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $rows]);
    }
}
