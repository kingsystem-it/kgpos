<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function permissions()
    {
        // Tabela pivô: role_permissions (role_id, permission_id)
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    public function users()
    {
        // Tabela pivô: user_roles (user_id, role_id)
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }
}
