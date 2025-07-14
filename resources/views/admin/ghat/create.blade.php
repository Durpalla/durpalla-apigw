@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12 content-overlap">
                <!-- /.card-header -->
                <div class="col-md-7">
                    <div class="card-body">

                        <form role="form" action="{{ route('dashboard.ghat.store') }}" method="POST">
                        @csrf
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
                                <label>Name</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       placeholder="Ghat name" value="{{ old('name') }}" required>
                                @error('name')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Latitude</label>
                                <input type="text" name="latitude" class="form-control @error('latitude') is-invalid @enderror"
                                       placeholder="Latitude" value="{{ old('latitude') }}" required>
                                @error('latitude')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Longitude</label>
                                <input type="text" name="longitude" class="form-control @error('longitude') is-invalid @enderror"
                                       placeholder="Longitude" value="{{ old('longitude') }}" required>
                                @error('longitude')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Altitude (Area)</label>
                                <input type="text" name="altitude" class="form-control @error('altitude') is-invalid @enderror"
                                       placeholder="Altitude" value="{{ old('altitude') }}" required>
                                @error('altitude')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-lg btn-primary">Create ghat</button>
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
