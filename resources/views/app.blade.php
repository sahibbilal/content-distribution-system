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
</body>
</html>

