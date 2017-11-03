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
 * A scheduled task for clearing document conversions.
 *
 * @package   fileconverter_cloudconvert
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace fileconverter_dummy;
defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_moodlecloud\common\functions;

/**
 * Document conversion cleanup task.
 *
 * Once a file has been converted with our dummy converter, it will
 * not ever be reconverted as long as the file remains in the files
 * table.
 *
 * This becomes a problem if a site owner enables the Google converter
 * and gives it higher priority than the MoodleCloud one - in this
 * case old conversions will continue to show the upsell PDF, so we
 * need to clear them out to trigger a reconversion.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document_conversions_cleanup_task extends scheduled_task {

    public function get_name() : string {
        return get_string('conversioncleanup', 'fileconverter_dummy');
    }

    public function execute() {
        global $CFG;

        $currentsortorder = explode(',', $CFG->converter_plugins_sortorder);
        $lastseensortorder = isset(get_config('fileconverter_dummy')->lastseensortorder)
                                 ? explode(',', get_config('fileconverter_dummy')->lastseensortorder)
                                 : null;

        set_config('lastseensortorder', $CFG->converter_plugins_sortorder, 'fileconverter_dummy');

        // If we have never persisted the sortorder in our own table, we can't make
        // any assumptions about what has happened, so nuke everything to be safe.
        if (!$lastseensortorder) {
            $this->nuke_conversions();
            return;
        }

        // If the dummy plugin has been given lower priority, nuke conversions.
        // Note: Lower priority means higher index in the array.
        if (array_search('dummy', $lastseensortorder) < array_search('dummy', $currentsortorder)) {
            $this->nuke_conversions();
            return;
        }
    }

    private function nuke_conversions() {
        global $DB;

        $destfileids = array_map(function($row) {
            return $row->id;
        }, $DB->get_records_sql(
            'SELECT files.id FROM {files} files JOIN {file_conversion} conversions ON files.id = conversions.destfileid AND conversions.converter = ? AND conversions.status = 2',
            ['\fileconverter_dummy\converter']
        ));

        if (!$destfileids) {
            return;
        }

        list($compose, $partial) = functions::export('compose', 'partial');
        $gradeids = $compose(
            $partial('array_map', function($userandassignid) use ($DB) {
                return $DB->get_record_sql(
                    'SELECT id from {assign_grades} WHERE userid = ? AND assignment = ?',
                    [$userandassignid->userid, $userandassignid->assignment]
                )->id;
            }),
            $partial('array_map', function($id) use ($DB) {
                return $DB->get_record_sql(
                    'SELECT userid, assignment FROM {assign_submission} WHERE id = ?',
                    [$id]
                );
            }),
            $partial('array_map', function($row) {
                return $row->itemid;
            })
        )(
            $DB->get_records_sql(
                'SELECT files.itemid FROM {files} files JOIN {file_conversion} conversions ON files.id = conversions.sourcefileid AND conversions.converter = ? AND conversions.status = 2',
                ['\fileconverter_dummy\converter']
            )
        );

        list($gradeidinsql, $gradeidinparams) = $DB->get_in_or_equal($gradeids);
        list($fileareainsql, $fileareainparams) = $DB->get_in_or_equal(['pages', 'combined']);

        $extrafileidstodelete = array_map(
            function($row) {
                return $row->id;
            },
            $DB->get_records_sql(
                "SELECT id FROM {files} WHERE itemid $gradeidinsql AND filearea $fileareainsql AND component = ?",
                array_merge(
                    $gradeidinparams,
                    $fileareainparams,
                    ['assignfeedback_editpdf']
                )
            )
        );

        $DB->delete_records_list('files', 'id', array_merge($destfileids, $extrafileidstodelete));
        $DB->delete_records('file_conversion', ['converter' => '\fileconverter_dummy\converter']);
    }
}
