<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
    <li class="nav-item @if(Request::segment(2) == '') menu-open @endif">
        <a href="{{ route('home')}}" class="nav-link">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>
                Dashboard
            </p>
        </a>
    </li>
    @if(auth()->user()->hasAnyPermission(['merchant-list']) || auth()->user()->hasRole('admin'))
        <li class="nav-item has-treeview @if(Request::segment(2) =='merchant') menu-open @endif">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-user-tie"></i>
                <p>
                    Merchants
                </p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="{{ route('dashboard.merchant.index') }}"
                       class="nav-link @if(Request::segment(2) == 'merchant' && Request::segment(3) == '') active @endif">
                        <i class="fas fa-user-tie nav-icon"></i>
                        <p>Merchant list</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('merchant.supervisors') }}"
                       class="nav-link @if(Request::segment(2) == 'merchant' && Request::segment(3) == 'supervisors') active @endif">
                        <i class="fas fa-user-tie nav-icon"></i>
                        <p>Supervisors</p>
                    </a>
                </li>
            </ul>
        </li>
    @endif
    @if(auth()->user()->hasAnyPermission(['party-list', 'service-list']) || auth()->user()->hasRole('admin'))
        <li class="nav-item has-treeview @if(in_array(Request::segment(2), ['parties', 'services'])) menu-open @endif">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-user-tie"></i>
                <p>
                    Parties
                </p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="{{ route('parties.index') }}"
                       class="nav-link @if(Request::segment(2) == 'parties') active @endif">
                        <i class="fas fa-user-tie nav-icon"></i>
                        <p>Party list</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('services.index') }}"
                       class="nav-link @if(Request::segment(2) == 'services') active @endif">
                        <i class="fas fa-user-tie nav-icon"></i>
                        <p>Service list</p>
                    </a>
                </li>
            </ul>
        </li>
    @endif

    @if(auth()->user()->hasAnyPermission(['vehicle-list']) || auth()->user()->hasRole('admin'))
        <li class="nav-item has-treeview @if(Request::segment(2) =='vehicle' && in_array(Request::segment(3), ['', 'show'])) menu-open @endif">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-ship"></i>
                <p>
                    Vehicles
                </p>
            </a>
            <ul class="nav nav-treeview">
                @foreach($service_list as $key => $value)
                    <li class="nav-item">
                        <a href="{{ route('dashboard.vehicle.index', ['type' => $key]) }}"
                           class="nav-link @if(Request::segment(2) == 'vehicle' && Request::get('type') == $key) active @endif">
                            <i class="fas fa-ship nav-icon"></i>
                            <p>{{ $value }} list</p>
                        </a>
                    </li>
                @endforeach
            </ul>
        </li>
    @endif

    @if(auth()->user()->hasAnyPermission(['vehicle-list', 'coupon-list', 'coupon-show', 'coupon-create', 'coupon-edit', 'coupon-delete']) || auth()->user()->hasRole('admin'))
        <li class="nav-item has-treeview @if(in_array(Request::segment(3), ['schedule', 'route', 'ghat', 'cabin', 'coupon', 'discount', 'banner', 'withdrawal'])) menu-open @endif">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-ship"></i>
                <p>
                    Operand
                </p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="{{ route('withdrawal.index') }}"
                       class="nav-link @if(Request::segment(3) == 'withdrawal') active @endif">
                        <i class="fas fa-map-marker nav-icon"></i>
                        <p>Withdrawals</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('dashboard.schedule.index') }}"
                       class="nav-link @if(Request::segment(3) == 'schedule') active @endif">
                        <i class="fas fa-map-marker nav-icon"></i>
                        <p>Schedules</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('dashboard.routes.index') }}"
                       class="nav-link @if(Request::segment(3) == 'route') active @endif">
                        <i class="fas fa-route nav-icon"></i>
                        <p>Routes</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('dashboard.ghat.index') }}"
                       class="nav-link @if(Request::segment(3) == 'ghat') active @endif">
                        <i class="fas fa-map-marker nav-icon"></i>
                        <p>Stoppages</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('dashboard.cabintype.index') }}"
                       class="nav-link @if(Request::segment(4) == 'type') active @endif">
                        <i class="fas fa-bed nav-icon"></i>
                        <p>Cabin-Seat</p>
                    </a>
                </li>
                @if(auth()->user()->hasAnyPermission(['coupon-list', 'coupon-create']) || auth()->user()->hasRole('admin'))
                    <li class="nav-item">
                        <a href="{{ route('dashboard.coupon.index')}}"
                           class="nav-link @if(Request::segment(3) == 'coupon') active @endif">
                            <i class="fas fa-gift nav-icon"></i>
                            <p>Coupons</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('dashboard.discount.index')}}"
                           class="nav-link @if(Request::segment(3) == 'discount') active @endif">
                            <i class="fas fa-percentage nav-icon"></i>
                            <p>Discounts</p>
                        </a>
                    </li>
                    @if(auth()->user()->hasAnyPermission(['banner-list', 'banner-create']) || auth()->user()->hasRole('admin'))
                        <li class="nav-item">
                            <a href="{{ route('dashboard.banner.index')}}"
                               class="nav-link @if(Request::segment(3) == 'banner') active @endif">
                                <i class="fas fa-image nav-icon"></i>
                                <p>Banners</p>
                            </a>
                        </li>
                    @endif
                    @if(auth()->user()->hasAnyPermission(['social-list', 'social-create']) || auth()->user()->hasRole('admin'))
                        <li class="nav-item">
                            <a href="{{ route('dashboard.social.index')}}"
                               class="nav-link @if(Request::segment(3) == 'social') active @endif">
                                <i class="fas fa-facebook nav-icon"></i>
                                <p>Social media posters</p>
                            </a>
                        </li>
                    @endif
                @endif
            </ul>
        </li>
    @endif

    @if(auth()->user()->hasAnyPermission(['agent-list', 'agent-create']) || auth()->user()->hasRole('admin'))
        <li class="nav-item @if(Request::segment(2) =='agent') menu-open @endif">
            <a href="{{ route('agent.index') }}" class="nav-link">
                <i class="nav-icon fas fa-user-secret"></i>
                <p>
                    Agents
                </p>
            </a>
        </li>
    @endif

    @if(auth()->user()->hasAnyPermission(['partner-list', 'partner-create']) || auth()->user()->hasRole('admin'))
        <li class="nav-item @if(Request::segment(2) =='partner') menu-open @endif">
            <a href="{{ route('partner.index') }}" class="nav-link">
                <i class="nav-icon fas fa-user-secret"></i>
                <p>
                    Partners
                </p>
            </a>
        </li>
    @endif

    @if(auth()->user()->hasAnyPermission(['customer-list']) || auth()->user()->hasRole('admin'))
        <li class="nav-item @if(Request::segment(2) =='customer') menu-open @endif">
            <a href="{{ route('dashboard.customer.index') }}" class="nav-link">
                <i class="nav-icon fas fa-users"></i>
                <p>
                    Customers
                </p>
            </a>
        </li>
    @endif
    @if(auth()->user()->hasAnyPermission(['booking-list', 'bookings-list']) || auth()->user()->hasRole('admin'))
        <li class="nav-item has-treeview @if(Request::segment(2) =='booking') menu-open @endif">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-shopping-cart"></i>
                <p>
                    Operations
                </p>
            </a>
            <ul class="nav nav-treeview">
                @foreach($service_list as $key => $value)
                    <li class="nav-item @if(isset($_GET['type']) && $_GET['type'] == $key) menu-open @endif"">
                    <a href="#"
                       class="nav-link @if(isset($_GET['type']) && $_GET['type'] == $key) menu-open @endif">
                        @switch($key)
                            @case('launch')
                                <i class="fas fa-ship nav-icon"></i>
                                @break
                            @case('train')
                                <i class="fas fa-train nav-icon"></i>
                                @break
                            @case('bus')
                                <i class="fas fa-bus nav-icon"></i>
                                @break
                            @case('air')
                                <i class="fas fa-airbnb nav-icon"></i>
                                @break
                        @endswitch

                        <p>{{ $value }}</p>
                    </a>
                    <ul class="nav nav-treeview">
                        @if(auth()->user()->hasAnyPermission(['booking-list']) || auth()->user()->hasRole('admin'))
                            <li class="nav-item">
                                <a href="{{ route('dashboard.booking.index', ['type' => $key]) }}"
                                   class="nav-link @if(Request::segment(2) == 'booking' && Request::segment(3) == '' && isset($_GET['type']) && $_GET['type'] == $key) active @endif">
                                    <i class="fas fa-address-book nav-icon"></i>
                                    <p>Booking list</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('dashboard.payment.index', ['type' => $key]) }}"
                                   class="nav-link @if(Request::segment(3) == 'payment' && isset($_GET['type']) && $_GET['type'] == $key) active @endif">
                                    <i class="fas fa-money-bill-alt nav-icon"></i>
                                    <p>Payments</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('dashboard.cancellation.index', ['type' => $key]) }}"
                                   class="nav-link @if(Request::segment(3) == 'cancellation' && isset($_GET['type']) && $_GET['type'] == $key) active @endif">
                                    <i class="fas fa-times-circle nav-icon"></i>
                                    <p>Cancellations</p>
                                </a>
                            </li>
                        @endif
                    </ul>
        </li>
        @endforeach
