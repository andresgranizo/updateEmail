<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\RegistrationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [RegistrationController::class, 'create'])->name('registration.create');
Route::post('/consultar-cedula', [RegistrationController::class, 'consultarCedula'])->name('registration.consultarCedula');
Route::post('/guardar-registro', [RegistrationController::class, 'store'])
     ->name('registration.store');
