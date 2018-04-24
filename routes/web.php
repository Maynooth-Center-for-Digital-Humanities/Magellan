<?php

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


/*Route::get('/ui/', 'public/ui/index.html');
Route::get('/react/', function () {
    return View::make("react.index");
});*/

Route::get('/download-xml/{filename}', 'FileEntryController@downloadXML');

Route::post('/importer', function () {
    echo '<h1>Hello</h1>';
});
