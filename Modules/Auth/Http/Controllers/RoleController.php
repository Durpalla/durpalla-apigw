<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Auth\Entities\Role;
use Modules\Auth\Http\Requests\RoleCreateRequest;
use Modules\Auth\Http\Requests\RoleUpdateRequest;
use Modules\Auth\Services\RoleService;

class RoleController extends Controller
{
    use ValidatesRequests, AuthorizesRequests;

    private RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        parent::__construct();
        $this->roleService = $roleService;
    }

    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->roleService->getDataTable($request);
        }
        return $this->themedView('auth::role.index');
    }

    public function create(): View
    {
        $permissions = $this->roleService->getPermissions();
        return $this->themedView('auth::role.create', [
            'permissions' => $permissions,
        ]);
    }

    public function store(RoleCreateRequest $request): RedirectResponse
    {
        return $this->roleService->create($request->validated());
    }

    public function show(Role $role): View
    {
        return $this->themedView('auth::role.show', [
            'role' => $role
        ]);
    }

    public function edit(Role $role): View
    {
        $permissions = $this->roleService->getPermissions();
        $rolePermissions = $role->permissions->pluck('id', 'id')->all();
        return $this->themedView('auth::role.edit', [
            'role' => $role,
            'permissions' => $permissions,
            'rolePermissions' => $rolePermissions
        ]);
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        return $this->roleService->update($request->validated(), $role);
    }

    public function destroy($id)
    {
        //
    }

    public function callAction($method, $parameters)
    {
        if (!in_array($method, ['attachVendor', 'stockSuggestions', 'suggestions'])) {
            $this->authorize($method, Role::class);
        }
        return parent::callAction($method, $parameters);
    }
}
