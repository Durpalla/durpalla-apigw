<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Auth\Entities\User;
use Modules\Auth\Http\Requests\UserCreateRequest;
use Modules\Auth\Http\Requests\UserStatusUpdateRequest;
use Modules\Auth\Http\Requests\UserUpdateRequest;
use Modules\Auth\Services\RoleService;
use Modules\Auth\Services\UserService;

class UserController extends Controller
{
    use ValidatesRequests, AuthorizesRequests;
    private UserService $userService;
    private RoleService $roleService;

    public function __construct(UserService $userService, RoleService $roleService)
    {
        parent::__construct();
        $this->userService = $userService;
        $this->roleService = $roleService;
    }

    public function index(Request $request)
    {
        if($request->ajax() || $request->wantsJson()) {
            return $this->userService->getDataTable($request);
        }
        return $this->themedView('auth::user.index');
    }

    public function create(): View
    {
        $roles = $this->roleService->all()->where('guard_name', 'web');
        return $this->themedView('auth::user.create', [
            'roles' => $roles
        ]);
    }

    public function store(UserCreateRequest $request): RedirectResponse
    {
        return $this->userService->create($request->all());
    }

    public function show(User $user): View
    {
        return $this->themedView('auth::user.show', [
            'user' => $user
        ]);
    }

    public function edit(User $user): View
    {
        abort_if(!$user->is_editable, 403);
        $roles = $this->roleService->all()->where('guard_name', 'web');
        return $this->themedView('auth::user.edit', [
            'user' => $user,
            'roles' => $roles
        ]);
    }

    public function update(UserUpdateRequest $request, $id): RedirectResponse
    {
        return $this->userService->update($request->validated(), $id);
    }

    public function updateStatus(UserStatusUpdateRequest $request, User $user)
    {
        try {
            $user->update($request->validated());
            return response()->success();
        } catch (\Exception $exception) {
            return response()->failed(['message' => $exception->getMessage()]);
        }
    }

    public function suggestions(Request $request): JsonResponse
    {
        return $this->userService->suggestions($request);
    }

    public function callAction($method, $parameters)
    {
        if (!in_array($method, ['attachVendor', 'stockSuggestions', 'suggestions'])) {
            $this->authorize($method, User::class);
        }
        return parent::callAction($method, $parameters);
    }
}
