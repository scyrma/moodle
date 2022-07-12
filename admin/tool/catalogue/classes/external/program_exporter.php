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

declare(strict_types=1);

namespace tool_catalogue\external;

use core\external\persistent_exporter;
use core_tag_tag;
use renderer_base;
use tool_catalogue\constants;
use tool_catalogue\manager;
use tool_certification\constants as certificationconstants;
use tool_program\customfield\program_handler;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_program\program_tree_progress;
use tool_program\api;

/**
 * Program exporter class
 *
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_exporter extends persistent_exporter {

    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class(): string {
        return program::class;
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'allocations' => program_user::class . '[]',
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'image' => ['type' => PARAM_URL],
            'programstructure' => ['type' => program_content_exporter::read_properties_definition()],
            'basesetcriteria' => ['type' => PARAM_TEXT],
            'progress' => ['type' => PARAM_INT],
            'certifications' => [
                'type' => [
                    'message' => ['type' => PARAM_TEXT],
                    'type' => ['type' => PARAM_TEXT],
                    'hash' => ['type' => PARAM_ALPHANUM],
                ],
                'optional' => true,
                'multiple' => true,
            ],
            'hascertifications' => ['type' => PARAM_BOOL],
            'startdate' => ['type' => PARAM_TEXT],
            'startdatestr' => ['type' => PARAM_TEXT],
            'duedate' => ['type' => PARAM_INT],
            'duedatestr' => ['type' => PARAM_TEXT],
            'duedatebadgetype' => ['type' => PARAM_TEXT],
            'duedatebadgestr' => ['type' => PARAM_TEXT],
            'enddate' => ['type' => PARAM_TEXT],
            'enddatestr' => ['type' => PARAM_TEXT],
            'numcourses' => ['type' => PARAM_INT],
            'lastaccess' => ['type' => PARAM_INT],
            'customfields' => ['type' => PARAM_RAW],
            'hastags' => ['type' => PARAM_BOOL],
            'tags' => ['type' => PARAM_RAW],
        ];
    }

    /**
     * Other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        global $USER, $CFG;
        require_once($CFG->libdir . '/filelib.php');

        /** @var program $program */
        $program = $this->persistent;
        $userid = (int) $USER->id;
        $context = $this->related['context'];
        $data = (object)[];

        // Fetch the program structure with all related data for the current user.
        // We pass all course objects at once to avoid retrieving individual courses inside the exporters.
        $treeprogress = new program_tree_progress($program, $userid);
        $exporter = new program_content_exporter(null, [
            'context' => $context,
            'treeprogress' => $treeprogress,
            'courses' => $program->get_courses(),
        ]);
        $programstructure = $exporter->export($output);
        $data->programstructure = $programstructure->baseset;
        $data->basesetcriteria = $data->programstructure->setcriteriastr;
        $data->progress = $data->programstructure->progress;

        // TODO check when next exporter is done how to refactor this.
        $data->certifications = $this->get_certification_messages($userid, $this->related['allocations']);
        $data->hascertifications = !empty($data->certifications);

        $data->image = $program->get_image_url();
        if (!$data->image) {
            $data->image = api::get_program_pattern($program->get('id'));
        }
        $data->numcourses = $program->get_courses_count();
        $data->lastaccess = $this->get_lastaccessed($program, $userid);
        $data->customfields = $this->export_program_customfields();
        $data->tags = $this->export_program_tags();
        $data->hastags = !empty($data->tags);

        // Retrieve program allocation dates. // TODO review if we need to export all variants.
        [$data->startdate, $data->startdatestr] = $this->get_program_date(constants::STARTDATE);
        [$data->duedate, $data->duedatestr] = $this->get_program_date(constants::DUEDATE);
        [$data->enddate, $data->enddatestr] = $this->get_program_date(constants::ENDDATE);
        [$data->duedatebadgetype, $data->duedatebadgestr] = $this->get_duedate_badge($data->duedate);

        return (array)$data;
    }

    /**
     * Returns all certification allocations related messages
     *
     * @param int $userid
     * @param array $allocations
     * @return array
     */
    private function get_certification_messages(int $userid, array $allocations): array {
        $messages = [];
        $dateformat = get_string('strftimedatefullshort', 'langconfig');

        $certifications = manager::get_certifications_by_userid($userid);
        $allocationstatus = manager::get_certification_allocations_status($allocations, $userid);
        $certificationcompletions = manager::get_certifications_completion($certifications, $userid);

        foreach ($allocations as $allocation) {

            // Skip if this is a direct allocation.
            if ($allocation->get('certificationid') === 0) {
                continue;
            }

            // Do not show information about suspended certification allocations.
            // TODO what about suspended + certified/expired?
            if ($allocationstatus[$allocation->get('id')] === certificationconstants::STATUS_OVERRIDE_SUSPENDED) {
                continue;
            }

            // Do not show information about future certification allocations.
            if ($allocationstatus[$allocation->get('id')] === certificationconstants::STATUS_FUTUREALLOCATION) {
                continue;
            }

            $name = $certifications[$allocation->get('certificationid')]->get_formatted_name();
            switch ($allocationstatus[$allocation->get('id')]) {
                case certificationconstants::STATUS_EXPIRED:
                    $completion = $certificationcompletions[$allocation->get('certificationid')];
                    $a = ['name' => $name, 'date' => userdate($completion->get('expirydate'), $dateformat)];
                    $messages[] = $this->build_message('certificationstatusexpired', $a, 'danger', $allocation->get('id'));
                    break;
                case certificationconstants::STATUS_CERTIFIED:
                    $completion = $certificationcompletions[$allocation->get('certificationid')];
                    $expirydate = $completion->get('expirydate');
                    if ($expirydate === 0) {
                        $messages[] = $this->build_message('certificationstatuscertified', ['name' => $name], 'success',
                            $allocation->get('id'));
                    } else {
                        $a = ['name' => $name, 'date' => userdate($expirydate, $dateformat)];
                        $messages[] = $this->build_message('certificationstatuscertifiedwithdate', $a, 'success',
                            $allocation->get('id'));
                    }
                    break;
                case certificationconstants::STATUS_OPEN:
                    if ($allocation->get('duedate') === 0) {
                        $messages[] = $this->build_message('certificationstatusopen', ['name' => $name], 'info',
                            $allocation->get('id'));
                    } else {
                        $a = ['name' => $name, 'date' => userdate($allocation->get('duedate'), $dateformat)];
                        $messages[] = $this->build_message('certificationstatusopenwithdate', $a, 'info', $allocation->get('id'));
                    }
                    break;
                case certificationconstants::STATUS_OVERDUE:
                    $a = ['name' => $name, 'date' => userdate($allocation->get('duedate'), $dateformat)];
                    $messages[] = $this->build_message('certificationstatusoverdue', $a, 'warning', $allocation->get('id'));
                    break;
            }
        }

        return $messages;
    }

    /**
     * Build message structure
     *
     * @param string $stringid
     * @param array $params
     * @param string $type
     * @param int $allocationid
     * @return array
     */
    private function build_message(string $stringid, array $params, string $type, int $allocationid): array {
        return [
            'message' => get_string($stringid, 'tool_catalogue', $params),
            'type' => $type,
            'hash' => md5($stringid . $allocationid),
        ];
    }

    // TODO: Place in tool_program API?
    // TODO: Add ul.timeaccessdesc in 'admin/tool/program/classes/api.php:3799'.
    /**
     * Gets the max timeaccess data for courses within the program.
     *
     * @param program $program
     * @param int $userid the user id.
     * @return int timeaccess timestamp.
     */
    public function get_lastaccessed(program $program, int $userid): int {
        global $DB;
        if ($coursesids = $program->get_courses_ids()) {
            [$sql, $params] = $DB->get_in_or_equal($coursesids, SQL_PARAMS_NAMED, 'id');
            $params['userid'] = $userid;
            $where = 'WHERE c.id ' . $sql;
            $query = "
                   SELECT MAX(timeaccess) AS timeaccess
                     FROM {course} c
                LEFT JOIN {user_lastaccess} ul
                       ON (ul.courseid = c.id AND ul.userid = :userid)
                          $where
            ";
            $timeaccess = $DB->get_record_sql($query, $params);
            if ($timeaccess) {
                return (int)$timeaccess->timeaccess;
            }
        }
        return 0;
    }

    /**
     * Returns the lowest allocation date for the program (startdate/duedate/enddate)
     *
     * @param string $datetype startdate/duedate/enddate
     * @return array
     */
    private function get_program_date(string $datetype): array {
        $date = 0;
        foreach ($this->related['allocations'] as $allocation) {
            if ($allocation->get($datetype)) {
                if ($datetype === 'startdate' || $datetype === 'duedate') {
                    // If date is still 0 then we need to use MAX_DATE to get the minimum value.
                    $date = min($date ?: constants::MAX_DATE, (int)$allocation->get($datetype));
                } else {
                    $date = max($date, (int)$allocation->get($datetype));
                }
            }
        }

        if ($date === 0) {
            $date = constants::MAX_DATE; // Needed for ordering.
            $datestr = get_string('notset', 'tool_catalogue');
        } else {
            $datestr = userdate($date, get_string('strftimedatefullshort', 'langconfig'));
        }

        return [$date, $datestr];
    }

    /**
     * Gets the due date string ready to be exported
     *
     * @param int $duedate duedate timestamp or 0 if no due date
     * @return array type / formatted due date
     */
    private function get_duedate_badge(int $duedate): array {
        $duelimit = get_config('tool_catalogue', 'programdisplayduelimit');
        $now = time();

        if ($duedate === 0 || $duelimit < 0 || $duedate > ($now + $duelimit * DAYSECS)) {
            return ['', ''];
        }

        $days = floor(($duedate - $now) / DAYSECS);
        if ($days < 0) {
            return ['danger', get_string('overdue', 'tool_catalogue')];
        }
        if ($days <= 1) {
            return ['warning', get_string('duedateinfo', 'tool_catalogue')];
        }

        return ['warning', get_string('duedateinfodays', 'tool_catalogue', $days)];
    }

    /**
     * Export program customfields
     *
     * @return array
     */
    private function export_program_customfields(): array {
        global $PAGE;

        $exportedcustomfields = [];
        $handler = program_handler::create();
        $output = $PAGE->get_renderer('core_customfield');

        $customfields = $handler->export_instance_data($this->persistent->get('id'));
        foreach ($customfields as $customfield) {
            $exportedcustomfields[] = $customfield->export_for_template($output);
        }

        return $exportedcustomfields;
    }

    /**
     * Exports program tags and related certifications tags
     *
     * @return array
     */
    private function export_program_tags(): array {
        $tags = core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $this->persistent->get('id'));

        foreach ($this->related['allocations'] as $allocation) {
            if ($allocation->get('certificationid') === 0) {
                continue;
            }

            $certificationtags = core_tag_tag::get_item_tags_array('tool_certification', 'tool_certification',
                $allocation->get('certificationid'));
            $tags = array_merge($tags, $certificationtags);
        }

        return array_values(array_unique($tags));
    }

    /**
     * External format parameters for the description property
     *
     * @return array
     */
    protected function get_format_parameters_for_description(): array {
        return [
            'component' => 'tool_program',
            'filearea' => 'program_description',
            'itemid' => $this->persistent->get('id'),
        ];
    }
}
