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

    private function inputArraytoString($inputArray) {
      if (is_array($inputArray) && count($inputArray)>0) {
        $new_string = "";
        $i=0;
        foreach($inputArray as $chunk) {
          $new_chunk = "";
          if ($i>0) {
            $new_chunk .= ",";
          }
          $new_chunk .= '"'.$chunk.'"';
          $new_string .= $new_chunk;
          $i++;
        }
        return $new_string;
      }
      else return "'".$inputArray."'";
    }

    private function returnIdsArray($ids) {
      if (count($ids)>0) {
        $new_ids = array();
        foreach($ids as $idrow) {
          if (count($idrow)>0) {
            foreach($idrow as $key=>$val) {
              $new_ids[] = $val;
            }
          }
        }
        return $new_ids;
      }
      else return false;
    }

    public function getEntryStatus($pages) {
      $status = 0;
      $page_count = count($pages);
      $expected_total = $page_count*2;
      $transcriptions_total = 0;
      foreach($pages as $page) {
        $transcription_status=0;
        if (isset($page['transcription_status'])) {
          $transcription_status = $page['transcription_status'];
        }
        $transcriptions_total += intval($transcription_status);
      }
      if ($expected_total===$transcriptions_total) {
        $status = 1;
      }
      return $status;
    }

}