</ul>
</li>
@endif


@if(auth()->user()->hasAnyPermission(['report-list']) || auth()->user()->hasRole('admin'))
    <li class="nav-item has-treeview @if(Request::segment(2) =='report') menu-open @endif">
        <a href="#" class="nav-link">
            <i class="nav-icon fas fa-chart-pie"></i>
            <p>
                Reports
            </p>
        </a>
        <ul class="nav nav-treeview">
            <li class="nav-item">
                <a href="{{ route('dashboard.report.index')}}"
                   class="nav-link @if(Request::segment(2) == 'report' && Request::segment(3) == '') active @endif">
                    <i class="fas fa-chart-bar nav-icon"></i>
                    <p>Report list</p>
                </a>
            </li>
        </ul>
    </li>
@endif
@can('page-list')
    <li class="nav-item @if(Request::segment(2) =='page') menu-open @endif">
        <a href="{{ route('dashboard.page.index') }}" class="nav-link">
            <i class="nav-icon fas fa-leaf"></i>
            <p>
                Pages
            </p>
        </a>
    </li>
@endcan
@can('blog-list')
    <li class="nav-item has-treeview @if(Request::segment(2) =='blog') menu-open @endif">
        <a href="#" class="nav-link">
            <i class="nav-icon fas fa-list"></i>
            <p>
                Blog
            </p>
        </a>
        <ul class="nav nav-treeview">
            <li class="nav-item">
                <a href="{{ route('dashboard.blog.create')}}"
                   class="nav-link @if(Request::segment(2) == 'blog' && Request::segment(3) == 'create') active @endif">
                    <i class="fas fa-plus nav-icon"></i>
                    <p>Add new blog</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('dashboard.blog.index') }}"
                   class="nav-link @if(Request::segment(2) == 'blog' && Request::segment(3) == '') active @endif">
                    <i class="fas fa-list-alt nav-icon"></i>
                    <p>Blogs</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('dashboard.blogcatagory.index')}}"
                   class="nav-link @if(Request::segment(2) == 'blog' && Request::segment(3) == 'catagory') active @endif">
                    <i class="fas fa-code-branch nav-icon"></i>
                    <p>Catagories</p>
                </a>
            </li>
        </ul>
    </li>
