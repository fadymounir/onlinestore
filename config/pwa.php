<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web app manifest
    |--------------------------------------------------------------------------
    |
    | Where the manifest is written. It belongs in the document root, because
    | that is where public/sw.js precaches it from and where its "/" scope is
    | read relative to, so the default is the only value a deployment should
    | ever use.
    |
    | The override exists for the test suite. The shop's name lives in the
    | settings table, so App\Filament\Pages\ManageSettings rewrites this file
    | whenever an admin saves — and a test that exercises that save would
    | otherwise leave the repository's committed manifest naming whatever shop
    | the test invented. phpunit.xml points PWA_MANIFEST_PATH at a throwaway
    | file under storage/ instead.
    |
    | A custom value is read relative to the project root.
    |
    */

    'manifest' => env('PWA_MANIFEST_PATH')
        ? base_path(env('PWA_MANIFEST_PATH'))
        : public_path('manifest.json'),

];
