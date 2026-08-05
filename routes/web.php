<?php

use App\Http\Controllers\GenerateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Generator');
});

Route::post('/generate', GenerateController::class)->name('generate');