@endcan
@if(Auth::user()->type == 'merchant')
    @canany(['launch-list', 'vehicle-list', 'vehicle-manage', 'vehicle-add', 'vehicle-update', 'vehicle-view'])
        <li class="nav-item has-treeview @if(Request::segment(2) =='vehicle') menu-open @endif">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-car"></i>
                <p>
                    Vehicles
                </p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="{{ route('dashboard.vehicle.index')}}"
                       class="nav-link @if(Request::segment(2) == 'vehicle' && Request::segment(3) == '') active @endif">
                        <i class="fas fa-ship nav-icon"></i>
                        <p>vehicles</p>
                    </a>
                </li>
                @can('schedule-list')
                    <li class="nav-item">
                        <a href="{{ route('dashboard.schedule.index')}}"
                           class="nav-link @if(Request::segment(3) == 'schedule') active @endif">
                            <i class="fas fa-clock nav-icon"></i>
                            <p>Schedules</p>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
    @endcanany

    @if(auth()->user()->hasAnyPermission(['merchant-report']) || auth()->user()->hasRole('merchant'))
        <li class="nav-item has-treeview @if(Request::segment(2) =='report') menu-open @endif">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-chart-pie"></i>
                <p>
                    Reports
                </p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="{{ route('merchant.report.index')}}"
                       class="nav-link @if(Request::segment(2) == 'report' && Request::segment(3) == '') active @endif">
                        <i class="fas fa-chart-bar nav-icon"></i>
                        <p>Summary</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('merchant.report.statistics') }}"
                       class="nav-link @if(Request::segment(2) == 'report' && Request::segment(3) == 'statistics') active @endif">
                        <i class="fas fa-chart-area nav-icon"></i>
                        <p>Statistics</p>
                    </a>
                </li>
            </ul>
        </li>
    @endif

