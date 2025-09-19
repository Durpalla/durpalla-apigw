<li class="nav-divider"></li>
@if(\App\Helpers\CommonHelper::hasPermission(['administrator-list', 'administrator-create', 'administrator-show', 'administrator-update', 'administrator-action']))
    <li class="@if($module_name == 'auth') active @endif">
        <a href="javascript:;"
           @if(in_array($current_class, ['UserController','RoleController'])) area-expanded="true" @endif>
            <i class="fa fa-user-md"></i>
            <span class="link-title">Manage</span>
            <span class="fa arrow"></span>
        </a>
        <ul class="collapse">
            @if(\App\Helpers\CommonHelper::hasPermission(['administrator-list', 'administrator-create', 'administrator-show', 'administrator-update', 'administrator-action']))
                <li class="@if($current_class == 'UserController') active @endif">
                    <a href="{{ route('user.index') }}">
                        <i class="fa fa-user-secret"></i>
                        <span class="link-title">Administrators</span>
                    </a>
                </li>
            @endif
            @if(\App\Helpers\CommonHelper::hasPermission(['role-list', 'role-create', 'role-update', 'role-action']))
                <li class="@if($current_class == 'RoleController') active @endif">
                    <a href="{{ route('role.index') }}">
                        <i class="fa fa-tags"></i>
                        <span class="link-title">Roles</span>
                    </a>
                </li>
            @endif
            @if(auth()->user()->id == 1)
                <li class="@if($current_class == 'PermissionController') active @endif">
                    <a href="{{ route('permission.index') }}">
                        <i class="fa fa-cogs"></i>
                        <span class="link-title">Permissions</span>
                    </a>
                </li>
            @endif
            <li class="@if($current_path === 'dashboard/auth/user/access-block') active @endif">
                <a href="{{ route('access-block.index') }}">
                    <i class="fa fa-user-times"></i>&nbsp; Access blocks</a>
            </li>
        </ul>
    </li>
@endif
<li class="nav-divider"></li>
<li class="logout">
    <form method="POST" action="{{ route('auth.logout') }}">
        @csrf
        <button type="submit"
                class="btn btn-default btn-sidebar btn-flat btn-lg text-danger btn-text-shadow btn-logout"><i
                class="fa fa-sign-out"></i> Logout
        </button>
    </form>
</li>
