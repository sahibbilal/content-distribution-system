<?php

return [

    'worker' => [
        'url' => env('CLAUDE_WORKER_URL'),
        'secret' => env('CLAUDE_WORKER_SECRET'),
    ],

    'elevenlabs' => [
        'key' => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID'),
        'model_id' => env('ELEVENLABS_MODEL_ID', 'eleven_v3'),
        'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.8, 'style' => 0.2, 'use_speaker_boost' => true],
    ],

];
