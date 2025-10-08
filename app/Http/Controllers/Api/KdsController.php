<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        // normaliza timestamps para ISO8601 (UTC)
        $rows = $q->get()->map(function ($r) {
            if ($r->sent_at)     $r->sent_at     = \Carbon\Carbon::parse($r->sent_at, 'UTC')->toIso8601String();
            if ($r->prepared_at) $r->prepared_at = \Carbon\Carbon::parse($r->prepared_at, 'UTC')->toIso8601String();
            return $r;
        });

        return response()
            ->json(['data' => $rows])
            ->header('Cache-Control', 'no-store, max-age=0');
    }


    public function markPrepared(Request $req, OrderItem $item)
    {
        if (!in_array($item->status, ['sent'])) return response()->json(['error' => 'Invalid state'], 422);
        $item->update(['status' => 'prepared', 'prepared_at' => now('UTC')]);
        $this->log($req->user()?->id, $item->order_id, 'prepared', ['item_id' => $item->id]);
        return response()->json(['ok' => true]);
    }

    public function markServed(Request $req, OrderItem $item)
    {
        if (!in_array($item->status, ['sent', 'prepared'])) return response()->json(['error' => 'Invalid state'], 422);
        $item->update(['status' => 'served', 'served_at' => now('UTC')]);
        $this->log($req->user()?->id, $item->order_id, 'served', ['item_id' => $item->id]);
        return response()->json(['ok' => true]);
    }

    private function log($userId, int $orderId, string $action, array $ctx): void
    {
        DB::table('order_logs')->insert([
            'order_id' => $orderId,
            'user_id' => $userId,
            'action' => $action,
            'context' => json_encode($ctx),
            'created_at' => now('UTC'),
        ]);
    }
}
