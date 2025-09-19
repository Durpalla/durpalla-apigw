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
                                <h5>{{ $title ?? 'Add new permission' }}</h5>
                                <!-- .toolbar -->
                                <div class="toolbar">
                                    <nav style="padding: 8px;">

                                    </nav>
                                </div>
                            </header>
                            <div id="collapse2" class="body">
                                <form class="form-horizontal" id="popup-validation" action="{{ route('permission.store') }}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <label class="control-label col-lg-4">Permission name</label>
                                        <div class="col-lg-4">
                                            <input type="text" value="{{ old('name') }}" class="validate[required] form-control" name="name"
                                                   id="req" placeholder="Permission name">
                                        </div>
                                    </div>

                                    <div class="form-actions no-margin-bottom">
                                        <label class="control-label col-lg-4"></label>
                                        <div class="col-lg-4">
                                            <button type="submit" class="btn btn-primary">CREATE</button>
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
