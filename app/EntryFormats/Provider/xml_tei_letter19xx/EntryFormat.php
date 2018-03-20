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

use App\EntryFormats\Provider\xml_tei_letter19xx\XML_utilities;

class EntryFormat implements EntryFormatInterface
{
    use SaveOnDatabaseTrait;

    protected $xml_dir = "xml";
    // path to the xsl file that produces the JSON
    protected $xsl_file = "xsl/main-style.xsl";
    // path to the rng schema to validate the XML file against
    protected $rng_schema = "schema/template.rng";
    // set errors to custom file
    protected $error_log = "errors/error-log.log";

    protected $json_output = null;

    protected $spec = [
        'api_version' => 'required|string|max:255',
        'doc_id' => 'required|alpha|max:255',
        'type'=>'required|max:255',
    ];

    private function getValidatorSpec(){

        return array_map(
            function($elem) {return (!is_array($elem)?$elem:Rule::in($elem));},
            $this->spec
        );
    }
    public function getJsonData($parent){

        dd($this->json_output);
        return '{"type":"xml_tei_letter19xx","title":"","description":"","topics":[],"pages":[]}';
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

        $file = $entry->getPath()."/".$entry->filename;

        $XML = new XML_utilities();

        if($entry->getClientMimeType()=="application/xml"){

            $xml_json = $XML->parseXMLFile($file, $this->xsl_file, $this->rng_schema);

            if ($xml_json!==false) {
                $normalized_json = $XML->normalizeXMLAttributes($xml_json);
                $normalized_json = $XML->removeDoubleQuotes($normalized_json);
                $this->json_output = $normalized_json;
                return true;
            }

        };

        return false;
    }


}
