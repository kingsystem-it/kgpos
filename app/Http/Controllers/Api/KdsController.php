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

        $q = OrderItem::query()
            ->select([
                'id','order_id','name_snapshot as name','quantity','status',
                'sent_at','prepared_at','route_id_snapshot as route_id'
            ])
            ->whereNull('served_at')
            ->whereIn('status', ['sent','prepared']);

        if ($rid = $req->integer('route_id')) $q->where('route_id_snapshot',$rid);
        $q->where('status',$status)->orderBy($status==='prepared'?'prepared_at':'sent_at');

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
