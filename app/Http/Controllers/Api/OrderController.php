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
        $o = Order::create($data + ['status'=>'open']);
        DB::table('order_logs')->insert([
            'order_id'=>$o->id,'user_id'=>optional($req->user())->id,
            'action'=>'created','context'=>json_encode($data),'created_at'=>now('UTC'),
        ]);
        return response()->json($o, 201);
    }

    public function addItem(Request $req, Order $order)
    {
        $data = $req->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'nullable|numeric|min:0.001|max:999999',
        ]);

        $p = Product::findOrFail($data['product_id']);
        $qty = (float)($data['quantity'] ?? 1);

        $item = OrderItem::create([
            'order_id'        => $order->id,
            'product_id'      => $p->id,
            'name_snapshot'   => $p->name,
            'price_snapshot'  => $p->price,
            'quantity'        => $qty,
            'status'          => 'draft',
            'route_id_snapshot'=> $p->category?->printer_route_id, // pode ser null
        ]);

        DB::table('order_logs')->insert([
            'order_id'=>$order->id,'user_id'=>optional($req->user())->id,
            'action'=>'item_added',
            'context'=>json_encode(['item_id'=>$item->id,'product_id'=>$p->id,'qty'=>$qty]),
            'created_at'=>now('UTC'),
        ]);

        $this->recalc($order->id);
        return response()->json($item->fresh(), 201);
    }

    public function send(Request $req, Order $order)
    {
        $affected = OrderItem::where('order_id',$order->id)
            ->where('status','draft')
            ->update(['status'=>'sent','sent_at'=>now('UTC')]);

        if ($affected>0) {
            DB::table('order_logs')->insert([
                'order_id'=>$order->id,'user_id'=>optional($req->user())->id,
                'action'=>'sent','context'=>json_encode(['count'=>$affected]),
                'created_at'=>now('UTC'),
            ]);
        }

        return response()->json(['ok'=>true,'sent_items'=>$affected]);
    }

    public function show(Order $order)
    {
        $order->load('items');
        return response()->json($order);
    }

    private function recalc(int $orderId): void
    {
        $sum = OrderItem::where('order_id',$orderId)
            ->selectRaw('SUM(price_snapshot * quantity) as s')->value('s') ?? 0;
        Order::where('id',$orderId)->update(['subtotal'=>$sum,'total'=>$sum]);
    }
}
