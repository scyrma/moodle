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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * File for a class for exporting export details data.
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Mikel Martín
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\external;

use core\external\persistent_exporter;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\exports_list_report;
use tool_wp\local\exportimport\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for exporting export details data.
 *
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Mikel Martín
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_details_exporter extends persistent_exporter {
    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class(): string {
        return export_persistent::class;
    }

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
     * Other properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'statusstr' => [
                'type' => PARAM_RAW,
            ],
            'iscomplete' => [
                'type' => PARAM_BOOL,
            ],
            'isinprogress' => [
                'type' => PARAM_BOOL,
            ],
            'issuccess' => [
                'type' => PARAM_BOOL,
            ],
            'progress' => [
                'type' => PARAM_INT,
            ],
            'date' => [
                'type' => PARAM_TEXT,
            ],
            'user' => [
                'type' => PARAM_TEXT,
            ],
            'tenant' => [
                'type' => PARAM_TEXT,
            ],
            'exporterstr' => [
                'type' => PARAM_TEXT,
            ],
            'version' => [
                'type' => PARAM_TEXT,
            ],
            'size' => [
                'type' => PARAM_TEXT,
            ],
            'summary' => [
                'type' => PARAM_RAW,
            ],
            'errors' => [
                'type' => PARAM_RAW,
            ],
        ];
    }

    /**
     * Exported content and instances
     *
     * @param \renderer_base $output
     * @param export_manager $exportmanager
     * @param exporter_base $exporter
     * @return string
     */
    protected function get_summary(\renderer_base $output, export_manager $exportmanager, exporter_base $exporter) {
        $settings = $exporter->get_summary_for_review_step($exportmanager->is_completed());
        $instances = helper::display_instances_list(@json_decode($this->persistent->get('reviewdata'), true)['instances'] ?? []);

        return $settings . $instances;
    }

    /**
     * Other values.
     *
     * @param \renderer_base $output
     * @return array
     */
    protected function get_other_values(\renderer_base $output): array {
        global $DB;

        $user = $DB->get_record('user', array('id' => $this->persistent->get('createdby')));
        $exportmanager = new export_manager(0, $this->persistent);
        $exporter = helper::get_available_exporter($this->persistent->get('exporter'), $this->persistent->get('entrypoint'),
            $this->persistent->get('entrypointid'), $exportmanager);
        $summary = $this->get_summary($output, $exportmanager, $exporter);
        $statusstr = exports_list_report::format_status($this->persistent->get('status'));
        $iscompleted = $exportmanager->is_completed();
        $isinprogress = $this->persistent->get('status') == helper::STATUS_IN_PROGRESS ||
            $this->persistent->get('status') == helper::STATUS_SCHEDULED;
        $issuccess = $this->persistent->get('status') == helper::STATUS_DONE;
        $progress = $exportmanager->get_export_progress();
        $date = userdate(
            (int) $this->persistent->get('timemodified'),
            get_string('strftimedatetime', 'langconfig')
        );
        $user = fullname(
            $user,
            has_capability('moodle/site:viewfullnames', \context_system::instance())
        );
        $tenant = \tool_tenant\permission::can_switch_tenant() ? helper::format_tenantid($this->persistent->get('tenantid')) : '';
        $exporterstr = helper::format_exporter($this->persistent->get('exporter'), (object) [
            'entrypoint' => $this->persistent->get('entrypoint'),
            'entrypointid' => $this->persistent->get('entrypointid'),
        ]);
        $exportfile = helper::get_export_file($this->persistent->get('id'));
        $size = $exportfile ? display_size($exportfile->get_filesize()) : '';
        $version = $exportmanager->get_review_data()['release'] ?? '';

        return [
            'statusstr' => $statusstr,
            'errors' => helper::format_export_errors($exportmanager->get_export_errors(), true),
            'iscomplete' => $iscompleted,
            'isinprogress' => $isinprogress,
            'issuccess' => $issuccess,
            'progress' => $progress,
            'date' => $date,
            'user' => $user,
            'tenant' => $tenant,
            'exporterstr' => $exporterstr,
            'version' => $version,
            'size' => $size,
            'summary' => $summary
        ];
    }
}
