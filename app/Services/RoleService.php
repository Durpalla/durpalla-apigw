<?php


namespace App\Services;


use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function getRoles()
    {
        $user = Auth::user();
        $roles = Role::get();
        $returnArr = [];
        foreach($roles as $role) {
            if($user->type == 'admin' && !(in_array($role->name, ['merchant', 'manager', 'counter-officer']))) {
                $returnArr[$role->id] = ucwords( str_replace('_', ' ', $role->name));
            } elseif($user->type == 'merchant' && in_array($role->name, ['manager', 'officer', 'supervisor', 'counter-officer'])) {
                $returnArr[$role->id] = ucwords( str_replace('_', ' ', $role->name));
            }
        }
        return $returnArr;
    }
}
