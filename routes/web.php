<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dang-nhap'));

Route::get('/dang-nhap', fn () => view('dang-nhap'));
Route::get('/pos', fn () => view('pos'));
Route::get('/bep', fn () => view('bep'));
