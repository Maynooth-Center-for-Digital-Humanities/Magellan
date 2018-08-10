<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 18/01/2018
 * Time: 09:02
 */

namespace App\EntryFormats\Provider\xml_tei_letter19xx;

use Illuminate\Validation\Rule as Rule;
use App\EntryFormats\EntryFormatInterface as EntryFormatInterface;

use App\EntryFormats\Helpers\SaveOnDatabaseTrait as SaveOnDatabaseTrait;

use App\EntryFormats\Provider\xml_tei_letter19xx\XML_utilities as XML;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class EntryFormat implements EntryFormatInterface
{
    use SaveOnDatabaseTrait;

    protected $xml_dir = "xml";
    // path to the xsl file that produces the JSON
    //protected $xsl_file = "EntryFormats/Provider/xml_tei_letter19xx/xsl/main-style.xsl";

    protected $xsl_file = "main-style.xsl";

    // path to the rng schema to validate the XML file against
    protected $rng_schema = "template.rng";
    // set errors to custom file
    protected $error_log = "errors/error-log.log";

    protected $json_output = Array("debug"=>"element not processed");

    protected $spec = [
        'api_version' => 'required|string|max:255',
        'collection' => 'required|string|max:255',
        'collection_id' => 'required|integer|max:255',
        'copyright_statement' => 'required|string|max:1500',
        'creator' => 'nullable|string|max:255',
        'creator_gender' => array('Female','Male'),
        'creator_location' => 'string|max:255',
        'date_created' => 'string|max:255',
        'description' => 'nullable|string|max:2500',
        'doc_collection' => 'string|max:255',
        'language' => 'required|alpha|max:255',
        'document_id' => 'required|integer',
        'modified_timestamp' => 'date|required|date_format:Y-m-d\TH:i:sP',
        'number_pages'=>'required|integer',
        'title' => 'required|string|max:500',
        'pages.*.archive_filename'=>'required|max:255',
        'pages.*.contributor'=>'nullable|string|max:255',
        'pages.*.last_rev_timestamp'=>'date|required|date_format:Y-m-d\TH:i:sP',
        'pages.*.original_filename'=>'required|max:255',
        'pages.*.page_count'=>'required|integer',
        'pages.*.page_id'=>'required|integer',
        'pages.*.page_type'=>'nullable|string|max:50',
        'pages.*.rev_id'=>'required|integer',
        'pages.*.rev_name'=>'required|max:255',
        'pages.*.transcription'=>'string',
        'pages.*.transcription_status'=>'required|integer',
        'recipient'=>'nullable|max:255',
        'recipient_location'=>'max:255',
        'request_time'=>'date|required|date_format:Y-m-d\TH:i:sP',
        'source'=>'required|max:255',
        'status'=>'required|integer',
        'terms_of_use'=>'required|max:1',
        'time_zone'=>'required|max:255',
        'topics.*.topic_id'=>'required|integer',
        'topics.*.topic_name'=>'required|max:255',
        'transcription_status'=>'required|integer',
        'type'=>'required|max:255',
        'user_id'=>'required|max:15',
        'year_of_death_of_author'=>'max:4'
    ];

    private function getValidatorSpec(){

        return array_map(
            function($elem) {return (!is_array($elem)?$elem:Rule::in($elem));},
            $this->spec
        );
    }
    public function getJsonData($parent = ''){

        return json_encode($this->json_output);
    }
    public function validateText($text,$parent)
    {
        return null;
    }
    public function getConstrainedArrayFields($parent){
        return null;
    }
    public function validateArray($array,$parent){
        return null;
    }

    public function valid($entry){
        $file = "app/".$entry->getFilename().'.'.$entry->getClientOriginalExtension();

        $XML = new XML_utilities();

        if($entry->getClientMimeType()=="application/xml" || $entry->getClientMimeType()=="text/xml"){

            $xml_json = $XML->parseXMLFile(storage_path($file), $this->xsl_file, $this->rng_schema);
            if ($xml_json!==false) {
                $normalized_json = $XML->normalizeXMLAttributes($xml_json);
                $normalized_json = $XML->removeDoubleQuotes($normalized_json);
                $this->json_output = json_decode($normalized_json,true);
                return \Validator::make($this->json_output, $this->getValidatorSpec());
            }

        };
        return false;
    }


}
