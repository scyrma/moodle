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
 * File for a class for exporting storage files data.
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\external;

use context;
use core\external\exporter;
use external_files;
use moodle_url;
use renderer_base;
use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for exporting stored files data.
 *
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_exporter extends exporter {
    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'file' => stored_file::class,
        ];
    }

    /**
     * Return the list of additional properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return external_files::get_properties_for_exporter();
    }

    /**
     * Get other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        /** @var context $context */
        $context = $this->related['context'];
        /** @var stored_file $file */
        $file = $this->related['file'];

        return [
            'filename' => $file->get_filename(),
            'fileurl' => $this->get_file_url($file),
            'filesize' => $file->get_filesize(),
            'filepath' => $file->get_filepath(),
            'mimetype' => $file->get_mimetype(),
            'timemodified' => $file->get_timemodified(),
        ];
    }

    /**
     * Returns a file url string.
     *
     * @param stored_file $file
     * @return string
     */
    private function get_file_url(stored_file $file): string {
        return moodle_url::make_webservice_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
            $file->get_itemid(), $file->get_filepath(), $file->get_filename())->out(false);
    }
}
