<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class Administrator
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
      $user = Auth::user();
      if ($user->isAdmin()) {
        return $next($request);
      }
      $error = array(
        "status"=>"error",
        "message"=>"Unauthorized request. You do not have the necessary user rights to access this page."
      );
      return response($error, 401);
    }
}
