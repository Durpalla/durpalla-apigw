@extends('default::layouts.master')

@section('content')
    <x-default::toolbar title="{{ $title ?? 'Add new role' }}"></x-default::toolbar>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
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
                                                <h5><input type="checkbox"
                                                           class="checkedAll"> {{ ucfirst( substr($lists[0]->name, 0, strpos($lists[0]->name, '-') ) ) }}
                                                </h5>
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
    </div>
@endsection

@section('footer')
    <script>
        jQuery(function ($) {
            $('.checkedAll').on('click', function (e) {
                let parent = $(this).parents('#permissionParent');
                if ($(this).is(':checked')) {
                    $(parent).find('.checkedItem').each(function () {
                        $(this).attr('checked', true);
                    });
                } else {
                    $(parent).find('.checkedItem').attr('checked', false)
                }
            })
        })
    </script>
@endsection
