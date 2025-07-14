@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content content-overlap">
<form method="post" action="{{ route('dashboard.customer.store')}}" role="form" enctype="multipart/form-data">
  <div class="row">
      <!-- /.card-header -->
      <div class="col-md-7">
        <div class="card-body">
              @csrf
              <div class="form-group">
                <label for="name">Traveller name</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Name" required="">
                @error('name')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label>Email</label>
                <input type="text" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="Email" required="">
                @error('email')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label>Mobile</label>
                <input type="text" name="mobile" id="mobile" class="form-control @error('mobile') is-invalid @enderror" value="{{ old('mobile') }}" placeholder="Mobile" required="">
                @error('mobile')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                 <input type="submit" value="Submit" class="btn btn-primary form-control" />
              </div>
        </div>
      </div>
      <div class="col-5 mt-4">

      </div>
  </div>
</form>
</section>

@endsection

@section('header')

@endsection

@section('footer')

@endsection
