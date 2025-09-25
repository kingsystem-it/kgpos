<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now('UTC');

        // ---- Roles base
        $roles = ['Owner','Admin','Manager','Cashier','Bartender','Waiter','Door','Kitchen','Analyst'];
        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(
                ['name'=>$r],
                ['description'=>$r.' role','created_at'=>$now,'updated_at'=>$now]
            );
        }

        // ---- Permissões (inclui POS e KDS)
        $perms = [
            ['name'=>'settings:view',            'module'=>'settings','action'=>'view'],
            ['name'=>'settings:update',          'module'=>'settings','action'=>'update'],
            ['name'=>'acl:assign_user_to_role',  'module'=>'acl','action'=>'assign_user_to_role'],
            ['name'=>'reports:view_realtime',    'module'=>'reports','action'=>'view_realtime'],

            ['name'=>'categories:manage',        'module'=>'catalog','action'=>'manage'],
            ['name'=>'products:manage',          'module'=>'catalog','action'=>'manage'],

            ['name'=>'pos:open_tab',             'module'=>'pos','action'=>'open_tab'],
            ['name'=>'pos:add_item',             'module'=>'pos','action'=>'add_item'],
            ['name'=>'pos:send_to_kds',          'module'=>'pos','action'=>'send_to_kds'],

            ['name'=>'cashier:process_payment',  'module'=>'cashier','action'=>'process_payment'],

            ['name'=>'kds:view',                 'module'=>'kds','action'=>'view'],
            ['name'=>'kds:update_status',        'module'=>'kds','action'=>'update_status'],
        ];
        foreach ($perms as $p) {
            DB::table('permissions')->updateOrInsert(
                ['name'=>$p['name']],
                $p + ['created_at'=>$now,'updated_at'=>$now]
            );
        }

        // ---- Mapa Role → Permissões
        // Owner/Admin: todas; Manager: POS+KDS+catálogo+reports; Kitchen: KDS; Cashier: pagamento+reports
        $allPerms = DB::table('permissions')->pluck('id','name'); // name => id

        $map = [
            'Owner'   => ['*'],
            'Admin'   => ['*'],
            'Manager' => [
                'settings:view','reports:view_realtime','acl:assign_user_to_role',
                'categories:manage','products:manage',
                'pos:open_tab','pos:add_item','pos:send_to_kds',
                'cashier:process_payment',
                'kds:view','kds:update_status',
            ],
            'Kitchen' => ['kds:view','kds:update_status'],
            'Cashier' => ['cashier:process_payment','reports:view_realtime'],
            // Bartender/Waiter/Door/Analyst podem ficar vazios por enquanto
        ];

        foreach ($map as $roleName => $permNames) {
            $roleId = DB::table('roles')->where('name',$roleName)->value('id');
            if (!$roleId) continue;

            $names = ($permNames === ['*']) ? array_keys($allPerms->toArray()) : $permNames;
            foreach ($names as $permName) {
                $pid = $allPerms[$permName] ?? null;
                if ($pid) {
                    DB::table('role_permissions')->updateOrInsert(
                        ['role_id'=>$roleId,'permission_id'=>$pid],
                        []
                    );
                }
            }
        }

        // ---- Usuário Owner padrão
        $ownerId = DB::table('users')->where('email','owner@kgpos.test')->value('id');
        if (!$ownerId) {
            $ownerId = DB::table('users')->insertGetId([
                'name'=>'Owner KG POS',
                'email'=>'owner@kgpos.test',
                'password'=>Hash::make('password'),
                'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
        $ownerRoleId = DB::table('roles')->where('name','Owner')->value('id');
        if ($ownerId && $ownerRoleId) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id'=>$ownerId,'role_id'=>$ownerRoleId],
                ['assigned_at'=>$now]
            );
        }

        // (Opcional) Usuário Kitchen para testar KDS
        /*
        $kitchenUserId = DB::table('users')->where('email','kitchen@kgpos.test')->value('id');
        if (!$kitchenUserId) {
            $kitchenUserId = DB::table('users')->insertGetId([
                'name'=>'Kitchen User',
                'email'=>'kitchen@kgpos.test',
                'password'=>Hash::make('password'),
                'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
        $kitchenRoleId = DB::table('roles')->where('name','Kitchen')->value('id');
        if ($kitchenUserId && $kitchenRoleId) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id'=>$kitchenUserId,'role_id'=>$kitchenRoleId],
                ['assigned_at'=>$now]
            );
        }
        */

        // ---- POS settings default
        DB::table('pos_settings')->updateOrInsert(['id'=>1], [
            'currency'=>'EUR','ui_language'=>'it',
            'printing_enabled'=>false,'kds_enabled'=>true,
            'created_at'=>$now,'updated_at'=>$now
        ]);
    }
}
