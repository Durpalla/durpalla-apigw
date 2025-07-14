<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }} | {{ $title ?? '' }}</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/fontawesome-free/css/all.min.css') }}">
    <!-- overlayScrollbars -->
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css') }}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/dist/css/adminlte.min.css') }}">
    <!-- Google Font: Source Sans Pro -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('css/custom.css')}}">
    <style type="text/css">
        .sidebarLogout {
            position: absolute;
            bottom: 0;
            background: #219876;
            right: 0;
            left: 0;
            color: #eeabab !important;
        }

        #addProperty {
            cursor: pointer;
        }

        #dashboardCalendarFilter #datepicker {
            background: #8c8d915c;
            color: #d2bbbb;
            border: 0;
            width: 286px;
            padding: 5px 15px;
            border-radius: 50px;
            padding-left: 45px;
        }

        #dashboardCalendarFilter .fa {
            position: absolute;
            margin: 11px 15px;
            font-size: 25px;
            color: #219876;
        }

        #loadingWrapper {
            position: fixed;
            width: 100%;
            left: 0;
            right: 0;
            top: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.7);
            z-index: 9999;
        }

        @-webkit-keyframes spin {
            from {
                -webkit-transform: rotate(0deg);
            }
            to {
                -webkit-transform: rotate(360deg);
            }
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        #loadingWrapper::after {
            content: '';
            display: block;
            position: absolute;
            left: 48%;
            top: 40%;
            width: 40px;
            height: 40px;
            border-style: solid;
            border-color: black;
            border-top-color: transparent;
            border-width: 4px;
            border-radius: 50%;
            -webkit-animation: spin .8s linear infinite;
            animation: spin .8s linear infinite;
        }
        #reload {
            position: absolute;
            top:0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 999;
            background: #0A246A1F;
            display: none;
        }
        #reload .reload-content {
            position: fixed;
            top: 50%;
            left: 50%;
            /* bring your own prefixes */
            transform: translate(-50%, -50%);
            color: #1ea0ee;
            background: #ffffff63;
            border-radius: 150%;
        }
    </style>
    @yield('header')
    @php
        $user = auth()->user();
        $avatar = ($user->profile_pic) ? asset($user->profile_pic) : asset('default/avatar.png');
    @endphp
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div id="reload">
    <div class="reload-content">
        <i class="fa fa-radiation fa-spin fa-4x"></i>
    </div>
