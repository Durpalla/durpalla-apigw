<?php


namespace App\Services;


use App\Models\Merchant;
use App\Models\MerchantStaff;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function getRoles()
    {
        $user = Auth::user();
        $roles = Role::get();
        $returnArr = [];
        $isMerchant = $user instanceof Merchant || $user instanceof MerchantStaff;
        $isAdmin = ! $isMerchant && (
            (isset($user->type) && $user->type == 'admin')
            || (method_exists($user, 'hasRole') && $user->hasRole('admin'))
        );

        foreach ($roles as $role) {
            if ($isAdmin && !(in_array($role->name, ['merchant', 'manager', 'counter-officer']))) {
                $returnArr[$role->id] = ucwords(str_replace('_', ' ', $role->name));
            } elseif ($isMerchant && in_array($role->name, ['manager', 'officer', 'supervisor', 'counter-officer'])) {
                $returnArr[$role->id] = ucwords(str_replace('_', ' ', $role->name));
            }
        }

        return $returnArr;
    }
}
