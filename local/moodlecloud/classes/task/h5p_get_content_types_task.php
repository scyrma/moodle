<?php
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
 * A scheduled task for updating H5P updating H5P content types.
 *
 * @package   local_moodlecloud
 * @copyright 2020 Cameron Ball <cameron@cameron1729.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\task;
defined('MOODLE_INTERNAL') || die();

use core_h5p\{file_storage, framework, factory, helper, core};
use core_h5p\local\library\autoloader;

/**
 * Similar to core's task, but checks if the files are already cached locally.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class h5p_get_content_types_task extends \core\task\scheduled_task {

    public function get_name() : string {
        return get_string('h5p_get_content_types_task', 'local_moodlecloud');
    }

    public function execute() {
        global $CFG, $DB, $dynamicsite;

        if (substr($dynamicsite, 0, 8) == 'template') {
            return;
        }

        autoloader::register();

        $fs = new file_storage();
        $framework = new framework();
        $language = \core_h5p\framework::get_language();
        $context = \context_system::instance();
        $url = \moodle_url::make_pluginfile_url($context->id, 'core_h5p', '', null,
                                                '', '')->out();
        $hackedcore = new class($framework, $fs, $url, $language, true) extends core {
            public function __construct($framework, $path, $url, $language = 'en', $export = false) {
                parent::__construct($framework, $path, $url, $language, $export);
            }

            public function fetch_content_type(array $library): ?int {
                global $CFG;
                $librarykey = static::libraryToString($library);
                $librarysourcepath = $CFG->moodlecloud_h5p_library_sources_path . '/' . str_replace([' ', '.'], '_', $librarykey);

                $factory = new factory();
                $fs = get_file_storage();
                $libraryfileinfo = (object) [
                    'component' => 'core_h5p',
                    'filearea' => 'library_sources',
                    'itemid' => 0,
                    'contextid' => (\context_system::instance())->id,
                    'filepath' => '/',
                    'filename' => $library['machineName'],
                ];

                if (file_exists($librarysourcepath)) {
                    $file = $fs->create_file_from_pathname($libraryfileinfo, $librarysourcepath);
                } else {
                    // Download the latest content type from the H5P official repository.
                    $file = $fs->create_file_from_url(
                        $libraryfileinfo,
                        $this->get_api_endpoint($library['machineName']),
                        null,
                        true
                    );

                    if (!$file) {
                        return null;
                    }
                }

                helper::save_h5p($factory, $file, (object) [], false, true);

                $file->delete();
                $libraryid = $factory->get_storage()->h5pC->librariesJsonData[$librarykey]["libraryId"];

                return $libraryid;
            }
        };

        $result = $hackedcore->fetch_latest_content_types();

        if (!empty($result->error)) {
            mtrace($result->error);
        } else {
            $numtypesinstalled = count($result->typesinstalled);
            mtrace("{$numtypesinstalled} new content types installed");
        }
    }
}
