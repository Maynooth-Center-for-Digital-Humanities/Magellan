<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Entry;
use App\Helpers\PrepareOutputTrait;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class UserController extends Controller
{
  use PrepareOutputTrait;

  /**
   * Display the auth user details.
   *
   * @param  \Illuminate\Http\Request $request
   * @return Auth::User
   */

  public function userProfile() {
    $user = Auth::user();
    // letters count
    $num_letters = Entry::where([
      ['current_version', '=', 1],
      ['user_id','=',$user->id],
    ])->count();

    // transcriptions count
    $num_transcriptions = $user->transcriptions->count();
    $user['num_letters'] = $num_letters;
    $user['num_transcriptions'] = $num_transcriptions;
    $user['roles'] = $user->roles;
    return $this->prepareResult(true, $user, [], "User details");
  }

  public function userUpdate(Request $request) {
    $user = Auth::user();
    $user['name'] = $request->input('name');
    $user['email'] = $request->input('email');
    $user->save();
    return $this->prepareResult(true, $user, [], "User updated successfully");
  }

  public function userUpdatePassword(Request $request) {
    $user = Auth::user();
    $validatedData = $request->validate([
      'password' => 'required|string|min:6|confirmed',
    ]);
    try {
        $user['password'] = bcrypt(array_get($validatedData, 'password'));
        $user->save();
    } catch (\Exception $exception) {
        return $this->prepareResult(false, [], 'Unable to update user password.', $exception);
    }
    return $this->prepareResult(true, $user, [], "User password updated successfully");
  }

  public function userLetters(Request $request) {
    if (Entry::first() != null) {
      $sort = "asc";
      $paginate = 10;
      if ($request->input('sort') !== "") {
          $sort = $request->input('sort');
      }
      if ($request->input('paginate') !== "") {
          $paginate = $request->input('paginate');
      }
      $coll = Entry::where([
        ['current_version', '=', 1],
        ['status','=',0],
        ['transcription_status','<',2],
        ['user_id','=',Auth::user()->id],
      ])->orderBy('created_at', $sort)->paginate($paginate);
    } else {
      $coll = "empty bottle";
    };

    return $this->prepareResult(true, $coll, [], "All user entries");
  }

  public function userLetter(Request $request, $id) {
    $entry = Entry::where([
      ['id', $id],
      ['user_id','=',Auth::user()->id],
    ])->first();

    $msg = "Entry found";
    $status = true;

    if ($entry != null) {
      $coll = json_decode($entry->element, true);
      $coll['notes'] = $entry->notes;
      $file_id = $entry->uploadedfile_id;
      if ($file_id!==null) {
        $file = Uploadedfile::select('id', 'filename', 'original_filename')
            ->where('id',$file_id)->get();
        $link = asset('/').'download-xml/'.$file_id;
        $newFile = $file[0];
        $newFile['link'] = $link;
        $coll["file"] = $newFile;
      }

    } else {
      $coll = "";
      $msg = "Entry not found";
      $status = false;

      abort(404, json_encode($this->prepareResult($status, $coll, [], $msg, "Request failed")));
    };

    return $this->prepareResult($status, $coll, [], $msg, "Request complete");
  }

  public function userTranscriptions(Request $request) {
    $paginate = 10;
    $page = 1;
    if ($request->input('paginate') !== null && $request->input('paginate') !== "") {
        $paginate = $request->input('paginate');
    }
    if ($request->input('page') !== null && $request->input('page') !== "") {
        $page = $request->input('page');
    }
    $transcriptions_data = Auth::user()->transcriptions;
    $count = count($transcriptions_data);

    $paginator = new Paginator($transcriptions_data, $count, $paginate, $page, [
      'path'  => Paginator::resolveCurrentPath()
    ]);
    return $this->prepareResult(true, $paginator, [], "All user transcriptions");
  }

  public function userForget() {
    $user = Auth::user();
    $response = [];

    if (!$user->isAdmin()) {
      $user['name'] = "Anonymous user";
      $user['email'] = "";
      $user['password'] = "";
      $user->save();
      $user->transcriptions()->sync([]);
      $user->rights()->sync([]);
      $response = array(
        "status"=>"success"
      );
      return $this->prepareResult(true, $response, [], "User forgotten succesfully");
    }
    else {
      $response = array(
        "status"=>"error"
      );
      return $this->prepareResult(true, $response, [], "This user is an administrator. Administrator status must be removed before the user can be forgotten.");
    }
  }

  public function subscribeToMailchimp(Request $request) {
    $email = $request->input('email');
    $url = "https://us8.api.mailchimp.com/3.0/lists/90977771c7/members/".md5($email);

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "PUT",
      CURLOPT_POSTFIELDS => "{\"email_address\":\"".$email."\", \"status\": \"subscribed\", \"status_if_new\": \"subscribed\"}",
      CURLOPT_HTTPHEADER => array(
        "Authorization: Basic bGV0dGVyczE5MTYyMzpiYWYzOWQ2NWZkM2FjNmUyZmZjNDg0YjIwZWVmYWIyOS11czg=",
        "Cache-Control: no-cache",
        "Content-Type: application/json",
      ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      return $this->prepareResult(true, array("status"=>"error", "email"=>$email), [], $err);
    } else {
      return $this->prepareResult(true, array("status"=>"success", "email"=>$email), [], json_decode($response));
    }

  }
}
