@extends('layouts.master')

@section('content')
  <!-- Main content -->
  <section class="content">
    <div id="reader">
   </div>
  </section>
@endsection

@section('header')

@endsection

@section('footer')
<script type="text/javascript" src="{{ asset('assets/plugins/html5-qrcode/jsqrcode-combined.js')}}"></script>
<script type="text/javascript" src="{{ asset('assets/plugins/html5-qrcode/minified/html5-qrcode.min.js')}}"></script>
<script type="text/javascript">
    let cameraId;
// This method will trigger user permissions
// This method will trigger user permissions 
Html5Qrcode.getCameras().then(cameras => {
      /**
        * devices would be an array of objects of type:
        * { id: "id", label: "label" }
       */
       if (devices && devices.length) {
           cameraId = devices[0].id;
            // .. use this to start scanning.
        }
   }).catch(err => {
        // handle err   
});
const html5QrCode = new Html5Qrcode(“reader”);
html5QrCode.start(
   cameraId, // retreived in the previous step.
   {
      fps: 10,    // sets the framerate to 10 frame per second 
      qrbox: 250  // sets only 250 X 250 region of viewfinder to
                  // scannable, rest shaded.
 },
 qrCodeMessage => {     // do something when code is read. For example:
     console.log(`QR Code detected: ${qrCodeMessage}`);
 },
 errorMessage => {     // parse error, ideally ignore it. For example:
     console.log(`QR Code no longer in front of camera.`);
 })
 .catch(err => {     // Start failed, handle it. For example, 
     console.log(`Unable to start scanning, error: ${err}`);
 });
html5QrCode.stop().then(ignore => {
  // QR Code scanning is stopped. 
  console.log(“QR Code scanning stopped.”);
}).catch(err => { 
  // Stop failed, handle it. 
  console.log(“Unable to stop scanning.”);
 });
</script>
@endsection