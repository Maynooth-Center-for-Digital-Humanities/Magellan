<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 20/03/2018
 * Time: 16:45
 */

namespace app\EntryFormats\Provider\xml_tei_letter19xx;




class XML_utilities
{
    use \Illuminate\Console\DetectsApplicationNamespace;

    // function to validate and transform the XML file
    function parseXMLFile($xml_file, $xsl, $rng_schema) {

        $xml = new \DOMDocument();
        $xml->load($xml_file);

        $class = new \ReflectionClass($this);
        $namespace = str_replace("\\","/",$class->getNamespaceName());

        $rng_file  = base_path()."/".$namespace."/schema/".$rng_schema;
        $xsl_file = base_path()."/".$namespace."/xsl/".$xsl;

        if (file_exists($rng_file)){
            // validate the xml file against the rng schema
            $valid = $xml->relaxNGValidate($rng_file);
            if (!$valid) {
                return false;
            }
        }
        else $valid = TRUE;

        if ($valid) {
            $newXML = new \DOMDocument();
            $newXML->loadXML(trim(XML_utilities::fixPageBreaks($xml_file)));

            $xsl = new \DOMDocument();
            $xsl->load($xsl_file);

            $proc = new \XSLTProcessor();
            $proc->importStyleSheet($xsl);

            // return the xsl output
            return $proc->transformToXML($newXML);

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

    function writeToFile($xml_file, $new_content) {
    	$new_file = fopen($xml_file, "w") or die("Unable to open file!");
    	$utf8_content = mb_convert_encoding($new_content, "UTF-8", "auto");
    	fwrite($new_file,$utf8_content);
    	fclose($new_file);
    }

    function fixPageBreaks($filepath) {
    	$record = new \DOMDocument();
    	$record->preserveWhiteSpace = true;
    	$record->load($filepath);
    	$record->formatOutput = true;

    	$xpath = new \DOMXPath($record);
    	$ns = $record->documentElement->namespaceURI;
    	if($ns) {
    	  $xpath->registerNamespace("ns", $ns);
    	}

    	$newDoc = new \DOMDocument('1.0','utf-8');
    	$newDoc->resolveExternals = false;
    	$newDoc->substituteEntities = false;
    	$newXpath = new \DOMXPath($newDoc);

    	$textNodes = $record->createElementNS($ns,'text');
    	$textGroup = $record->createElementNS($ns,'group');

    	$nodes = $xpath->query("//ns:TEI/ns:text");
    	foreach ($nodes as $node) {

    		$pbs = $node->getElementsByTagName('pb');
    		foreach($pbs as $pb) {
    			$newDoc->appendChild($newDoc->importNode($pb));
    			$pb->removeAttribute('facs');
    			$pb->removeAttribute('n');
    			$pb_html = $pb->ownerDocument->saveXML($pb);
    		}
    		$node_content = $node->ownerDocument->saveXML($node);

    		$node_content = XML_utilities::removePBFromComments($node_content);

    		$pb_chunks = explode('<pb/>', $node_content);

    		$original_pbs = $newXpath->query("//*");

    		$i=0;
    		$prevTextNode = new \stdClass();
    		foreach($pb_chunks as $pb_chunk) {
    			// clear php warnings 1
    			libxml_use_internal_errors(true);

    			$new_pb_chunk = XML_utilities::checkForEndTags($pb_chunk);

    			$new_pb_chunk = XML_utilities::escapeHeadTags($new_pb_chunk);

    			$new_pb_chunk = XML_utilities::addWrappingTags($new_pb_chunk);


    			$newDoc->loadHTML("<html>".$new_pb_chunk."</html>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    			// clear php warnings 2
    			libxml_clear_errors();

    			$savedXMLnode = $newDoc->saveXML($newDoc->childNodes->item(0));

    			$newDoc->loadXML($savedXMLnode);

    			$text_node = $newDoc->childNodes->item(0);

    			$secondLevelName = "";

    			foreach	($text_node->childNodes as $firstLevel) {
    				if ($firstLevel->childNodes!==null) {
    					foreach	($firstLevel->childNodes as $secondLevel) {
    						$secondLevelName = $secondLevel->nodeName;
    					}
    				}
    			}
    			if ($text_node->childNodes->length>0 && $secondLevelName !=="group") {

    				$original_pb = $original_pbs[$i];
    				if (isset($original_pb)) {
    					$textGroup->appendChild($record->importNode($original_pb,true));
    				}

    				if ($text_node->childNodes->item(0)->nodeName!=="text") {
    					//echo "case 1\n";

    					$newTextNode = $newDoc->createElement('text');
    					$typeAttr = "";
    					if (isset($text_node->firstChild->attributes) && $text_node->firstChild->attributes->length>0 && $text_node->childNodes->item(0)->nodeName!=="div") {
    						foreach($text_node->firstChild->attributes as $attr) {
    							$name = $attr->nodeName;
    							$value = $attr->nodeValue;
    							$newAttr = $newDoc->createAttribute($name);
    							$newAttr->value=$value;

    							$newTextNode->appendChild($newAttr);
    							if ($name==="type") {
    								$typeAttr = $value;
    							}
    						}
    					}
    					else {
    						if (isset($prevTextNode->attributes)) {
    							foreach($prevTextNode->attributes as $attr) {
    								$name = $attr->nodeName;
    								$value = $attr->nodeValue;
    								$newAttr = $newDoc->createAttribute($name);
    								$newAttr->value=$value;

    								$newTextNode->appendChild($newAttr);
    								if ($name==="type") {
    									$typeAttr = $value;
    								}
    							}
    						}
    					}
    					if ($text_node->firstChild->nodeName!=="body") {
    						//echo "\ncase 1.1\n";
    						$newTextBodyNode = $newDoc->createElement('body');
    						//echo "\n154 ".$text_node->firstChild->nodeName."\n";
    						if ($text_node->firstChild->nodeName==="note" || $text_node->firstChild->nodeName==="closer" || $text_node->firstChild->nodeName==="postscript" || $text_node->firstChild->nodeName==="#comment" || ($text_node->firstChild->nodeName!=="div" && $text_node->firstChild->nodeName!=="cb" && $text_node->firstChild->nodeName!=="pb" && trim($text_node->firstChild->nodeValue)==="")) {
    							//echo "\ncase 1.1.1\n";
    							if ($typeAttr==="" || $typeAttr!=="postcard") {
    								$emptyParagraph = $newDoc->createElement('p');
    							}
    							else {
    								$emptyParagraph = $newDoc->createElement('cb');
    							}
    							$newTextBodyNode->appendChild($emptyParagraph);
    						}
    						for($k=0; $k<$text_node->childNodes->length; $k++) {
    							 $childNode = $text_node->childNodes->item($k);
    							$newTextBodyNode->appendChild($childNode);
    						}
    						$newTextNode->appendChild($newTextBodyNode);
    					}
    					else {
    						foreach($text_node->childNodes as $childNode) {
    							$newTextNode->appendChild($childNode);
    						}
    					}
    				}
    				else if ($text_node->nodeName==="text") {
    					//echo "case 2\n";
    					$newTextNode = $text_node->childNodes->item(0);
    					if (isset($text_node->firstChild->attributes) && $text_node->firstChild->attributes->length>0 ) {
    						foreach($text_node->firstChild->attributes as $attr) {
    							$name = $attr->nodeName;
    							$value = $attr->nodeValue;
    							$newAttr = $newDoc->createAttribute($name);
    							$newAttr->value=$value;

    							$newTextNode->appendChild($newAttr);
    						}
    					}
    					else {
    						if (isset($prevTextNode->attributes)) {
    							foreach($prevTextNode->attributes as $attr) {
    								$name = $attr->nodeName;
    								$value = $attr->nodeValue;
    								$newAttr = $newDoc->createAttribute($name);
    								$newAttr->value=$value;

    								$newTextNode->appendChild($newAttr);
    							}
    						}
    					}
    				}
    				else {
    					//echo "case 3\n";
    					$newTextNode = $text_node->childNodes->item(0);
    					if (isset($text_node->firstChild->attributes) && $text_node->firstChild->attributes->length>0 ) {
    						foreach($text_node->firstChild->attributes as $attr) {
    							$name = $attr->nodeName;
    							$value = $attr->nodeValue;
    							$newAttr = $newDoc->createAttribute($name);
    							$newAttr->value=$value;

    							$newTextNode->appendChild($newAttr);
    						}
    					}
    					else {
    						if (isset($prevTextNode->attributes)) {
    							foreach($prevTextNode->attributes as $attr) {
    								$name = $attr->nodeName;
    								$value = $attr->nodeValue;
    								$newAttr = $newDoc->createAttribute($name);
    								$newAttr->value=$value;

    								$newTextNode->appendChild($newAttr);
    							}
    						}
    					}
    				}
    				$textGroup->appendChild($record->importNode($newTextNode, true));
    				$prevTextNode = $newTextNode;
    				$i++;
    			}


    		}
    		$textNodes->appendChild($textGroup);
    		$node->replaceChild($textGroup, $node->getElementsByTagName('group')[0]);
    	}

    	$newXML = $record->saveXML();
    	$newXML = str_replace("addrline", "addrLine", $newXML);
    	$newXML = str_replace("<html>", "", $newXML);
    	$newXML = str_replace("</html>", "", $newXML);
    	$newXML = XML_utilities::restoreHeadTags($newXML);

    	return $newXML;
    }

    function checkForEndTags($str) {
    	$str = trim($str);
    	if (strlen($str)>0) {
    		$first_character = $str[0];
    		$first_three_characters = substr($str, 0, 3);

    		if ($first_character!=="<" || $first_three_characters==="<lb" || $first_three_characters==="<si" || $first_three_characters==="<de" || $first_three_characters==="<un"|| $first_three_characters==="<hi"|| $first_three_characters==="<ad" || $first_three_characters==="<ga" || $first_three_characters==="<fw") {
    			$strlen = strlen($str);

    			$opening_tags = "";
    			$tempTag = "";
    			$tagName = "";
    			$endingTags = array();
    			$endingTagsNames = array();
    			$string_positions = array();
    			$endTag = false;
    			$array_escape_chars = array("<", "/", ">");
    			for ($i=0; $i<$strlen; $i++) {
    				$first_char = substr($str, $i, 1);
    				$first_two_chars = substr($str, $i, 2);
    				if ($first_two_chars==="</") {
    					$endTag = true;
    					$tempTag = "";
    					$tagName = "";
    					$string_positions[]=$i;
    				}
    				if ($endTag===true) {
    					$tempTag .= $first_char;
    					if (!in_array($first_char, $array_escape_chars)) {
    						$tagName .= $first_char;
    					}
    				}
    				if ($endTag===true && $first_char===">") {
    					// end tag
    					$endingTags[] = $tempTag;

    					$endingTagsNames[] = $tagName;

    					// check if the previous text contains the opening tag
    					$previous_text = substr($str, 0, $i);
    					if (strpos($previous_text, "<".$tagName)===false && $tagName!=="text" && $tagName!=="group" && $tagName!=="body") {
    						$opening_tags = "<".$tagName.">".$opening_tags;

    					}

    					// find start tag
    					$endTag = false;
    				}
    			}
    			return XML_utilities::closeOpenTags($opening_tags.$str);
    		}
    		else {
    			return XML_utilities::closeOpenTags($str);
    		}
    	}
    }

    function closeOpenTags($str) {
    	$str = trim($str);
    	$strlen = strlen($str);
    	$startTag = false;
    	$rm_tag = false;
    	$tempStartTag = "";
    	$tempStartTagName = "";
    	$rm_tmp = '';
    	$startTags = array();
    	$startTagsNames = array();
    	$stringChunks = array();
    	$closing_text = "";

    	$array_escape_chars = array("<", "/", ">");
    	for ($i=0; $i <= $strlen; $i++) {
    		$first_char = substr($str, $i, 1);
    		$first_two_chars = substr($str, $i, 2);
    		if ($first_char === '<' && $first_two_chars!=="</") {
    			$startTag = true;
    			$tempStartTag = "";
    			$tempStartTagName = "";
    		}
    		if ($startTag===true) {
    			$tempStartTag .= $first_char;
    			if (!in_array($first_char, $array_escape_chars)) {
    				$tempStartTagName .= $first_char;
    			}

    		}
    		if ($startTag===true && ($first_char===">" || $first_char===" ")) {
    			$startTags[] = $tempStartTag;

    			// end tag name
    			$startTagsNames[] = $tempStartTagName;

    			$startTag = false;

    			$next_text = substr($str, $i, $strlen);
    			// next text
    			if (strpos($next_text, "</".$tempStartTagName)===false) {
    				$stringChunks[] = $next_text;
    			}
    			// next p tag
    			if ($tempStartTagName==="p") {
    				$pOpenPos = strpos($next_text, "<p>");
    				$pClosePos = strpos($next_text, "</p>");
    				if ($pOpenPos!==false && $pOpenPos<$pClosePos) {

    				}
    				if ($pClosePos===false) {
    					if ($pOpenPos>0) {
    						$new_substring = "";
    					}
    					else {
    						$str = $str."</p>";
    					}
    				}
    			}
    		}
    	}
    	$response = array();
    	$response["startTags"] = $startTags;
    	$response["startTagsNames"] = $startTagsNames;
    	$response["stringChunks"] = $stringChunks;
    	return $str;

    }

    function addWrappingTags($str) {
    	if (strlen($str)>0) {
    		$first_character = $str[0];
    		$first_three_characters = substr($str, 0, 3);
    		$last_character = substr($str, -1);
    		$last_two_characters = substr($str, -2);

    		if (($last_two_characters==="->" || $last_character!==">") && ($first_character!=="<" || $first_three_characters==="<lb" || $first_three_characters==="<un" || $first_three_characters==="<hi" || $first_three_characters==="<ad" || $first_three_characters==="<ga" || $first_three_characters==="<de" || $first_three_characters==="<fw")) {
    			$str = "<p>".$str."</p>";
    		}
    		return $str;
    	}
    }

    function escapeHeadTags($str) {
    	if (strlen($str)>0) {
    		$str = str_replace("<head>", "<headescaped>", $str);
    		$str = str_replace("</head>", "</headescaped>", $str);
    	}
    	return $str;
    }

    function restoreHeadTags($str) {
    	if (strlen($str)>0) {
    		$str = str_replace("<headescaped>", "<head>", $str);
    		$str = str_replace("</headescaped>", "</head>", $str);
    	}
    	return $str;
    }

    function removePBFromComments($str) {
    	$str = mb_convert_encoding($str, "UTF-8", "auto");
    	$strlen = strlen($str);
    	if ($strlen>0) {
    		$comments = array();
    		$isComment = false;
    		$commentText = "";
    		$startPosition = 0;
    		$endPosition = 0;
    		for ($i=0; $i <= $strlen; $i++) {
    			$first_char = substr($str, $i, 1);
    			$first_four_chars = substr($str, $i, 4);
    			$last_three_chars = substr($str, $i, 3);
    			if ($first_four_chars==="<!--") {
    				$isComment = true;
    				$startPosition = $i;
    				$commentText = "";
    			}
    			if ($isComment===true) {
    				$commentText .= $first_char;
    			}
    			if ($isComment===true && $last_three_chars ==="-->") {
    				$commentText .="->";
    				$isComment = false;
    				$endPosition = intval($i)-3;
    				$comment = array("start"=>$startPosition, "end"=>$endPosition, "text"=>$commentText);
    				$comments[] = $comment;
    			}
    		}

    		for ($j=0; $j<count($comments); $j++) {
    			$comment = $comments[$j];
    			$commentStart = $comment['start'];
    			$commentEnd = $comment['end'];
    			$commentText = $comment['text'];
    			$newCommentText = "";
    			$isPB = false;
    			$pbTags = array();
    			$pbTag = "";
    			$start = 0;
    			$end = 0;
    			for ($k=0; $k<strlen($commentText); $k++) {
    				$first_comment_char = substr($commentText, $k, 1);
    				$first_comment_three_chars = substr($commentText, $k, 3);
    				if ($first_comment_three_chars==="<pb") {
    					$isPB=true;
    					$start = $k;
    				}
    				if ($isPB===true) {
    					$pbTag .=$first_comment_char;
    				}
    				if ($isPB===true && $first_comment_char===">") {
    					$pbTags[]=$pbTag;
    					$isPB=false;
    					$end = $k;
    				}

    			}
    			$newCommentText = $commentText;
    			for($p=0; $p<count($pbTags);$p++) {
    				$pbTag = $pbTags[$p];
    				$newCommentText = str_replace($pbTag, "", $commentText);
    			}
    			$str = str_replace($commentText, $newCommentText, $str);
    		}
    	}
    	return $str;
    }

}
