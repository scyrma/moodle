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

namespace tool_wp\reportbuilder\local\exportimport;

use core_reportbuilder\local\audiences\base;
use core_reportbuilder\reportbuilder\audience\manual;
use tool_reportbuilder\tool_reportbuilder\audiences\cohortmember;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * Implementations of audience export-import mapping for the audience types defined outside of Workplace
 *
 * @package   tool_wp
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience_mapping_helper {

    /**
     * Add audience config values mapping during export
     *
     * For audiences defined in Workplace that implement interface {@see audience_with_mapping} it calls method
     * from this interface.
     *
     * For audiences defined in core (such as cohortmember or manual) it calls
     * our own method.
     *
     * @param base $audience
     * @param exporter_base $exporter
     */
    public static function add_exporter_mapping(base $audience, exporter_base $exporter) {
        if ($audience instanceof audience_with_mapping) {
            $audience->add_exporter_mapping($exporter);
        } else if ($audience instanceof cohortmember) {
            self::cohortmember_add_exporter_mapping($audience, $exporter);
        } else if ($audience instanceof manual) {
            self::manual_add_exporter_mapping($audience, $exporter);
        }
    }

    /**
     * Substitute audiences config values with the mapped values during import
     *
     * For audiences defined in Workplace that implement interface {@see audience_with_mapping} it calls method
     * from this interface.
     *
     * For audiences defined in core (such as cohortmember or manual) it calls
     * our own method.
     *
     * @param base $audience
     * @param importer_base $importer
     */
    public static function get_importer_mapping(base $audience, importer_base $importer): void {
        if ($audience instanceof audience_with_mapping) {
            $audience->get_importer_mapping($importer);
        } else if ($audience instanceof cohortmember) {
            self::cohortmember_get_importer_mapping($audience, $importer);
        } else if ($audience instanceof manual) {
            self::manual_get_importer_mapping($audience, $importer);
        }
    }

    /**
     * Add mapping for cohort ids
     *
     * @param cohortmember $audience
     * @param exporter_base $exporter
     */
    protected static function cohortmember_add_exporter_mapping(cohortmember $audience, exporter_base $exporter) {
        $cohortids = $audience->get_configdata()['cohorts'];
        foreach ($cohortids as $cohortid) {
            $exporter->add_mapping('cohort', $cohortid);
        }
    }

    /**
     * Get cohort ids mappings during import
     *
     * @param cohortmember $audience
     * @param importer_base $importer
     */
    protected static function cohortmember_get_importer_mapping(cohortmember $audience, importer_base $importer): void {
        $config = $audience->get_configdata();
        if (!empty($config['cohorts'])) {
            $config['cohorts'] = array_map(static function(int $cohortid) use ($importer): int {
                return $importer->get_mapping('cohort', $cohortid, IGNORE_MISSING) ?? -1;
            }, $config['cohorts']);
            $audience->update_configdata($config);
        }
    }

    /**
     * Add mappings for userids
     *
     * @param manual $audience
     * @param exporter_base $exporter
     */
    protected static function manual_add_exporter_mapping(manual $audience, exporter_base $exporter): void {
        $userids = $audience->get_configdata()['users'];
        foreach ($userids as $userid) {
            $exporter->add_mapping('user', $userid);
        }
    }

    /**
     * Get user ids mappings during import
     *
     * @param manual $audience
     * @param importer_base $importer
     * @return void
     */
    protected static function manual_get_importer_mapping(manual $audience, importer_base $importer): void {
        $config = $audience->get_configdata();
        if (!empty($config['users'])) {
            $config['users'] = array_map(static function(int $userid) use ($importer): int {
                return $importer->get_mapping('user', $userid, IGNORE_MISSING) ?? -1;
            }, $config['users']);
            $audience->update_configdata($config);
        }
    }
}
