<?php

namespace App;

use Laravel\Passport\HasApiTokens; /* Trait  for extending the User model*/
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password','activation_code','status'
    ];

    protected static function boot() {
        static::created(function($user) {
            $user->afterSave($user);
        });

        static::deleting(function($user) {
          $user->roles()->sync([]);
          $user->transcriptions()->sync([]);
          $user->entryLock()->sync([]);
          $user->rights()->sync([]);          
        });
    }

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }


    public function authorizeRoles($roles)
    {
        if (is_array($roles)) {
            return $this->hasAnyRole($roles) ||
                abort(401, 'This action is unauthorized.');
        }
        return $this->hasRole($roles) ||
            abort(401, 'This action is unauthorized.');
    }

    public function hasRole($role)
    {
        return null !== $this->roles()->where("name", $role)->first();
    }

    public function hasAnyRole(){

        $roles = Role::all();

        foreach ($roles as $role) {

            $verified = $this->hasRole($role);

            if($verified) return true;
        }
        return false;
    }

    public function afterSave($user){
            //
    }

    public function transcriptions()
    {
        return $this->belongsToMany('App\Entry', 'user_transcriptions', 'user_id', 'entry_id')->withTimestamps();
    }

    public function entryLock()
    {
        return $this->belongsToMany('App\Entry','entry_locks','entry_id','user_id','locked_at')->withTimestamps();
    }

    public function rights()
    {
        return $this->belongsToMany('App\Rights', 'user_rights', 'user_id', 'rights_id')->withTimestamps();
    }

    public function isAdmin() {
      $isAdmin = false;
      $roles = $this->roles;
      foreach($roles as $role) {
        if ($role['is_admin']===1) {
          $isAdmin = true;
        }
      }
      return $isAdmin;
    }

}
