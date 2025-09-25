<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KdsController extends Controller
{
    public function queue(Request $req)
{
    $req->validate([
        'route_id' => 'nullable|integer',
        'status'   => 'nullable|in:sent,prepared'
    ]);
    $status = $req->get('status','sent');

    $q = \App\Models\OrderItem::query()
        ->join('orders','orders.id','=','order_items.order_id')
        ->select([
            'order_items.id',
            'order_items.order_id',
            'orders.anchor as anchor',
            'order_items.name_snapshot as name',
            'order_items.quantity',
            'order_items.status',
            'order_items.sent_at',
            'order_items.prepared_at',
            'order_items.route_id_snapshot as route_id',
        ])
        ->whereNull('order_items.served_at')
        ->whereIn('order_items.status', ['sent','prepared']);

    if ($rid = $req->integer('route_id')) $q->where('order_items.route_id_snapshot',$rid);
    $q->where('order_items.status',$status)
      ->orderBy($status==='prepared' ? 'order_items.prepared_at' : 'order_items.sent_at');

    return response()->json(['data'=>$q->get()]);
}

    public function markPrepared(Request $req, OrderItem $item)
    {
        if (!in_array($item->status,['sent'])) return response()->json(['error'=>'Invalid state'], 422);
        $item->update(['status'=>'prepared','prepared_at'=>now('UTC')]);
        $this->log($req->user()?->id, $item->order_id, 'prepared', ['item_id'=>$item->id]);
        return response()->json(['ok'=>true]);
    }

    public function markServed(Request $req, OrderItem $item)
    {
        if (!in_array($item->status,['sent','prepared'])) return response()->json(['error'=>'Invalid state'], 422);
        $item->update(['status'=>'served','served_at'=>now('UTC')]);
        $this->log($req->user()?->id, $item->order_id, 'served', ['item_id'=>$item->id]);
        return response()->json(['ok'=>true]);
    }

    private function log($userId, int $orderId, string $action, array $ctx): void
    {
        DB::table('order_logs')->insert([
            'order_id'=>$orderId,'user_id'=>$userId,'action'=>$action,
            'context'=>json_encode($ctx),'created_at'=>now('UTC'),
        ]);
    }
}
