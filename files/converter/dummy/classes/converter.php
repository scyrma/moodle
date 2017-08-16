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

use core_files\conversion;
use core_files\converter_interface;

/**
 * Dummy filconverter. Always serves the same PDF regardless of input.
 *
 * @package    fileconverter_dummy
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converter implements converter_interface {

    public function start_document_conversion(conversion $conversion) : self {
        global $CFG;

        // set_sourcefile is a hack to make the document converter API think it's reconverting the same
        // file every time. This makes it clean up old records in the files table so the user's quota
        // shouldn't get taken up by a million placeholder PDFs.
        $conversion->set_sourcefile(get_file_storage()->get_file_by_id(1))
                   ->store_destfile_from_path($CFG->dirroot . '/files/converter/dummy/placeholder.pdf')
                   ->set('status', conversion::STATUS_COMPLETE)
                   ->set('statusmessage', 'Can I hab a ubrgrade pls?')
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
        return get_string('upgrademessage', 'fileconverter_dummy');
    }
}
