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

Route::get('/', 'IndexController@showIndex')->middleware('auth');
Route::get('/login', 'IndexController@login')->name('login');
Route::get('/login/callback', 'IndexController@loginCallback');
Route::get('/auth/discord','AuthController@Discord')->name('auth/discord');
Route::get('/auth/discord/callback', 'AuthController@DiscordCallback');
Route::get('/success',function() {
    return view('success');
});
