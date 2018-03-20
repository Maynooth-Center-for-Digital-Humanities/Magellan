<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 20/03/2018
 * Time: 11:14
 */

namespace App\Helpers;

trait PrepareOutputTrait
{

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */

    private function prepareResult($status, $data, $errors, $msg)

    {
        return ['status' => $status, 'data' => $data, 'message' => $msg, 'errors' => $errors];

    }

}