</div>
<div id="loadingWrapper" class="d-none"></div>
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <!-- SEARCH FORM -->
        <form class="form-inline ml-3">
            <!-- <div class="input-group input-group-sm">
              <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
              <div class="input-group-append">
                <button class="btn btn-navbar" type="submit">
                  <i class="fas fa-search"></i>
                </button>
              </div>
            </div> -->
            @canany(['booking-quick', 'other-quick-book'])
                <a href="{{ route('dashboard.quickbook') }}" class="btn btn-success">Quick book</a>
        @endcanany
        <!-- <a href="{{ route('dashboard.other.confirmation') }}" class="btn btn-primary">Confirm booking</a> -->
        </form>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- Messages Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-comments"></i>
                    <span class="badge badge-danger navbar-badge">3</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <a href="#" class="dropdown-item">
                        <!-- Message Start -->
                        <div class="media">
                            <img src="{{ $avatar }}" alt="{{ $user->name }}" class="img-size-50 mr-3 img-circle">
                            <div class="media-body">
                                <h3 class="dropdown-item-title">
                                    Brad Diesel
                                    <span class="float-right text-sm text-danger"><i class="fas fa-star"></i></span>
                                </h3>
                                <p class="text-sm">Call me whenever you can...</p>
                                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
                            </div>
                        </div>
                        <!-- Message End -->
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <!-- Message Start -->
                        <div class="media">
                            <img src="{{ asset('assets/plugins/AdminLte/dist/img/user8-128x128.jpg') }}"
                                 alt="User Avatar" class="img-size-50 img-circle mr-3">
                            <div class="media-body">
                                <h3 class="dropdown-item-title">
                                    John Pierce
                                    <span class="float-right text-sm text-muted"><i class="fas fa-star"></i></span>
                                </h3>
                                <p class="text-sm">I got your message bro</p>
                                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
                            </div>
                        </div>
                        <!-- Message End -->
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <!-- Message Start -->
                        <div class="media">
                            <img src="{{ asset('assets/plugins/AdminLte/dist/img/user3-128x128.jpg') }}"
                                 alt="User Avatar" class="img-size-50 img-circle mr-3">
                            <div class="media-body">
                                <h3 class="dropdown-item-title">
                                    Nora Silvester
                                    <span class="float-right text-sm text-warning"><i class="fas fa-star"></i></span>
                                </h3>
                                <p class="text-sm">The subject goes here</p>
                                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
                            </div>
                        </div>
                        <!-- Message End -->
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer">See All Messages</a>
                </div>
            </li>
            <!-- Notifications Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-bell"></i>
                    <span class="badge badge-warning navbar-badge">0</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header">0 Notifications</span>
                    <div class="dropdown-divider"></div>
                    <!-- <a href="#" class="dropdown-item">
                      <i class="fas fa-envelope mr-2"></i> 4 new messages
                      <span class="float-right text-muted text-sm">3 mins</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                      <i class="fas fa-users mr-2"></i> 8 friend requests
                      <span class="float-right text-muted text-sm">12 hours</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                      <i class="fas fa-file mr-2"></i> 3 new reports
                      <span class="float-right text-muted text-sm">2 days</span>
                    </a> -->
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
                </div>
            </li>
            <!-- <li class="nav-item">
              <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#"><i
                  class="fas fa-th-large"></i></a>
                </li> -->
            <li class="nav-item dropdown ml-3">
                <a class="nav-link" data-toggle="dropdown" href="#" style="padding:0;">
                    <img src="{{ $avatar }}" alt="{{ $user->name }}" class="img-size-50 img-circle"
                         style="width:50px; height:50px;margin-top: -5px; height: 50px;">
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
              <span class="dropdown-item dropdown-header" style="background: #219876;">
                <img src="{{ $avatar }}" alt="{{ $user->name }}" class="img-size-150 img-circle"
                     style="width:150px; height: 150px;padding: 5px; border: 2px solid #41c29d; background: #fff;"><br>
                <span class="text-center"
                      style="font-size: 16px; padding: 25px; color: #fff; line-height: 200%;">{{ $user->name }}</span>
              </span>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('dashboard.user.profile') }}" class="dropdown-item">
                        <i class="fas fa-user mr-2"></i> My profile
                    </a>
                    @if(session()->has('master_id'))
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('shadow_sessions.show', session()->get('master_id')) }}" class="dropdown-item">
                        <i class="fas fa-sign-out-alt mr-2"></i> End shadow session
                    </a>
                    @endif
                    <div class="dropdown-divider"></div>
                    <form action="{{ route('logout') }}" method="POST" class="form-inline">
                        @csrf
                        <button type="submit" class="dropdown-item dropdown-footer"><i class="fas fa-sign-out-alt"></i>
                            Logout
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="index3.html" class="brand-link">
            <img src="{{ asset('default/logo.png') }}" alt="{{ config('app.name') }}"
                 class="brand-image"
                 style="opacity: 1">
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel (optional) -->
            <div class="user-panel pt-3 pb-3 pb-3 mb-5 text-center">
                <div class="info">
                    <a href="{{ route('home') }}" class="d-block">{{ $user->name }}</a>
                    <p class="mb-2 text-white"><small>{{ $user->email }}</small></p>
                </div>
                <div class="image">
                    <img src="{{ $avatar }}" class="img-circle elevation-2" style="width:70px;height:70px;"
                         alt="{{ $user->name }}">
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                @include('elements.admin.nav')
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-7">
                        <h1 class="m-0 mt-2 pl-2 text-white">
                            {{ $title ?? '' }}
                            @if( Request::segment(2) == '' )
                                <span class="date" id="dashboardCalendarFilter"><i class="fa fa-calendar"></i> <input
                                        type="text" id="datepicker" name=""
                                        value="{{ date('F-Y', strtotime($month)) }}"></span>
                            @endif
                        </h1>
                    </div><!-- /.col -->
                    <div class="col-sm-5">
                        <ol class="breadcrumb float-sm-right mt-2">
                            @php
                                $segments = Request::segments();
                                $segmentCount = count( $segments ) - 1;
                                foreach( $segments as $key => $segment) :
                                if( is_integer( $segment ) ) :
                                $segmentCount = $segmentCount - 1;
                                else :
                            @endphp
                            <li class="breadcrumb-item @php echo ( $segmentCount == $key ) ? 'active' : ''; @endphp">{{ ucfirst( str_replace('-', ' ', $segment) ) }}</li>
                            @php
                                endif;
                                endforeach;
                            @endphp
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content p-3">
            <div class="container-fluid">
                @yield('content')
            </div><!--/. container-fluid -->
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content goes here -->
    </aside>
    <!-- /.control-sidebar -->

    <!-- Main Footer -->
    <footer class="main-footer">
        &reg; All rights reserved.
    </footer>

    <!-- Modal -->
    <div class="modal fade" id="defaultModal" data-backdrop="static" tabindex="-1" role="dialog"
         aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="/" id="defaultModalForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel"></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">

                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="{{ asset('assets/plugins/AdminLte/plugins/jquery/jquery.min.js') }}"></script>
