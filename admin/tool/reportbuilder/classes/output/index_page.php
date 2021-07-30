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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class tool_reportbuilder\output\index_page
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
namespace tool_reportbuilder\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_reportbuilder\permission;

/**
 * Class tool_reportbuilder\output\index_page
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class index_page implements \templatable, \renderable {

    /**
     * index_page constructor.
     */
    public function __construct() {
        $this->prepare_page();
    }

    /**
     * Implementation of exporter from templatable interface
     *
     * @param renderer_base $output
     *
     * @return array|\stdClass
     * @throws \coding_exception
     */
    public function export_for_template(renderer_base $output) {
        $attributestab = ['id' => 'main'];
        $tabsoutput = new \tool_wp\output\tabs($attributestab);
        $data = [];
        $tabsoutput->add_tab(new \tool_reportbuilder\output\tabs\reports($data));
        if (permission::can_view_all_schedules_list()) {
            $tabsoutput->add_tab(new \tool_reportbuilder\output\tabs\schedules($data));
        }
        $tabscontent = $tabsoutput->export_for_template($output);
        return $tabscontent;
    }

    /**
     * Sets up the global $PAGE and performs the access checks.
     */
    protected function prepare_page() {
        global $CFG, $PAGE;
        require_once($CFG->libdir.'/adminlib.php');

        $url = new \moodle_url('/admin/tool/reportbuilder/index.php');
        $PAGE->set_url($url);

        $context = \context_system::instance();
        $PAGE->set_context($context);

        admin_externalpage_setup('tool_reportbuilder');

        $strtitle = get_string('customreports', 'tool_reportbuilder');

        if (!permission::can_view_any()) {
            $PAGE->navbar->add($strtitle);
        }

        $PAGE->set_title($strtitle);
        $PAGE->set_heading($strtitle);
    }
}
