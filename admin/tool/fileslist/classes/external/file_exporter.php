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
 * File exporter.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core\external\exporter;
use renderer_base;
use tool_fileslist\file;

/**
 * Class for exporting a file object.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_exporter extends exporter {
    /**
     * @var file $file The file object to export.
     */
    private $file;

    public function __construct(file $file, array $related = []) {
        $this->file = $file;

        parent::__construct(
            (object)[
                'id' => $file->get_id(),
                'component' => $file->get_component(),
                'filearea' => $file->get_filearea(),
                'name' => $file->get_name(),
                'mimetype' => $file->get_mimetype(),
                'size' => $file->get_size(),
                'location' => $file->get_location_url()->out(true)
            ],
            $related
        );
    }

    protected static function define_other_properties() : array  {
        return [
            'user' => [
                'type' => user_exporter::read_properties_definition()
            ]
        ];
    }

    protected static function define_properties() : array {
        return [
            'id' => ['type' => PARAM_INT],
            'component' => ['type' => PARAM_TEXT],
            'filearea' => ['type' => PARAM_TEXT],
            'name' => ['type' => PARAM_TEXT],
            'mimetype' => ['type' => PARAM_TEXT],
            'size' => ['type' => PARAM_INT],
            'location' => ['type' => PARAM_URL]
        ];
    }

    protected function get_other_values(renderer_base $output) : array {
        return [
            'user' => (new user_exporter($this->file->get_user(), ['context' => context_system::instance()]))->export($output)
        ];
    }

    protected static function define_related() : array {
        return ['context' => 'context'];
    }
}
