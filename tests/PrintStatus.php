<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 20/01/2018
 * Time: 07:18
 */

namespace Tests;

trait PrintStatus
{
    public function printThis($cont,$label = '')
    {
        if(empty($label)) {
            echo "CAZZO". json_encode($cont, false);
        }else{
            echo "CAZZONE";
            //sprintf("Test Debug: %s", json_decode($cont,true)[$label]);
        }

    }

    public function anotherMethod()
    {
        echo "Trait – anotherMethod() executed";
    }
}