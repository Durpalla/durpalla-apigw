@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content content-overlap">
        <form method="post" action="{{ route('gateway.update', $gateway->id)}}" role="form">
            @csrf
            @method('PUT')
            <div class="row">
                <!-- /.card-header -->
                <div class="col-7">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Gateway name</label>
                            <input type="text" class="form-control" value="{{ old('name', $gateway->name) }}" name="name"
                                   placeholder="Name" required/>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description"
                                      style="min-height: 80px;">{{ old('description', $gateway->description) }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control" required>
                                <option value="1" @if($gateway->status === 1) selected @endif>Active</option>
                                <option value="0" @if($gateway->status === 0) selected @endif>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="submit" name="Submit" value="Submit" class="btn btn-primary"/>
                        </div>
                    </div>
                </div>
                <div class="col-5">
                </div>
            </div>
        </form>
    </section>

@endsection

@section('header')
@endsection

@section('footer')
@endsection
