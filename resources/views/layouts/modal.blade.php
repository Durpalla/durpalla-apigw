<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{{ config('app.name') }}2</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/fontawesome-free/css/all.min.css') }}">
  <!-- Ionicons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
  <!-- Theme style -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/dist/css/adminlte.min.css') }}">
  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
  @yield('header')
</head>
<body class="hold-transition login-page">
  @yield('content')

<!-- jQuery -->
<script src="{{ asset('assets/plugins/AdminLte/plugins/jquery/jquery.min.js') }}"></script>

<script type="text/javascript" src="{{ asset('js/sweetalert.min.js')}}"></script>
<!-- Bootstrap 4 -->
<script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<!-- AdminLTE App -->
<script src="{{ asset('assets/plugins/AdminLte/dist/js/adminlte.min.js') }}"></script>

<script type="text/javascript">
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
    </script>
    @yield('footer')
</body>
</html>
