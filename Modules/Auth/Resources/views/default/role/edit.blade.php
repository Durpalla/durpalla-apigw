@extends('default::layouts.master')

@section('content')
    <x-default::toolbar title="{{ $title ?? 'Update role' }}"></x-default::toolbar>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('role.update', $role->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Role Name Input -->
                        <div class="form-group row mb-3">
                            <label class="col-lg-4 col-form-label">Role Name</label>
                            <div class="col-lg-8">
                                <div class="input-group">
                                    <input type="text"
                                           name="name"
                                           value="{{ old('name', $role->name) }}"
                                           class="form-control"
                                           placeholder="Role name"
                                           required>
                                    <button type="submit" class="btn btn-primary">UPDATE</button>
                                </div>
                                @error('name')
                                <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <hr/>

                        <!-- Permission Assignment -->
                        <h4>Assign Permissions</h4>
                        <div class="row">
                            @foreach(array_chunk($permissions, 4) as $chunk)
                                @foreach($chunk as $key => $lists)
                                    <div class="col-lg-3 col-md-4 mb-3">
                                        <div class="card border">
                                            <div class="card-header bg-dark text-white">
                                                @php
                                                    $prefix = explode('-', $lists[0]->name)[0];
                                                @endphp
                                                <input type="checkbox" class="form-check-input me-1 checkedAll"
                                                       id="{{ $prefix }}">
                                                <strong>{{ ucfirst($prefix) }}</strong>
                                            </div>
                                            <div class="card-body">
                                                @foreach($lists as $permission)
                                                    <div class="form-check">
                                                        <input type="checkbox"
                                                               name="permission[]"
                                                               value="{{ $permission->id }}"
                                                               class="form-check-input checkedItem {{ $prefix }}"
                                                               id="perm_{{ $permission->id }}"
                                                               @if(in_array($permission->id, $rolePermissions)) checked @endif>
                                                        <label for="perm_{{ $permission->id }}"
                                                               class="form-check-label">
                                                            {{ ucwords(str_replace('-', ' ', $permission->name)) }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
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
                console.log(parent);
                if ($(this).is(':checked')) {
                    $(parent).find('.checkedItem').attr('checked', true)
                } else {
                    $(parent).find('.checkedItem').attr('checked', false)
                }
            })
        })
    </script>
@endsection
