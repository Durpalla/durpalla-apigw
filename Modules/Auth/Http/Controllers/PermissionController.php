<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Auth\Entities\Permission;
use Modules\Auth\Http\Requests\PermissionCreateRequest;
use Modules\Auth\Http\Requests\PermissionUpdateRequest;
use Modules\Auth\Services\PermissionService;

class PermissionController extends Controller
{
    use ValidatesRequests, AuthorizesRequests;

    private PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        parent::__construct();
        $this->permissionService = $permissionService;
    }

    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->permissionService->getDataTable($request);
        }
        return $this->themedView('auth::permission.index');
    }

    public function create(): View
    {
        return $this->themedView('auth::permission.create');
    }

    public function store(PermissionCreateRequest $request): RedirectResponse
    {
        return $this->permissionService->create($request->validated());
    }

    public function show(Permission $permission): View
    {
        return $this->themedView('auth::permission.show', [
            'permission' => $permission
        ]);
    }

    public function edit(Permission $permission): View
    {
        return $this->themedView('auth::permission.edit', [
            'permission' => $permission
        ]);
    }

    public function update(PermissionUpdateRequest $request, Permission $permission): RedirectResponse
    {
        return $this->permissionService->update($request->validated(), $permission);
    }

    public function destroy($id)
    {
        //
    }

    public function callAction($method, $parameters)
    {
        if (!in_array($method, ['attachVendor', 'stockSuggestions', 'suggestions'])) {
            $this->authorize($method, Permission::class);
        }
        return parent::callAction($method, $parameters);
    }
}
