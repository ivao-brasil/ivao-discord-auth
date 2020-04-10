<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'MainController@showIndex')->middleware('auth')->name('home');
Route::get('/ivao/login', 'IVAOController@login')->name('login');
Route::get('/ivao/callback', 'IVAOController@loginCallback');
Route::get('/discord/login','DiscordController@login')->name('auth/discord');
Route::get('/discord/callback', 'DiscordController@loginCallback');

Route::get('/admin', function() {
    return view ('admin');
})->middleware(['auth','admin']);
Route::get('/success',function() {
    return view('success');
});

