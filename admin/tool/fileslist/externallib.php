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
 * External fileslist API.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_fileslist\external\files_exporter;
use tool_fileslist\container;

require_once($CFG->libdir . '/externallib.php');

final class tool_fileslist_external extends external_api {
    public static function get_files_by_size(int $offset, int $limit) {
        if (!is_siteadmin()) {
            throw new moodle_exception('nopermissions', 'error', '', 'access files list');
        }

        global $PAGE;

        self::validate_context(\context_system::instance());

        return (
            new files_exporter(container::get_files_by_size_repository($offset, $limit)->get_valid_files())
        )->export($PAGE->get_renderer('core'));
    }

    protected static function get_files_by_size_returns() {
        return files_exporter::get_read_structure();
    }

    protected static function get_files_by_size_parameters() {
        return new external_function_parameters([
            new external_value(PARAM_INT),
            new external_value(PARAM_INT)
        ]);
    }
}
