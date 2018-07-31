<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Entry;
use App\Uploadedfile;
use App\Role;
use App\User;
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
    $user = Auth::user();
    if (!$user->isAdmin()) {
      $entry = Entry::where([
        ['id', $id],
        ['user_id','=',Auth::user()->id],
      ])->first();

      $msg = "Entry found";
      $status = true;
    }
    else {
      $entry = Entry::where('id',$id)->first();

      $msg = "Entry found";
      $status = true;
    }
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
      $user->roles()->sync([]);
      $user->transcriptions()->sync([]);
      $user->entryLock()->sync([]);
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

  public function listUsers(Request $request) {
    $term = "";
    $type = "Name";
    $sort = "asc";
    $paginate = 10;
    $status = null;
    $group = 0;
    if ($request->input('sort')!=="") {
      $sort = $request->input('sort');
    }
    if ($request->input('paginate')!=="") {
      $paginate = $request->input('paginate');
    }
    if ($request->input('status')!=="") {
      $status = $request->input('status');
    }
    if ($request->input('term')!=="") {
      $term = strtolower($request->input('term'));
    }
    if ($request->input('type')!=="") {
      $type = $request->input('type');
    }
    if ($request->input('group')!=="") {
      $group = $request->input('group');
    }
    if (strlen($term)<2) {
      if ($group==0) {
        $users = User::orderBy('name', $sort)->paginate($paginate);
      }
      else if ($group>0) {
        $role_users = array();
        $role = Role::find($group);
        foreach($role->users as $role_user) {
          $role_users[]=$role_user->id;
        }
        $users = User::whereIn('id', $role_users)->orderBy('name', $sort)->paginate($paginate);
      }
    }
    else {
      if ($group==0) {
        if ($type==="Name") {
          $users = User::where('name','like','%'.$term.'%')->orderBy('name', $sort)->paginate($paginate);
        }
        else if ($type==="Email") {
          $users = User::where('email','like','%'.$term.'%')->orderBy('name', $sort)->paginate($paginate);
        }
      }
      else {
        $role_users = array();
        $role = Role::find($group);
        foreach($role->users as $role_user) {
          $role_users[]=$role_user->id;
        }
        if ($type==="Name") {
          $users = User::where('name','like','%'.$term.'%')->whereIn('id', $role_users)->orderBy('name', $sort)->paginate($paginate);
        }
        else if ($type==="Email") {
          $users = User::where('email','like','%'.$term.'%')->whereIn('id', $role_users)->orderBy('name', $sort)->paginate($paginate);
        }
      }
    }
    return $this->prepareResult(true, $users, [], "All users");
  }

  public function getUser(Request $request, $id) {
    if (User::where('id',$id)->first()!==null) {
      $user = User::where('id',$id)->first();
      $user['roles'] = User::where('id',$id)->first()->roles;
      return $this->prepareResult(true, $user, [], []);
    }
    else {
      return $this->prepareResult(false, [], "There is no user that matches this id.", []);
    }
  }

  public function updateUser(Request $request, $id) {
    $email = "";
    $name = "";
    $status = 0;
    $role = 0;

    if ($request->input('email')!=='') {
      $email = trim($request->input('email'));
    }
    if ($request->input('name')!=='') {
      $name = trim($request->input('name'));
    }
    if ($request->input('status')!=='') {
      $status = intval($request->input('status'));
    }
    if ($request->input('role')!=='') {
      $role = intval($request->input('role'));
    }
    if ($email==="") {
      return $this->prepareResult(false, [], "Please provide a valid email address", []);
    }
    if ($name==="") {
      return $this->prepareResult(false, [], "Please provide a user name", []);
    }
    if (intval($id)===0) {
      $user = new User();
      $user->email = $email;
      $user->name = $name;
      $user->status = $status;
      $user->save();
      $user->roles()->sync($role);

      return $this->prepareResult(true, $user, [], "User created");
    }
    else if (intval($id)>0){
      // check if auth user has the right to update this user
      $auth_user = Auth::user();
      $isAdmin = $auth_user->isAdmin();
      if (intval($auth_user->id)!==intval($id)){
        if (!$isAdmin) {
           return $this->prepareResult(false, [], "You do not have permissions to update this user account", []);
        }
      }
      if (User::where('id',$id)->first()!==null) {
        $user = User::where('id',$id)->first();
        $user->email = $email;
        $user->name = $name;
        $user->status = $status;
        $user->save();
        // update role
        $user->roles()->sync($role);
        return $this->prepareResult(true, $user, [], "User updated successfully");
      }
    }
  }

  public function deleteUser(Request $request, $id) {
    if (intval($id)>0) {
      $auth_user = Auth::user();
      $isAdmin = $auth_user->isAdmin();
      if (intval($auth_user->id)!==intval($id)){
        if (!$isAdmin) {
           return $this->prepareResult(false, [], "You do not have permissions to delete this user account", []);
        }
      }
      $user = User::find($id);
      if ($user!==null) {
        if (!$user->isAdmin()) {
          $user->delete();
          return $this->prepareResult(true, [], [], "User deleted successfully");
        }
        else {
          return $this->prepareResult(false, [], "You cannot delete an administrator. To delete this user you must first remove the administrator role.", "");
        }
      }
      else {
        return $this->prepareResult(false, [], "A user with id: ".$id." cannot be found.", "");
      }
    }
  }

  public function getUserRoles(Request $request, $id) {
    if (User::where('id',$id)->first()!==null) {
      $userRoles = User::where('id',$id)->first()->roles;
      return $this->prepareResult(true, $userRoles, [], []);
    }
    else {
      return $this->prepareResult(false, [], "There is no user that matches this id.", []);
    }
  }

  public function loadAvailableUserRolesAdmin(Request $request) {
    $availableRoles = Role::orderBy('default','asc')->get();
    return $this->prepareResult(true, $availableRoles, [], "All available user roles");
  }

  public function loadAvailableUserRoleAdmin(Request $request, $id) {
    $availableRole = Role::find($id);
    return $this->prepareResult(true, $availableRole, [], "All available user roles");
  }

  public function updateAvailableUserRoleAdmin(Request $request, $id) {
    if (intval($id)>0) {
      $availableRole = Role::find($id);
      if ($availableRole!==null) {
        $name = '';
        $description = '';
        $default = 0;

        if ($request->input('name')!=='') {
          $name = trim($request->input('name'));
        }
        if ($request->input('description')!=='') {
          $description = trim($request->input('description'));
        }
        if ($request->input('default')!=='') {
          $default = intval($request->input('default'));
        }

        if ($default>0) {
          Role::where('default','=',1)->update(['default'=>0]);
        }
        $availableRole->name = $name;
        $availableRole->description = $description;
        $availableRole->default = intval($default);
        $availableRole->save();
        return $this->prepareResult(true, $availableRole, [], "User group updated successfully");
      }
      else {
        return $this->prepareResult(false, [], "No user group matches the provided id.", "");
      }
    }
    else if (intval($id)===0) {
      $name = '';
      $description = '';
      $default = 0;

      if ($request->input('name')!=='') {
        $name = trim($request->input('name'));
      }
      if ($request->input('description')!=='') {
        $description = trim($request->input('description'));
      }
      if ($request->input('default')!=='') {
        $default = intval($request->input('default'));
      }

      if ($default>0) {
        Role::where('default','=',1)->update(['default'=>0]);
      }
      $availableRole = new Role();

      $availableRole->name = $name;
      $availableRole->description = $description;
      $availableRole->default = intval($default);
      $availableRole->save();
      return $this->prepareResult(true, $availableRole, [], "User group created successfully");

    }

  }

  public function deleteAvailableUserRoleAdmin(Request $request, $id) {
    if (intval($id)>0) {
      $availableRole = Role::find($id);
      if ($availableRole!==null) {
        if (count($availableRole->users)===0) {
          $availableRole->delete();
          return $this->prepareResult(true, $availableRole, [], "User role deleted successfully");
        }
        else {
          return $this->prepareResult(false, $availableRole, "This user role is associated with user accounts and cannot be deleted.", []);
        }

      }
      else {
        return $this->prepareResult(false, [], "No user group matches the provided id.", "");
      }
    }
  }
}