<!-- Bootstrap -->
<script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<!-- overlayScrollbars -->
<script
    src="{{ asset('assets/plugins/AdminLte/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') }}"></script>

<script type="text/javascript" src="{{ asset('js/sweetalert.min.js')}}"></script>
<!-- <script type="text/javascript" src="{{ asset('js/polyfill.js')}}"></script> -->
<!-- PAGE PLUGINS -->
<!-- jQuery Mapael -->
<!-- <script src="{{ asset('assets/plugins/AdminLte/plugins/jquery-mousewheel/jquery.mousewheel.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/raphael/raphael.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/jquery-mapael/jquery.mapael.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/jquery-mapael/maps/usa_states.min.js') }}"></script> -->

<!-- AdminLTE App -->
<script src="{{ asset('assets/plugins/AdminLte/dist/js/adminlte.js') }}"></script>

<!-- OPTIONAL SCRIPTS -->
<script src="{{ asset('assets/plugins/AdminLte/dist/js/demo.js') }}"></script>
<script src="{{ asset('js/history-tabs.js') }}"></script>

<script type="text/javascript">
    let reload = $('#reload');
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        onOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    @if(Session::has('message'))
    Toast.fire({
        icon: '{{ Session::get('message.label')}}',
        title: '{{ Session::get('message.content')}}'
    });
    @endif
    @if ($errors->any())
    Toast.fire({
        icon: 'error',
        title: '{{ $errors->first() }}'
    });
    @endif
    @if ($message = Session::get('info'))
    Toast.fire({
        icon: 'info',
        title: '{{ $message }}'
    });
    @endif
    @if ($message = Session::get('warning'))
    Toast.fire({
        icon: 'warning',
        title: '{{ $message }}'
    });
    @endif
    @if ($message = Session::get('danger'))
    Toast.fire({
        icon: 'danger',
        title: '{{ $message }}'
    });
    @endif
    @if ($message = Session::get('error'))
    Toast.fire({
        icon: 'error',
        title: '{{ $message }}'
    });
    @endif
    @if ($message = Session::get('success'))
    Toast.fire({
        icon: 'success',
        title: '{{ $message }}'
    });
    @endif
    let loading = $('#loadingWrapper');

    let defaultModal = $('#defaultModal');
    $('a[data-toggle="tab"]').historyTabs();
    {{--let parties = {!! json_encode($party_dropdowns) !!}--}}

    jQuery(function($) {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        // $(document).ajaxStop(function (e) {
        //     $(reload).hide();
        // });
        // $(document).ajaxSend(function(evt, request, settings) {
        //     $(reload).show();
        // });
    });
</script>
<!-- PAGE SCRIPTS -->
<!-- <script src="{{ asset('assets/plugins/AdminLte/dist/js/pages/dashboard2.js') }}"></script> -->
@yield('footer')
</body>
</html>
