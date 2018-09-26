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

Route::post('/login','Auth\LoginController@accessToken');

Route::post('/register/','Auth\RegisterController@register');

Route::get('/verify-user/{code}', 'Auth\RegisterController@activateUser')->name('activate.user');

Route::get('/rights', 'ApiIngestionController@rights');

Route::get('file', 'FileEntryController@index');

Route::get('file/get/{filename}', [
        'as' => 'getentry', 'uses' => 'FileEntryController@get']);

Route::get('/index/','ApiIngestionController@index');

Route::get('/show/{id}','ApiIngestionController@show');

Route::get('/show-letter/{id}','ApiIngestionController@showLetter');

Route::get('/fullsearch/{sentence}','ApiIngestionController@fullsearch');

Route::get('/search/{expr}','ApiIngestionController@search');

Route::get('/topics/{expr?}','ApiIngestionController@viewtopics');

Route::get('/topicsbyid/{ids}','ApiIngestionController@viewtopicsbyid');

Route::get('/indexfiltered/','ApiIngestionController@indexfiltered');
Route::get('/transcriptionsdeskfiltered/','ApiIngestionController@transcriptionsDeskfiltered');

Route::get('/indexfilteredfilters/','ApiIngestionController@indexfilteredFilters');

Route::get('/unauthorised', 'AdminController@Unauthorized');

// metadata elements
Route::get('/sources/','ApiIngestionController@sources');
Route::get('/authors/','ApiIngestionController@authors');
Route::get('/recipients/','ApiIngestionController@recipients');
Route::get('/people/','ApiIngestionController@people');
Route::get('/genders/','ApiIngestionController@genders');
Route::get('/languages/','ApiIngestionController@languages');
Route::get('/date_created/','ApiIngestionController@date_created');

// test API calls
Route::get('/test-api/','ApiIngestionController@testAPI');

// mailchimp
Route::post('/subscribe-to-newsletter/','UserController@subscribeToMailchimp');

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// registered users
Route::group(['middleware' => ['api','auth:api']], function(){

      Route::post('/add/','ApiIngestionController@store');

      Route::post('/missed-files-patch/','ApiIngestionController@missedFilesPatch');

      Route::get('/logout/','Auth\LoginController@resetAccessToken');

      Route::post('/fileupload',[
              'as' => 'addentry', 'uses' => 'FileEntryController@add']);

      Route::get('/indexall/','ApiIngestionController@indexAll');

      Route::post('/upload-letter/{id}','ApiIngestionController@uploadLetter');

      Route::post('/update-letter-pages-order/{id}','ApiIngestionController@updatePagesOrder');

      Route::post('/update-letter-transcription-page/{id}','ApiIngestionController@updateTranscriptionPage');

      Route::delete('/delete-letter-page','ApiIngestionController@deleteLetterPage');

      Route::delete('/delete-letter','ApiIngestionController@deleteLetter');

      Route::delete('/remove-transcription-association','ApiIngestionController@removeTranscriptionAssociation');

      Route::get('/letter-transcribe/{id}','ApiIngestionController@letterTranscribe');


      // user
      Route::get('/user-profile','UserController@userProfile');

      Route::post('/update-user','UserController@userUpdate');

      Route::post('/update-user-password','UserController@userUpdatePassword');

      Route::get('/forget-me','UserController@userForget');

      Route::get('/user-letters','UserController@userLetters');

      Route::get('/user-transcriptions','UserController@userTranscriptions');

      Route::get('/user-letter/{id}','UserController@userLetter');

  });

// admin
Route::group(['middleware' => ['admin','auth:api']], function(){

  Route::get('/admin/transcriptions-list','AdminController@listTranscriptions');
  Route::get('/admin/search/{sentence}','AdminController@adminsearch');
  Route::get('/admin/advanced-search/','AdminController@adminAdvancedSearch');
  Route::get('/admin/list','AdminController@list');
  Route::get('/admin/user-letter/{id}','AdminController@adminUserLetter');

  Route::post('/update-letter-transcription-status/{id}','AdminController@updateTranscriptionStatus');
  Route::post('/update-letter-transcription-page-status/{id}','AdminController@updateTranscriptionPageStatus');
  Route::post('/admin/update-letter-transcription-page/{id}','AdminController@updateTranscriptionPage');

  Route::post('/admin/update-entry/{id}','AdminController@updateEntry');
  Route::post('/admin/upload-entry-page/{id}','AdminController@uploadEntryPage');
  Route::post('/admin/update-entry-status/{id}','AdminController@updateEntryStatus');

  // users
  Route::get('/admin/users/','UserController@listUsers');
  Route::get('/admin/user/{id}','UserController@getUser');
  Route::get('/admin/user-roles/{id}','UserController@getUserRoles');
  Route::get('/admin/available-user-roles/','UserController@loadAvailableUserRolesAdmin');
  Route::get('/admin/available-user-role/{id}','UserController@loadAvailableUserRoleAdmin');

  Route::post('/admin/user/{id}','UserController@updateUser');
  Route::post('/admin/available-user-role/{id}','UserController@updateAvailableUserRoleAdmin');

  Route::delete('/admin/user/{id}','UserController@deleteUser');
  Route::delete('/admin/available-user-role/{id}','UserController@deleteAvailableUserRoleAdmin');

});
