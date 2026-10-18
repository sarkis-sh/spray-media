<?php

use SprayMedia\Http\Controllers\DirectUploadController;
use SprayMedia\Http\Controllers\MediaItemController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Media Package API Routes
|--------------------------------------------------------------------------
*/

// The main group reads its prefix from the config file for full customizability.
Route::prefix(Config::get('spray-media.route.prefix', 'media-items'))
    ->group(function () {

        // This is the public-facing endpoint for serving files via signed URLs.
        // Its path and name are fully configurable.
        Route::get(
            Config::get('spray-media.route.path', 'secure'),
            [MediaItemController::class, 'handle']
        )->middleware(Config::get('spray-media.route.middleware_public', ['api']))
            ->name(Config::get('spray-media.route.name', 'secure'));

        // CRUD operations for managing media records.
        Route::controller(MediaItemController::class)
            ->middleware(Config::get('spray-media.route.middleware_admin', ['api']))
            ->group(function () {

                // Route to upload a new file.
                Route::post('/', 'store')->name('media-items.store');

                // Route to update a media record.
                Route::put('/{id}/update-filename', 'updateFileName')->name('media-items.update');

                // Route to delete a media record.
                Route::delete('/{id}', 'delete')->name('media-items.delete');
            });

        // Direct uploads: steps 1 and 3 (step 2 goes straight to the disk).
        // Always registered; they answer 404 while 'direct_uploads.enabled'
        // is off, so a cached route list does not depend on that setting.
        Route::controller(DirectUploadController::class)
            ->middleware(Config::get('spray-media.route.middleware_admin', ['api']))
            ->prefix('direct-uploads')
            ->group(function () {
                Route::post('/', 'initiate')->name('media-items.direct-uploads.initiate');
                Route::post('/complete', 'complete')->name('media-items.direct-uploads.complete');
            });
    });
