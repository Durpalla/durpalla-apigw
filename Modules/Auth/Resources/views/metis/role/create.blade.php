@extends('metis::layouts.master')

@section('content')
    <div id="content">
        <div class="outer">
            <div class="inner bg-light lter">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="box">
                            <header class="dark">
                                <div class="icons"><i class="fa fa-plus"></i></div>
                                <h5>{{ $title ?? 'Add new role' }}</h5>
                                <!-- .toolbar -->
                                <div class="toolbar">
                                    <nav style="padding: 8px;">

                                    </nav>
                                </div>
                            </header>
                            <div id="collapse2" class="body">
                                <form action="{{ route('role.store') }}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <label class="control-label col-lg-4">Name</label>
                                        <div class="col-lg-8">
                                            <div class="input-group">
                                                <input type="text" value="{{ old('name') }}"
                                                       class="validate[required] form-control" name="name"
                                                       id="req" placeholder="Role name" required>
                                                <div class="input-group-btn p-0">
                                                    <button type="submit" class="btn btn-primary">CREATE</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <hr/>
                                    <h4>Assign permissions</h4>
                                    <div class="row">
                                        @foreach(array_chunk($permissions, 4) as $k => $chunks )
                                            @foreach($chunks as $key => $lists)
                                                <div class="col-lg-3 col-md-3 col-sm-3 col-xs-12 box-item"
                                                     id="permissionParent">
                                                    <div class="box">
                                                        <header class="dark">
                                                            <h5><input type="checkbox" class="checkedAll"> {{ ucfirst( substr($lists[0]->name, 0, strpos($lists[0]->name, '-') ) ) }}</h5>
                                                        </header>

                                                        <div class="body">
                                                            @foreach( $lists as $list )
                                                                <div class="form-check">
                                                                    <label>
                                                                        <input type="checkbox"
                                                                               class="checkedItem"
                                                                               name="permission[]"
                                                                               value="{{ $list->id }}">
                                                                        <span
                                                                            class="label-text">{{ ucwords( str_replace('-', ' ', $list->name ) ) }}</span>
                                                                    </label>
                                                                </div>
                                                            @endforeach
                                                        </div>

                                                    </div>
                                                </div>
                                            @endforeach
                                            <div class="clearfix"></div>
                                        @endforeach
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

@section('footer')
    <script>
        jQuery(function ($) {
            $('.checkedAll').on('click', function (e) {
                let parent = $(this).parents('#permissionParent');
                if($(this).is(':checked')) {
                    $(parent).find('.checkedItem').each(function() {
                        $(this).attr('checked', true);
                    });
                } else {
                    $(parent).find('.checkedItem').attr('checked', false)
                }
            })
        })
    </script>
@endsection
