@extends('metis::layouts.master')

@section('content')
    <div id="content">
        <div class="outer">
            <div class="inner bg-light lter">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="box">
                            <header class="dark">
                                <div class="icons"><i class="fa fa-check"></i></div>
                                <h5>{{ $title ?? 'Update gateway' }}</h5>
                                <!-- .toolbar -->
                                <div class="toolbar">
                                    <nav style="padding: 8px;">
                                        <a href="{{ route('gateway.index') }}" class="btn btn-default btn-sm">
                                            <i class="fa fa-angle-left"></i> back
                                        </a>
                                    </nav>
                                </div>
                                <!-- /.toolbar -->
                            </header>
                            <div id="collapse2" class="body">
                                <form class="form-horizontal" id="popup-validation"
                                      action="{{ route('gateway.update', $gateway->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <div class="form-group">
                                        <label class="control-label col-lg-4">Name</label>
                                        <div class="col-lg-4">
                                            <input type="text" value="{{ old('name', $gateway->name) }}"
                                                   class="validate[required] form-control" name="name"
                                                   id="req" placeholder="Name">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="control-label col-lg-4">Namespace</label>
                                        <div class="col-lg-4">
                                            <input type="text" value="{{ old('class_name', $gateway->class_name) }}"
                                                   class="validate[required] form-control" name="class_name"
                                                   id="req" placeholder="Namespace">
                                        </div>
                                    </div>

                                    @if(\App\Helpers\CommonHelper::hasPermission(['gateway-action']))
                                        <div class="form-group">
                                            <label class="control-label col-lg-4">Status</label>
                                            <div class="col-lg-4">
                                                <select class="form-control" name="status">
                                                    <option value="1"
                                                            @if(old('status', $gateway->status) == 1) selected @endif>
                                                        Active
                                                    </option>
                                                    <option value="0"
                                                            @if(old('status', $gateway->status) == 0) selected @endif>
                                                        Inactive
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="form-actions no-margin-bottom">
                                        <label class="control-label col-lg-4"></label>
                                        <div class="col-lg-4">
                                            <button type="submit" class="btn btn-primary">UPDATE</button>
                                        </div>
                                        <div class="clearfix"></div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-lg-12 -->
                </div>
                <!-- /.row -->
            </div>
            <!-- /.inner -->
        </div>
        <!-- /.outer -->
    </div>
    <!-- /#content -->
@endsection
