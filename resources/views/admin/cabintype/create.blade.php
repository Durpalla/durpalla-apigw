@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content">
  <div class="row">
    <div class="col-12 content-overlap">
      <!-- /.card-header -->
      <div class="col-md-7">
        <div class="card-body">

          <form role="form" action="{{ route('dashboard.cabintype.store') }}" method="POST">
            @csrf
                <!-- text input -->
                <div class="form-group">
                    <label>Service type</label>
                    <select name="service_type" class="form-control @error('service_type') is-invalid @enderror"
                            placeholder="Service name"required>
                        @foreach($service_list as $key => $value)
                            <option value="{{ $key }}" @if(old('service_type') == $key) selected @endif>{{ $value }}</option>
                        @endforeach
                    </select>
                    @error('service_type')
                    <div class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </div>
                    @enderror
                </div>
                <div class="form-group">
                  <label>For</label>
                  <select name="type" value="{{ old('type') }}"  class="form-control @error('type') is-invalid @enderror" required>
                    <option value="cabin">Cabin</option>
                    <option value="seat">Seat</option>
                    </select>
                  @error('type')
                  <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                  @enderror
                </div>
                <!-- text input -->
                <div class="form-group">
                  <label>Name</label>
                  <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="Cabin type" value="{{ old('name') }}" required>
                  @error('name')
                  <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                  @enderror
                </div>
                <div class="form-group">
                  <label>Type Letter.</label>
                  <input type="text" name="letter" value="{{ old('letter') }}" placeholder="Exp. (S = Single, D = Double, F = Family)" class="form-control @error('letter') is-invalid @enderror" required>
                  @error('letter')
                  <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                  @enderror
                </div>

                <div class="form-group">
                  <label>Passenger capacity</label>
                  <select name="capacity" value="{{ old('capacity') }}"  class="form-control @error('capacity') is-invalid @enderror" required>
                    <option>1</option>
                    <option>2</option>
                    <option>3</option>
                    <option>4</option>
                    <option>5</option>
                    </select>
                  @error('capacity')
                  <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                  @enderror
                </div>

                <div class="form-group">
                  <input type="checkbox" id="isAc" name="is_ac" value="1">
                  <label>AC available</label>
                  @error('is_ac')
                  <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                  @enderror
                </div>
            <hr>
            <div class="form-group">
              <button type="submit" class="btn btn-lg btn-primary">Create cabin type</button>
            </div>
          </form>
        </div>
      </div>
      <div class="col-sm-5">
      </div>
    </div>
  </div>
</section>
@endsection

@section('footer')
@endsection
