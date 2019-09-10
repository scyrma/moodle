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
 * Class report
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\output;

use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class report_view
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_view implements \templatable, \renderable {

    /** @var report_base */
    protected $report;

    /** @var bool $editon */
    protected $editon;

    /** @var int $reportid */
    protected $reportid;
    /**
     * report_view constructor.
     *
     * @param int $reportid
     * @param int $editon
     *
     * @throws \coding_exception
     */
    public function __construct($reportid, $editon) {
        $this->report = manager::get_report($reportid);
        $this->editon = $editon;
        $this->reportid = $reportid;
        $this->prepare_page();
    }

    /**
     * Export the context for a template.
     *
     * @param \renderer_base $output
     *
     * @return array|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function export_for_template(\renderer_base $output) {
        $content = $this->report->export($output, (int)$this->editon);
        return $content;
    }

    /**
     * Sets up the global $PAGE and performs the access checks.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function prepare_page() {
        global $CFG, $PAGE;
        require_once($CFG->libdir.'/adminlib.php');

        $url = new \moodle_url('/admin/tool/reportbuilder/view.php', ['id' => $this->reportid]);

        $context = \context_system::instance();
        $PAGE->set_context($context);

        permission::require_can_view($this->report);

        admin_externalpage_setup('tool_reportbuilder');

        $PAGE->set_url($url);

        $cancreate = permission::can_create();

        $identifier = $cancreate ? 'myreports' : 'customreports';
        $strtitle = get_string($identifier, 'tool_reportbuilder');

        if (!$cancreate) {
            $PAGE->navbar->add($strtitle, new \moodle_url('/admin/tool/reportbuilder/index.php'));
        }

        $PAGE->navbar->add(format_string($this->report->get_reportname()));

        $PAGE->set_title($strtitle);
        $PAGE->set_heading($strtitle);
    }
}