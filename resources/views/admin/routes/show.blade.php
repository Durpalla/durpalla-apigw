@extends('layouts.master')
@section('content')
<section class="content content-overlap">
  <div class="row">
          <div class="col-12 col-md-12 col-lg-8 order-2 order-md-1">

            <div class="row">
              <div class="col-12">
                <h4>Fare Managment</h4><hr>
                @foreach( $returnArr as $propertie )
                <div class="row row-striped">
                  <div class="col-12">
                    <h3 class="text-uppercase"><strong>{{$propertie['start'].'-'.$propertie['end']}}</strong></h3>
                    <ul class="list-inline">
                      <li class="list-inline-item"><i class="fa fa-calendar-o" aria-hidden="true"></i>Fare:</li>
                      <li class="list-inline-item"><i class="fa fa-clock-o" aria-hidden="true"></i> 0.00/-</li>
                      <li class="list-inline-item"><i class="fa fa-location-arrow" aria-hidden="true"></i>{{$propertie['start']}}</li>
                    </ul>
                  </div>
                </div>
                @endforeach
              </div>
            </div>
        </div>
          <div class="col-12 col-md-12 col-lg-4 order-1 order-md-2">
            <h3 class="text-secondary"><i class="fas fa-plus"></i> Add Fare to Route</h3><hr>
            <form action="{{ route('dashboard.schedule.store') }}" method="POST">
              @csrf
              <input type="hidden" name="vehicle_id" value="">
              <input type="hidden" name="tab" value="schedule">
              <div class="form-group">
                <button class="btn btn-block btn-primary" type="submit">Save</button>
              </div>
            </form>
          </div>
</section>
@endsection
@section('header')

  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.css') }}">
  <!-- iCheck for checkboxes and radio inputs -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
  <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <style type="text/css">
      .row-striped:nth-of-type(odd){
        background-color: #efefef;
        border-left: 4px #000000 solid;
      }

      .row-striped:nth-of-type(even){
        background-color: #ffffff;
        border-left: 4px #efefef solid;
      }

      .row-striped {
        padding: 15px 0;
      }
      .grid {
        position: relative;
      }
      .item {
        display: block;
        position: absolute;
        width: 100px;
        height: 100px;
        margin: 5px;
        z-index: 1;
        background: #000;
        color: #fff;
      }
      .item.muuri-item-dragging {
        z-index: 3;
      }
      .item.muuri-item-releasing {
        z-index: 2;
      }
      .item.muuri-item-hidden {
        z-index: 0;
      }
      .item-content {
        position: relative;
        width: 100%;
        height: 100%;
      }
      .cabin-card {
        text-align: center;
        padding: 25px;
        font-size: 24px;
        font-weight: bold;
        padding-bottom: 35px;
      }
      .cabin-card .cabinOverlap {
        position: absolute;
        top: 0;
        right: 0;
        width: auto;
        padding: 2px 10px;
        background: yellow;
        font-size: 16px;
      }
      .cabin-card .cabinPrice {
        position: absolute;
        bottom: 0;
        right: 0;
        left: 0;
        background: #219876;
        color: #fff;
        font-size: 18px;
      }
    </style>
    @endsection

    @section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
  @endsection
