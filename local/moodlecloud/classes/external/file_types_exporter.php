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
 * Notifications exporter.
 *
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\external;
defined('MOODLE_INTERNAL') || die();

use context_system;
use core\external\exporter;
use local_moodlecloud\external\file_type_exporter;
use renderer_base;
use stdClass;

/**
 * Class to export a list of notifications.
 *
 * @copyright 2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_types_exporter extends exporter {
    /**
     * Constructor.
     *
     * @param array $notifications Array of notifications.
     * @param array $related Array of related data.
     */
    public function __construct(array $filetypes, array $related = []) {
        $this->filetypes = $filetypes;
        parent::__construct([], $related);
    }

    protected static function define_other_properties() : array {
        return [
            'filetypes' => [
                'type' => file_type_exporter::read_properties_definition(),
                'multiple' => true
            ]
        ];
    }

    protected function get_other_values(renderer_base $output) : array {
        return [
            'filetypes' => array_map(
                function(stdClass $filetype) use ($output) {
                    return (new file_type_exporter($filetype, ['context' => context_system::instance()]))->export($output);
                },
                $this->filetypes
            )
        ];
    }
}
