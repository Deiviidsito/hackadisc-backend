<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/importar-json', [ImportController::class, 'importarVentasJson']);
