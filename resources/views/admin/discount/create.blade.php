@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content content-overlap">
<form method="post" action="{{ route('dashboard.discount.store')}}" role="form" enctype="multipart/form-data" id="couponForm">
  <div class="row">
      <!-- /.card-header -->
      <div class="col-md-7">
        <div class="card-body">
              @csrf
              <div class="form-group">
                <label for="description">Write a note</label>
                <input type="text" name="description" id="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" placeholder="Write note" required="">
                @error('description')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label>Applicable for</label>
                <select name="applicable_to" id="applicableToType" class="form-control @error('applicable_to') is-invalid @enderror" value="{{ old('applicable_to') }}" style="width:100%" required>
                  <option value="">Choose now</option>
                  <option value="merchant" @if(old('applicable_to') == 'merchant') selected @endif>Merchant</option>
                  <option value="jolzan" @if(old('applicable_to') == 'jolzan') selected @endif>Jolzan</option>
                  <option value="both" @if(old('applicable_to') == 'both') selected @endif>Both</option>
                </select>
                @error('applicable_to')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label for="amount">Discount amount</label>
                <div class="input-group mb-3">
                  <div class="input-group-prepend">
                    <div class="input-group-text">
                      Amount
                    </div>
                  </div>
                  <input type="number" min="0" id="amount" name="amount" class="form-control" value="{{ old('amount') }}" required>
                  <div class="input-group-append">
                    <div class="input-group-btn">
                      <select name="type" class="form-control btn btn-default" value="{{ old('type') }}" required>
                        <option value="f" @if(old('type') == 'f') selected @endif>Fixed</option>
                        <option value="p" @if(old('type') == 'p') selected @endif>Percentage</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <div class="select2-purple">
                  <select name="schedule_id" class="select2" id="items" data-placeholder="Select schedule" data-dropdown-css-class="select2-purple" style="width: 100%;">

                  </select>
                </div>
                @error('schedule_id')
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

              <div class="form-group clearfix">
                <label>Applicable to</label>
                <div class="icheck-primary">
                  <input type="checkbox" class="cancel-item" id="checkboxPrimaryCabin" name="is_cabin" value="1" checked>
                  <label for="checkboxPrimaryCabin">
                    Cabin
                  </label>
                </div>
                <div class="icheck-primary">
                  <input type="checkbox" class="cancel-item" id="checkboxPrimarySeat" name="is_seat" value="1">
                  <label for="checkboxPrimarySeat">
                    Seat
                  </label>
                </div>
                <div class="icheck-primary">
                  <input type="checkbox" class="cancel-item" id="checkboxPrimaryDeck" name="is_deck" value="1">
                  <label for="checkboxPrimaryDeck">
                    Deck
                  </label>
                </div>
              </div>
      </div>
  </div>
</form>
</section>

@endsection

@section('header')
  <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
@endsection

@section('footer')
<script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
  $(function() {

    $('#couponForm').on('submit',(function(e){
      e.defaultPrevented;
      $.ajaxSetup({
          headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
      });
      let url = $(this).attr('action');
      let form = $(this);
      console.log(url);
      $.ajax({
        url: url,
        type: "POST",
        data:  new FormData(this),
        contentType: false,
        cache: false,
        processData:false,
        success: function(data){
          if( data.status ) {
            $(form).trigger("reset");
            $('#pickSomeBlock').hide();
          }
          Toast.fire({
              icon: data.label,
              title: data.content
          });
        },
        error: function(){
          Toast.fire({
              icon: 'error',
              title: 'Sorry! something went wrong'
          });
        }
      });
      return false;
    }));

    $('#applicableToType').change( function(e) {
      let val = $(this).val();
      initializeSelect2(val);
    });

    @if( old('type') != 'period' )
      initializeSelect2("{{ old('type') }}");
    @endif

    function initializeSelect2(val)
    {
      let items = $('#items');
      $(items).html(null);
      let url = "{{ route('dashboard.discount.suggest') }}";

      // let url = "https://api.github.com/orgs/select2/repos";
      $(items).select2({
        placeholder: "Select schedule",
          allowClear: true,
          cache: false,
          theme: 'bootstrap4',
          ajax: {
              url: url,
              dataType: 'json',
              type: "GET",
              quietMillis: 50,
              data: function (term) {
                  return {
                      term: term.term
                  };
              },
              processResults: function (data) {
                  var myResults = [];
                  $.each(data.results, function (index, item) {
                      myResults.push({
                          'id': item.id,
                          'text': item.name
                      });
                  });
                  return {
                      results: myResults
                  };
              }
          }
      });
    }

});
</script>
@endsection
