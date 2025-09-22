@if(\App\Helpers\CommonHelper::hasPermission(['gateway-list', 'gateway-create', 'gateway-show', 'gateway-update', 'gateway-action']))
    <li class="@if(in_array($current_path, ['dashboard/gateway', 'dashboard/gateway'])) active @endif">
        <a href="{{ route('gateway.index') }}">
            <i class="fa fa-cubes"></i>
            <span class="link-title">Gateways</span>
        </a>
    </li>
@endif
