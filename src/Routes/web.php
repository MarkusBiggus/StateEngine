<?php

use Illuminate\Support\Facades\Route;
use MarkusBiggus\StateEngine\Http\Controllers\StateEngine;
use MarkusBiggus\StateEngine\Http\Controllers\Workflow;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Register web routes for a controller to run tests
|
*/
//Route::get('/state', 'StateEngine'); // reference model
//Route::get('/workflow/resume/{workflow}/{param?}', 'Workflow@resume')->name('workresume');
//Route::get('/workflow/{workflow}/{param?}', 'Workflow')->name('workflow');

Route::group([
    'namespace' => "MarkusBiggus\\StateEngine\Http\\Controllers",
    'as' => 'workflow.',
], function () {
    Route::get('/workflow/{model?}/{testseq?}', StateEngine::class)->name('engine');
});
