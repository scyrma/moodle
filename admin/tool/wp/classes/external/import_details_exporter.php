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
 * File for a class for exporting import details data.
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Mikel Martín
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\external;

use core\external\persistent_exporter;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\import_persistent;
use tool_wp\reportbuilder\local\systemreports\imports;

/**
 * Class for exporting import details data.
 *
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Mikel Martín
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import_details_exporter extends persistent_exporter {
    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class(): string {
        return import_persistent::class;
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
            'importerstr' => [
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
            'conflicts' => [
                'type' => PARAM_RAW,
            ],
            'hasconflicts' => [
                'type' => PARAM_BOOL,
            ],
        ];
    }

    /**
     * Summary section
     *
     * @param \renderer_base $output
     * @param import_manager $importmanager
     * @return string
     */
    protected function get_summary(\renderer_base $output, import_manager $importmanager) {
        $summary = '';
        foreach ($importmanager->get_importers() as $importer) {
            $summary .= $importer->get_summary_for_review_step($importmanager->is_completed());
        }
        $instances = helper::display_instances_list(@json_decode($this->persistent->get('reviewdata'), true)['instances'] ?? []);
        return $summary . $instances;
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
        $importmanager = new import_manager(0, $this->persistent);

        $statusstr = imports::format_status($this->persistent->get('status'));
        $iscompleted = $importmanager->is_completed();
        $isinprogress = $this->persistent->get('status') == helper::STATUS_IN_PROGRESS ||
            $this->persistent->get('status') == helper::STATUS_SCHEDULED;
        $issuccess = $this->persistent->get('status') == helper::STATUS_DONE;
        $progress = $importmanager->get_import_progress();
        $date = userdate(
            (int) $this->persistent->get('timemodified'),
            get_string('strftimedatetime', 'langconfig')
        );
        $user = fullname(
            $user,
            has_capability('moodle/site:viewfullnames', \context_system::instance())
        );
        $tenant = \tool_tenant\permission::can_switch_tenant() ? helper::format_tenantid($this->persistent->get('tenantid')) : '';
        $importerstr = helper::format_importer($this->persistent->get('importer'), (object) [
            'entrypoint' => $this->persistent->get('entrypoint'),
            'entrypointid' => $this->persistent->get('entrypointid'),
        ]);
        $importfile = helper::get_import_file($this->persistent->get('id'));
        $size = $importfile ? display_size($importfile->get_filesize()) : '';
        $version = $importmanager->get_review_data()['release'] ?? '';
        $summary = $this->get_summary($output, $importmanager);
        $conflicts = [];
        foreach ($importmanager->get_importers() as $importer) {
            $conflicts += $importer->get_conflicts_review();
        }
        return [
            'statusstr' => $statusstr,
            'iscomplete' => $iscompleted,
            'isinprogress' => $isinprogress,
            'issuccess' => $issuccess,
            'progress' => $progress,
            'date' => $date,
            'user' => $user,
            'tenant' => $tenant,
            'importerstr' => $importerstr,
            'version' => $version,
            'size' => $size,
            'summary' => $summary,
            'conflicts' => $conflicts,
            'hasconflicts' => !empty($conflicts)
        ];
    }
}
