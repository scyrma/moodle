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
 * Class for converting files between different file formats using CloudConvert.
 *
 * @package    fileconverter_cloudconvert
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace fileconverter_cloudconvert;
defined('MOODLE_INTERNAL') || die();

use CloudConvert\Api as cloudconvert_api;
use CloudConvert\Exceptions\ApiTemporaryUnavailableException as cloudconvert_unavailable_exception;
use CloudConvert\Process as cloudconvert_process;
use Exception;
use core_files\conversion;
use core_files\converter_interface;
use moodle_exception;
use moodle_url;
use stored_file;

/**
 * Class for converting files between different formats using CloudConvert.
 *
 * @package    fileconverter_cloudconvert
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class converter implements converter_interface {

    /**
     * Various CloudConvert formats. Not all of these will be supported (depends on
     * which formats Moodle knows about, see get_supported_extensions).
     */
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

    /**
     * @var array $mimetypes Supported mimetypes.
     */
    private static $mimetypes;


    /**
     * @var array $extensions Supported extensions.
     */
    private static $extensions;

    /**
     * @var \stdClass $config Moodle config.
     */
    private $config;

    /**
     * Construtor.
     */
    public function __construct() {
        global $CFG;
        $this->config = $CFG;
    }

    public function start_document_conversion(conversion $conversion) : self {
        self::cloudconvert_api_call(function(conversion $conversion) {
            $process = (new cloudconvert_api($this->config->cloudconvertapikey))
                     ->convert([
                         'inputformat' => pathinfo($conversion->get_sourcefile()->get_filename(), PATHINFO_EXTENSION),
                         'outputformat' => $conversion->get('targetformat'),
                         'input' => 'base64',
                         'file' => base64_encode($conversion->get_sourcefile()->get_content()),
                         'filename' => $conversion->get_sourcefile()->get_filename()
                     ]);

            $conversion->set('data', (object)['url' => $process->url])
                       ->set('statusmessage', 'Reticulating splines.')
                       ->set('status', conversion::STATUS_IN_PROGRESS)
                       ->update();
        }, $conversion);

        return $this;
    }

    public function poll_conversion_status(conversion $conversion) : self {
        self::cloudconvert_api_call(function(conversion $conversion) {
            $process = (new cloudconvert_process(
                new cloudconvert_api($this->config->cloudconvertapikey),
                $conversion->get('data')->url
            ))->refresh();

            if ($process->step == 'finished') {
                $tmpfile = make_request_directory() . '/' . uniqid() . '.' . $conversion->get('targetformat');
                $process->download($tmpfile);

                $conversion->store_destfile_from_path($tmpfile)
                           ->set('status', conversion::STATUS_COMPLETE)
                           ->set('statusmessage', $process->message)
                           ->update();
            }
        }, $conversion);

        return $this;
    }

    public static function are_requirements_met() : bool {
        // Can't avoid using global CFG here since this function is called
        // statically, before the class is instantiated.
        global $CFG;
        return isset($CFG->cloudconvertapikey);
    }

    public static function supports($from, $to) : bool {
        return
            // Is the input format accepted?
            in_array(\core_filetypes::get_types()[$from]['type'], self::get_supported_mimetypes()) &&
            // Is the output format accepted?
            in_array($to, self::get_supported_extensions());
    }

    /**
     * Returns a comma separated list of extensions supported by this plugin.
     *
     * @return string
     */
    public function get_supported_conversions() : string {
        return join(
            ', ',
            (function(array $array) : array {
                sort($array);
                return $array;
            })(self::get_supported_extensions())
        );
    }

    /**
     * Call the function provided, with safegaurds in place for exceptions.
     *
     * The API key and conversion parameters will be passed to $op.
     *
     * @param callable $op Function to run.
     * @param string $apikey CloudConvert API key.
     * @param conversion $conversion Document conversion process.
     */
    private function cloudconvert_api_call(callable $op, conversion $conversion) {
        // Nasty hack. Both the S3 SDK and the CloudConvert SDK bundle their own version of guzzle.
        // So  we register our own autoloader to load ONLY the CloudConvert components.
        // The guzzle components will be loaded by some other autoloader registered already.
        spl_autoload_register(
            function($class) {
                if (strpos($class, 'CloudConvert') === 0) {
                    require_once(
                        sprintf(
                            'phar://%s/files/converter/cloudconvert/cloudconvert-php.phar/src/%s.php',
                            $this->config->dirroot,
                            str_replace("\\", "/", explode("\\", $class, 2)[1])
                        )
                    );
                }
            }
        );

        try {
            $op($conversion);
        } catch (cloudconvert_unavailable_exception $e) {
            // Don't change conversion status, or rethrow the exception.
            // This has the effect that we can keep polling the conversion
            // status without the user getting disrupted.
            $conversion->set('statusmessage', $e->getMessage());
            $conversion->update();
        } catch (Exception $e) {
            // For any other failuers, fail the conversion and rethrow.
            $conversion->set('status', conversion::STATUS_FAILED);
            $conversion->set('statusmessage', $e->getMessage());
            $conversion->update();
            throw $e;
        }
    }

    /**
     * Get an array of all the mimetypes we can support.
     *
     * The converter API passes an extension to `supports` based on the mimetype of the
     * file. When there are multiple extensions for the same mimetype, the first on specified
     * is returned (for example, a jpeg will return 'jpe'.
     *
     * This means we cannot rely on looking up $from in our list of supported types. Instead
     * we transform that list in to a list of supported mimetypes. This is done by first removing
     * any type that Moodle doesn't know about, then looking up the mimetype for that extension.
     *
     * We can then use this new list in the `supports` method.
     *
     * @return array An array of supported mimetypes.
     */
    private static function get_supported_mimetypes() : array {
        return self::$mimetypes ?? self::$mimetypes = array_unique(
            array_map(
                function(string $extension) : string {
                    return \core_filetypes::get_types()[$extension]['type'];
                },
                self::get_supported_extensions()
            )
        );
    }

    /**
     * Get an array of all of the extensions we can support.
     *
     * We can't just return everything defined in the constants above because
     * Moodle defines what file types it supports, so we need to filter the list
     * and exclude those that Moodle won't play with.
     *
     * @return array An array of supported extensions.
     */
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
}