@endif
@hasanyrole('admin|merchant|manager')
<li class="nav-item has-treeview @if(Request::segment(2) =='setting') menu-open @endif">
    <a href="#" class="nav-link">
        <i class="nav-icon fas fa-wrench"></i>
        <p>
            Settings
        </p>
    </a>
    <ul class="nav nav-treeview">
        @role('admin')
        <li class="nav-item">
            <a href="{{ route('gateway.index')}}"
               class="nav-link @if(Request::segment(3) == 'gateway') active @endif">
                <i class="fas fa-user-secret nav-icon"></i>
                <p>Gateway manager</p>
            </a>
        </li>
        @endrole
        <li class="nav-item">
            <a href="{{ route('broadcast.index')}}"
               class="nav-link @if(Request::segment(3) == 'broadcast') active @endif">
                <i class="fas fa-hornbill nav-icon"></i>
                <p>Broadcasting</p>
            </a>
        </li>
        @canany(['sponsor-list'])
            <li class="nav-item">
                <a href="{{ route('dashboard.sponsor.index')}}"
                   class="nav-link @if(Request::segment(3) == 'sponsor') active @endif">
                    <i class="fas fa-user-secret nav-icon"></i>
                    <p>Sponsors</p>
                </a>
            </li>
        @endcanany
        @canany(['user-list', 'user-create', 'user-edit', 'user-delete', 'user-approve', 'user-suspend', 'supervisor-add', 'supervisor-edit', 'supervisor-active', 'supervisor-suspend'])
            <li class="nav-item">
                <a href="{{ route('dashboard.user.index')}}"
                   class="nav-link @if(Request::segment(3) == 'user') active @endif">
                    <i class="fas fa-user-secret nav-icon"></i>
                    <p>Users</p>
                </a>
            </li>
        @endcanany
        @hasrole('admin')
        <li class="nav-item">
            <a href="{{ route('dashboard.role.index') }}"
               class="nav-link @if(Request::segment(3) == 'role' && Request::segment(3) == '') active @endif">
                <i class="fas fa-tags nav-icon"></i>
                <p>Roles</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('dashboard.permission.index')}}"
               class="nav-link @if(Request::segment(3) == 'permission') active @endif">
                <i class="fas fa-cogs nav-icon"></i>
                <p>Permissions</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('dashboard.option.index')}}"
               class="nav-link @if(Request::segment(3) == 'option') active @endif">
                <i class="fas fa-cog nav-icon"></i>
                <p>Options</p>
            </a>
        </li>
        @endhasrole
    </ul>
</li>
@endhasanyrole
</ul>

<!-- <a href="#" class="nav-link sidebarLogout">
  <i class="fas fa-sign-out-alt"></i> Sign out
</a> -->
