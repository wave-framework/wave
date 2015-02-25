<?php

namespace Wave\Utils;

use Wave\DB\Model;
use XMLWriter;

class XML {

    const TYPE_OPEN = "open";
    const TYPE_COMPLETE = "complete";
    const TYPE_CLOSE = "close";

    public static function encode(array $data, $rootNodeName = 'response') {

        $xml = new XmlWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($rootNodeName);

        $data = self::arrayify($data);
        function write(XMLWriter $xml, $data) {
            foreach($data as $_key => $value) {
                // check the key isnt a number, (numeric keys invalid in XML)
                if(is_numeric($_key)) $key = 'element';
                else if(!is_string($_key) || empty($_key) || strncmp($_key, '_', 1) === 0) continue;
                else $key = $_key;

                $xml->startElement($key);

                // if the key is numeric, add an ID attribute to make tags properly unique
                if(is_numeric($_key)) $xml->writeAttribute('id', $_key);

                // if the value is an array recurse into it
                if(is_array($value)) write($xml, $value);
                // otherwise write the text to the document
                else $xml->text($value);

                $xml->endElement();
            }
        }

        // start the writing process
        write($xml, $data);

        $xml->endElement();

        return $xml->outputMemory();
    }

    public static function decode($contents) {

        if(!$contents) return array();

        //Get the XML parser of PHP - PHP must have this module for the parser to work
        $parser = xml_parser_create("");
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
        # http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, trim($contents), $xml_values);
        xml_parser_free($parser);

        if(!$xml_values) return;

        //Initializations
        $xml_array = array();

        $current = &$xml_array;

        //Go through the tags.
        foreach($xml_values as $data) {
            unset($attributes, $value); //Remove existing values
            // sets tag(string), type(string), level(int), attributes(array).
            extract($data);

            // Set value if it exists
            $result = null;
            if(isset($value))
                $result = $value;

            //The starting of the tag '<tag>'
            if($type == self::TYPE_OPEN) {
                $parent[$level - 1] =& $current;
                // Insert a new tag
                if(!is_array($current) or (!in_array($tag, array_keys($current)))) {
                    $current[$tag] = $result;
                    $current =& $current[$tag];
                } // A duplicate key exists, make it an array
                else {
                    if(isset($current[$tag][0]) and is_array($current[$tag]))
                        $current[$tag][] = $result;
                    //This section will make the value an array if multiple tags with the same name appear together
                    else
                        $current[$tag] = array($current[$tag], $result);//This will combine the existing item and the new item together to make an array

                    $current = &$current[$tag][count($current[$tag]) - 1];
                }

            } //Tags with no content '<tag />'
            else if($type == self::TYPE_COMPLETE) {
                //See if the key is already taken.
                if(!isset($current[$tag]))
                    $current[$tag] = $result;
                else {
                    //If it is already an array...
                    if(isset($current[$tag][0]) and is_array($current[$tag]))
                        $current[$tag][] = $result;
                    //Make it an array using using the existing value and the new value
                    else
                        $current[$tag] = array($current[$tag], $result);
                }

            } //End of tag '</tag>'
            else if($type == self::TYPE_CLOSE)
                $current =& $parent[$level - 1];
        }

        return $xml_array;
    }

    public static function arrayify($data, array $force = array(), $forceEach = true) {
        if(is_array($data) || is_object($data)) {
            $jsonarr = array();
            if($data instanceof Model)
                $data = $data->_toArray();
            foreach($data as $key => $value) {
                $jsonarr[$key] = self::arrayify($value, $forceEach ? $force : array(), false);
            }
            return $jsonarr;
        } else {
            return $data;
        }
    }

}

?>