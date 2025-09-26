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
        'status'   => 'nullable|in:sent,prepared',
    ]);

    $status  = $req->get('status', 'sent');  // 'sent' | 'prepared'
    $routeId = $req->filled('route_id') ? (int) $req->input('route_id') : null;

    $q = DB::table('order_items as oi')
        ->join('orders as o', 'o.id', '=', 'oi.order_id')
        ->selectRaw("
            oi.id,
            oi.order_id,
            o.anchor,
            oi.name_snapshot as name,
            oi.quantity,
            oi.status,
            oi.sent_at,
            oi.prepared_at,
            oi.route_id_snapshot as route_id
        ")
        ->whereNull('oi.served_at')
        ->whereIn('oi.status', ['sent','prepared'])
        ->where('oi.status', $status);

    if (!is_null($routeId)) {
        $q->where('oi.route_id_snapshot', $routeId);
    }

    // ordenação estável mesmo se timestamps estiverem NULL
    if ($status === 'prepared') {
        $q->orderByRaw('COALESCE(oi.prepared_at, oi.sent_at, oi.created_at) ASC');
    } else {
        $q->orderByRaw('COALESCE(oi.sent_at, oi.created_at) ASC');
    }

    $data = $q->get();

    return response()
        ->json(['data' => $data])
        ->header('Cache-Control', 'no-store, max-age=0');
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
