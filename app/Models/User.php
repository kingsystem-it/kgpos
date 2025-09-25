<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Cache; // (se quiser cachear)

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function hasPermission(string $permission): bool
    {
        // Sem cache (simples e direto):
        return DB::table('role_permissions')
            ->join('user_roles', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $this->id)
            ->where('permissions.name', $permission)
            ->exists();

        // Com cache de 60s (opcional):
        // return Cache::remember("user:{$this->id}:perm:{$permission}", 60, function () use ($permission) {
        //     return DB::table('role_permissions')
        //         ->join('user_roles', 'role_permissions.role_id', '=', 'user_roles.role_id')
        //         ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
        //         ->where('user_roles.user_id', $this->id)
        //         ->where('permissions.name', $permission)
        //         ->exists();
        // });
    }

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
