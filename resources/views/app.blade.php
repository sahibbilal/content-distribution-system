<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Content Distribution System</title>
    
    @php
        // Load assets from manifest in production
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (isset($manifest['resources/js/app.js'])) {
                $entry = $manifest['resources/js/app.js'];
                // Load CSS files
                if (isset($entry['css']) && is_array($entry['css'])) {
                    foreach ($entry['css'] as $css) {
                        echo '<link rel="stylesheet" href="' . asset('build/' . $css) . '">' . "\n    ";
                    }
                }
                // Load JS file
                if (isset($entry['file'])) {
                    echo '<script type="module" src="' . asset('build/' . $entry['file']) . '"></script>' . "\n    ";
                }
            }
        } else {
            // Fallback to Vite helper if manifest doesn't exist (dev mode)
            @vite(['resources/js/app.js']);
        }
    @endphp
</head>
<body>
    <div id="app"></div>
    
    <!-- Facebook SDK for JavaScript -->
    <script>
      window.fbAsyncInit = function() {
        FB.init({
          appId      : '{{ env('FACEBOOK_APP_ID', '') }}',
          cookie     : true,
          xfbml      : true,
          version    : 'v18.0'
        });
          
        FB.AppEvents.logPageView();
        
        // Parse XFBML tags after SDK is loaded
        FB.XFBML.parse();
      };

      (function(d, s, id){
         var js, fjs = d.getElementsByTagName(s)[0];
         if (d.getElementById(id)) {return;}
         js = d.createElement(s); js.id = id;
         js.src = "https://connect.facebook.net/en_US/sdk.js";
         fjs.parentNode.insertBefore(js, fjs);
       }(document, 'script', 'facebook-jssdk'));
    </script>
</body>
</html>

