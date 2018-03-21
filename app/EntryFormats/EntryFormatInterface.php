<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 18/01/2018
 * Time: 08:12
 */

namespace  App\EntryFormats;

interface EntryFormatInterface {

    public function getJsonData();
    public function validateText($text,$parent);
    public function getConstrainedArrayFields($parent);
    public function validateArray($array,$parent);
    public function saveCollateralEntities($entry);
    public function valid($entry);
}