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
 * Nuke document conversions for a given converter.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\common;
defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Class to nuke all conversions carried out by a particular converter.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversion_nuker {

    /**
     * Nuke all conversions for a given plugin.
     *
     * @param string $plugin The name of the file converter plugin to nuke conversion for. e.g., "unoconv".
     */
    public static function nuke_conversions(string $plugin) {
        global $DB;

        $destfileids = array_map(function(stdClass $row) : int {
                return (int)$row->id;
        }, $DB->get_records_sql(
            'SELECT files.id FROM {files} files JOIN {file_conversion} conversions ON files.id = conversions.destfileid AND conversions.converter = ? AND conversions.status = 2',
            ['\fileconverter_' . $plugin . '\converter']
        ));

        if (!$destfileids) {
            return;
        }

        list($compose, $partial) = functions::export('compose', 'partial');
        $gradeids = $compose(
            $partial('array_map', function(stdClass $userandassignid) use ($DB) : int {
                    return (int)$DB->get_record_sql(
                    'SELECT id from {assign_grades} WHERE userid = ? AND assignment = ?',
                    [$userandassignid->userid, $userandassignid->assignment]
                )->id;
            }),
            $partial('array_map', function(int $id) use ($DB) : stdClass {
                return $DB->get_record_sql(
                    'SELECT userid, assignment FROM {assign_submission} WHERE id = ?',
                    [$id]
                );
            }),
            $partial('array_map', function(stdClass $row) : int {
                    return (int)$row->itemid;
            })
        )(
            $DB->get_records_sql(
                'SELECT files.id, files.itemid FROM {files} files JOIN {file_conversion} conversions ON files.id = conversions.sourcefileid AND conversions.converter = ? AND conversions.status = 2',
                ['\fileconverter_' . $plugin . '\converter']
            )
        );

        if (!$gradeids) {
            return;
        }

        list($gradeidinsql, $gradeidinparams) = $DB->get_in_or_equal($gradeids);
        list($fileareainsql, $fileareainparams) = $DB->get_in_or_equal(['pages', 'combined']);

        $extrafileidstodelete = array_map(
            function(stdClass $row) : int {
                return (int)$row->id;
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
        $DB->delete_records('file_conversion', ['converter' => '\fileconverter_' . $plugin . '\converter']);
    }
}
