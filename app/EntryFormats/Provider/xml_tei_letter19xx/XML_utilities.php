<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 20/03/2018
 * Time: 16:45
 */

namespace App\EntryFormats\Provider\xml_tei_letter19xx;


class XML_utilities
{

    // function to validate and transform the XML file
    function parseXMLFile($xml_file, $xsl_file, $rng_schema) {
        $xml = new DOMDocument();
        $xml->load($xml_file);
        if (isset($rng_schema) && $rng_schema!=="") {
            // validate the xml file against the rng schema
            $valid = $xml->relaxNGValidate($rng_schema);
            if (!$valid) {
                echo $xml_file."\n";
            }
        }
        else $valid = TRUE;

        if ($valid) {

            $xsl = new DOMDocument();
            $xsl->load($xsl_file);

            $proc = new XSLTProcessor();
            $proc->importStyleSheet($xsl);

            // return the xsl output
            return $proc->transformToXML($xml);

        }
        else {
            return false;
        }
    }

// make everything utf8
    function safe_json_encode($value, $options = 0, $depth = 512) {
        $encoded = json_encode($value, $options, $depth);
        if ($encoded === false && $value && json_last_error() == JSON_ERROR_UTF8) {
            $encoded = json_encode(utf8ize($value), $options, $depth);
        }
        return $encoded;
    }

    function utf8ize($d) {
        if (is_array($d)) {
            foreach ($d as $k => $v) {
                $d[$k] = utf8ize($v);
            }
        } else if (is_string ($d)) {
            return mb_convert_encoding($d, "UTF-8", "UTF-8");
        }
        return $d;
    }


// function to normalize the xml attributes so that the json is valid
    function normalizeXMLAttributes($json) {
        $attr_start = '="';
        $attr_end = '"';
        $startPos = 0;
        $endPos = 0;
        while (($startPos = strpos($json, $attr_start, $startPos))!== false) {
            $startPos = $startPos + strlen($attr_start);
            $endPos = strpos($json, $attr_end, $startPos);
            $json = substr_replace($json, "'", $endPos,1);
        }
        $output = str_replace('="',"='",$json);
        // normalize spaces to single space
        $output = preg_replace('!\s+!', ' ', $output);

        return $output;
    }

// function to remove double quotes from json value
    function removeDoubleQuotes($json) {
        $value_start = '": "';
        $value_end = '","';
        $array_object_end = '"},{"';
        $array_end = '"}],"';
        $startPos = 0;
        $endPos = 0;
        while (($startPos = strpos($json, $value_start, $startPos))!== false) {
            $startPos = $startPos + strlen($value_start);

            $endPos = strpos($json, $value_end, $startPos);
            $arrayObjectEndPos = strpos($json, $array_object_end, $startPos);
            if ($arrayObjectEndPos>0 && $arrayObjectEndPos<$endPos) {
                $endPos = $arrayObjectEndPos;
            }
            else {
                $arrayEndPos = strpos($json, $array_end, $startPos);
                if ($arrayEndPos>0 && $arrayEndPos<$endPos) {
                    $endPos = $arrayEndPos;
                }
            }
            $length = $endPos - $startPos;
            if ($length>0) {
                $new_sub_string = substr($json, $startPos, $length);
                $new_sub_string = str_replace('"',"'",$new_sub_string);
                $json = substr_replace($json, $new_sub_string, $startPos,$length);
            }
        }
        // normalize spaces to single space
        $json = preg_replace('!\s+!', ' ', $json);
        return $json;
    }

// function to write the JSON file to the file system
    function writeJSONFile($xml_file_name, $normalized_json) {
        $json_file = fopen("json/".$xml_file_name.".json", "w") or die("Unable to open file!");
        // encode to utf-8
        $utf8_json = mb_convert_encoding($normalized_json, "UTF-8", "auto");
        fwrite($json_file, utf8_encode($utf8_json));
        fclose($json_file);
    }

}