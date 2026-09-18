<?php

use Illuminate\Support\Facades\Route;

// In local development, serve the Vue SPA shell directly from Laravel so
// `npm run dev` + Vite HMR works when you visit this app's URL in the browser.
// In production the frontend is built and deployed separately (Netlify), so
// this backend only needs to expose the API + a health-check root route.
if (app()->environment('local')) {
    Route::get('/{any}', function () {
        return view('app');
    })->where('any', '^(?!api).*$');
} else {
    Route::get('/', function () {
        return response()->json(['message' => 'iCARE API is running.']);
    });
}