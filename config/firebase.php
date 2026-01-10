<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'credentials' => storage_path('app/private/durpalla-e169c-firebase-adminsdk-fbsvc-19642056ec.json'),
    'params' => [

    ],
    'endpoints' => [
        'token' => 'https://oauth2.googleapis.com/token',
        'execute' => 'https://fcm.googleapis.com/v1/projects/durpalla-e169c/messages:send'
    ]
];
