<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/login','ApiIngestionController@accessToken');

Route::group(['middleware' => ['api','auth:api']], function(){

        Route::post('/add/','ApiIngestionController@store');

        Route::get('/logout/','ApiIngestionController@resetAccessToken');

        Route::post('/fileupload',[
                'as' => 'addentry', 'uses' => 'FileEntryController@add']);

    });



Route::get('file', 'FileEntryController@index');

Route::get('file/get/{filename}', [
        'as' => 'getentry', 'uses' => 'FileEntryController@get']);

Route::get('/index/','ApiIngestionController@index');

Route::get('/show/{id}','ApiIngestionController@show');

Route::get('/fullsearch/{sentence}','ApiIngestionController@fullsearch');

Route::get('/search/{expr}','ApiIngestionController@search');

Route::get('/topics/{expr?}','ApiIngestionController@viewtopics');

Route::get('/topicsbyid/{ids}','ApiIngestionController@viewtopicsbyid');

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
