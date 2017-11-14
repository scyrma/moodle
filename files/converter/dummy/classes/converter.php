<?php declare(strict_types=1);
// This file is part of Moodle - http://moodle.org/
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

/**
 * A dummy fileconverter that always serves the same PDF no matter what the input.
 *
 * @package    fileconverter_dummy
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace fileconverter_dummy;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/logging/vendor/autoload.php');

use core_files\conversion;
use core_files\converter_interface;
use local_logging\logger;

/**
 * Dummy filconverter. Always serves the same PDF regardless of input.
 *
 * @package    fileconverter_dummy
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class converter implements converter_interface {

    // Coppied from the real CloudConvert plugin.
    const FORMATS = [
        'document' => [
            'abw', 'djvu', 'doc', 'docm', 'docx', 'html', 'lwp', 'md', 'odt', 'pages', 'pages.zip', 'pdf', 'rst', 'rtf', 'sdw',
            'tex', 'txt', 'wpd', 'wps', 'zabw'
        ],

        'image' => [
            '3fr', 'arw', 'bmp', 'cr2', 'crw', 'dcr', 'dng', 'eps', 'erf', 'gif', 'icns', 'ico', 'jpeg', 'jpg', 'mos', 'mrw',
            'nef', 'odd', 'orf', 'pdf', 'pef', 'png', 'ppm', 'ps',  'psd', 'raf', 'raw', 'svg', 'svgz', 'tif', 'tiff', 'webp',
            'x3f', 'xcf', 'xps'
        ],

        'presentation' => [
            'eps', 'html', 'key', 'key.zip', 'odp', 'pdf', 'pps', 'ppsx', 'ppt', 'pptm', 'pptx', 'ps', 'sda', 'swf'
        ],


        'spreadsheet' => [
            'csv', 'html', 'numbers', 'numbers.zip', 'ods', 'pdf', 'sdc', 'xls', 'xlsm', 'xlsx'
        ]
    ];

    const KNOWN_FILEAREA_COMPONENT_COMBINATIONS = [
        [
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files'
        ],
        [
            'component' => 'assignfeedback_editpdf',
            'filearea' => 'submissions_onlinetext'
        ],
        [
            'component' => 'assignfeedback_editpdt',
            'filearea' => 'importhtml'
        ]
    ];

    private static $extensions;

    public function start_document_conversion(conversion $conversion) : self {

        if (!array_filter(
            KNOWN_FILEAREA_COMPONENT_COMBINATIONS,
            function(array $componentandfilearea) use ($conversion) {
                return
                    $conversion->get_sourcefile()->get_component() ==  $componentandfilearea['component'] &&
                    $conversion->get_sourcefile()->get_filearea() == $componentandfilearea['filearea'];
            })
        ) {
            logger::log(
                'Unusual filearea or component detected',
                [
                    'filearea' => $conversion->get_sourcefile()->get_filearea(),
                    'component' => $conversion->get_sourcefile()->get_component()
                ],
                'documentconverter_dummy',
                \Monolog\Logger::WARNING
            );
        }

        $conversion->store_destfile_from_string(self::get_placeholder_pdf())
                   ->set('status', conversion::STATUS_COMPLETE)
                   ->set('statusmessage', 'Upgrade please!')
                   ->update();

        return $this;
    }

    public function poll_conversion_status(conversion $conversion) : self {
        return $this;
    }

    public static function are_requirements_met() : bool {
        return true;
    }

    public static function supports($from, $to) : bool {
        return true;
    }

    public function get_supported_conversions() : string {
        return get_string(
            'upgrademessage',
            'fileconverter_dummy',
            join(
                ', ',
                (function(array $array) : array {
                    sort($array);
                    return $array;
                })(self::get_supported_extensions())
            )
        );
    }

    // Copied from the real converter plugin.
    private static function get_supported_extensions() : array {
        return self::$extensions ?? self::$extensions = array_unique(
            array_filter(
                array_reduce(
                    self::FORMATS,
                    function(array $c, array $v) : array {
                        return array_merge($c, $v);
                    }, []
                ),
                function(string $extension) : bool {
                    return isset(\core_filetypes::get_types()[$extension]);
                }
            )
        );
    }

    private static function get_placeholder_pdf() : string {
        global $CFG;

        return file_get_contents('https://assets.gl.moodlecloud.com/moodle/MoodleCloudConverterMessage.pdf') ?:
               file_get_contents($CFG->dirroot . '/files/converter/dummy/placeholder.pdf');
    }
}
