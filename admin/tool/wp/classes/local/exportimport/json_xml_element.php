<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

namespace tool_wp\local\exportimport;

/**
 * Class json_xml_element, used in simplexml_load_string() for converting XML to arrays
 *
 * Initially from: https://stackoverflow.com/questions/15092338/php-simplexmlelement-to-array-null-value
 * Modified to:
 * - translate $@NULL@$ into null
 * - translate empty values into empty strings (rather than empty arrays)
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class json_xml_element extends \SimpleXMLElement implements \JsonSerializable {

    // @codingStandardsIgnoreStart

    #[\ReturnTypeWillChange]
    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed data which can be serialized by json_encode.
     */
    public function jsonSerialize() { // @codingStandardsIgnoreEnd
        $array = array();

        // Json encode attributes if any.
        if ($attributes = $this->attributes()) {
            $array['@attributes'] = iterator_to_array($attributes);
        }

        // Json encode child elements if any. group on duplicate names as an array.
        $childrenkeys = [];
        foreach ($this as $name => $element) {
            $childrenkeys[$name] = $name;
        }
        if (count($childrenkeys) == 1 && $name . 's' == $this->getName()) {
            // This element has all children with the same name and this is the same name as this element withtout trailing "s".
            // For example: <_files><_file>...</_file><_file>....</_file></_files> .
            foreach ($this as $name => $element) {
                $array[] = $element;
            }
        } else {
            foreach ($this as $name => $element) {
                if (isset($array[$name])) {
                    if (!is_array($array[$name])) {
                        $array[$name] = [$array[$name]];
                    }
                    $array[$name][] = $element;
                } else {
                    $array[$name] = $element;
                }
            }
        }

        // Json encode non-whitespace element simplexml text values.
        if ($array && strlen(trim($this))) {
            // The element has both child elements and text (does not happen in Workplace but just in case).
            $array['@text'] = trim($this);
        } else if (!$array) {
            // The element does not have child elements. Treat it as string. Note that empty elements will be returned as empty
            // strings, not empty arrays.
            $array = '' . $this;
            if ($array === '$@NULL@$') {
                // String '$@NULL@$' means null.
                $array = null;
            }
        }

        return $array;
    }
}
