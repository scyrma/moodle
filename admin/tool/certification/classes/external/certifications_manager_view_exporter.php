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
 * Class tool_certification\external\certifications_manager_view_exporter
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\external;

defined('MOODLE_INTERNAL') || die();

use core\external\exporter;
use moodle_url;
use renderer_base;
use tool_certification\permission;
use tool_certification\local\reports\active_table;
use tool_reportbuilder\system_report_factory;
use html_writer;

/**
 * Class certifications_manager_view_exporter
 *
 * @package tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certifications_manager_view_exporter extends exporter {
    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'contextid' => [
                'type' => PARAM_INT,
            ],
            'certifications' => [
                'type' => PARAM_TEXT,
            ],
            'archived' => [
                'type' => PARAM_TEXT,
            ],
            'certificationslisttable' => [
                'type' => PARAM_CLEANHTML
            ],
            'newcertificationurl' => [
                'type' => PARAM_URL,
            ],
        ];
    }

    /**
     * Gets other values from the defined ones.
     *
     * @param renderer_base $output
     * @return array
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function get_other_values(renderer_base $output): array {
        $context = $this->related['context'];

        $newcerturl = '';
        if (permission::can_create($context)) {
            $newcertificationurl = new moodle_url('/admin/tool/certification/edit.php');
            $newcerturl = $newcertificationurl->out(false);
        }

        $archived = get_string('archived', 'tool_certification');
        $certifications = get_string('certifications', 'tool_certification');
        $contextid = $context->id;

        if (class_exists('\\tool_reportbuilder\\system_report_factory')) {
            // Active certifications list.
            $report = system_report_factory::create(active_table::class);
            $table = $report->output();
        } else {
            $str = get_string('reportbuilderactivecertifications', 'tool_certification');
            $table = html_writer::tag('div', $str, array('class' => 'alert alert-warning'));
        }

        return [
            'contextid' => $contextid,
            'certifications' => $certifications,
            'archived' => $archived,
            'certificationslisttable' => $table,
            'newcertificationurl' => $newcerturl,
        ];
    }
}