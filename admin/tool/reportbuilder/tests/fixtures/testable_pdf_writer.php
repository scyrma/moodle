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
 * pdf data format writer
 *
 * @package    tool_reportbuilder
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * pdf data format writer
 *
 * @package    tool_reportbuilder
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class testable_pdf_writer extends \dataformat_pdf\writer {

    /**
     * Echo PDF content as a string
     *
     * @return void
     */
    public function close_output() {
        echo $this->pdf->Output(null, 'S');
    }
}