<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'credentials' => storage_path(env('FIREBASE_CREDENTIALS', 'app/private/firebase-credentials.json')),
    'params' => [

    ],
    'endpoints' => [
        'token' => 'https://oauth2.googleapis.com/token',
        'execute' => 'https://fcm.googleapis.com/v1/projects/durpalla-e169c/messages:send'
    ]
];
