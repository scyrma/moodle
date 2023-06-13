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

namespace tool_program\external;

use coding_exception;
use context;
use core\external\exporter;
use renderer_base;
use stdClass;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_program\constants;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_program\program_item;
use tool_program\program_tree_progress;

/**
 * Class for exporting field data.
 *
 * @deprecated since 4.1
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class my_program_progress_exporter extends exporter {
    /**
     * Constructor - saves the persistent object, and the related objects.
     *
     * @param mixed $data - Either an stdClass or an array of values.
     * @param array $related - An optional list of pre-loaded objects related to this object.
     */
    public function __construct($data, $related = array()) {
        debugging('This class is deprecated', DEBUG_DEVELOPER);
        parent::__construct($data, $related);
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'programitemstree' => program_tree_progress::class,
            'program' => program::class,
            'userallocations' => '\\tool_program\\persistent\\program_user[]',
            'usercertifications' => '\\tool_certification\\certification_user[]',
            'certifications' => '\\tool_certification\\certification[]',
            'lastcourseaccess' => 'stdClass[]?',
            'programenrolledcourses' => 'stdClass[]?',
        ];
    }

    /**
     * Return the list of additional properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return program::properties_definition() + [
                'completeditems' => [
                    'type' => PARAM_INT,
                ],
                'completionatleast' => [
                    'type' => PARAM_INT,
                ],
                'completiontype' => [
                    'type' => PARAM_TEXT,
                ],
                'name' => [
                    'type' => PARAM_TEXT,
                ],
                'iscompleted' => [
                    'type' => PARAM_BOOL,
                ],
                'progress' => [
                    'type' => PARAM_FLOAT,
                ],
                'showprogress' => [
                    'type' => PARAM_BOOL,
                ],
                'totalitems' => [
                    'type' => PARAM_INT,
                ],
                'unlocked' => [
                    'type' => PARAM_BOOL,
                ],
                'allocationstartdate' => [
                    'type' => PARAM_INT,
                    'null' => NULL_ALLOWED,
                ],
                'allocationenddate' => [
                    'type' => PARAM_INT,
                    'null' => NULL_ALLOWED,
                ],
                'calltoaction' => [
                    'type' => PARAM_TEXT,
                    'null' => NULL_ALLOWED,
                ],
                'ongoingcourseid' => [
                    'type' => PARAM_INT,
                    'null' => NULL_ALLOWED,
                ],
                'image' => [
                    'type' => file_exporter::read_properties_definition(),
                    'multiple' => true,
                ],
                'descriptionfiles' => [
                    'type' => file_exporter::read_properties_definition(),
                    'multiple' => true,
                ],
                'certifications' => [
                    'type' => program_certification_exporter::read_properties_definition(),
                    'multiple' => true,
                ],
                'allocations' => [
                    'type' => program_user_exporter::read_properties_definition(),
                    'multiple' => true,
                ],
                'programcourses' => [
                    'type' => program_course_progress_exporter::read_properties_definition(),
                    'multiple' => true,
                ],
                'programsets' => [
                    'type' => program_set_progress_exporter::read_properties_definition(),
                    'multiple' => true,
                ],
                'lastaccess' => [
                    'type' => PARAM_INT
                ],
            ];
    }

    /**
     * Get other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        /** @var context $context */
        $context = $this->related['context'];
        /** @var program $program */
        $program = $this->related['program'];
        /** @var program_user[] $userallocations */
        $userallocations = $this->related['userallocations'];
        /** @var program_tree_progress $programitemstree */
        $programitemstree = $this->related['programitemstree'];
        /** @var certification_user[] $usercertifications */
        $usercertifications = $this->related['usercertifications'];
        /** @var certification[] $certifications */
        $certifications = $this->related['certifications'];
        /** @var array $programenrolledcourses */
        $programenrolledcourses = $this->related['programenrolledcourses'];

        $exporter = new program_exporter($program, ['context' => $context]);
        $exporteddata = $exporter->export($output);
        $exporteddata->name = $exporteddata->fullname;
        $this->export_program_images($output, $program, $exporteddata, $context);

        $exporteddata->programcourses = [];
        $exporteddata->programsets = [];
        $this->export_progress_and_completion_data($output, $programitemstree, $exporteddata, $context);

        $exporteddata->certifications = [];
        $exporteddata->allocations = [];
        $this->export_certifications_and_program_allocation_data($output, $userallocations, $certifications,
            $context, $exporteddata);

        $this->export_allocation_window_absolute_dates($program, $exporteddata);

        $exporteddata->calltoaction = null;
        $exporteddata->ongoingcourseid = null;
        $this->export_program_call_to_action_data($programitemstree, $exporteddata);

        $exporteddata->lastaccess = $this->export_program_lastaccess($programenrolledcourses) ?? 0;

        return (array) $exporteddata;
    }

    /**
     * Get completion type string.
     *
     * @param program_item $set
     * @return string
     */
    private function get_completion_type(program_item $set): string {
        switch ($set->get_completion_criteria()) {
            case program_set::COMPLETION_ALL_IN_ANY_ORDER:
                return 'completeallinanyorder';
            case program_set::COMPLETION_ALL_IN_ORDER:
                return 'completeallinorder';
            case program_set::COMPLETION_AT_LEAST:
                return 'completeatleast';
            default:
                throw new coding_exception('Unexpected program set completion criteria');
        }
    }

    /**
     * Export completion data for the program as taken from the base set calculations.
     *
     * @param stdClass $exportedprogram
     * @param program_item $baseset
     */
    private function export_program_completion_data(stdClass $exportedprogram, program_item $baseset): void {
        $exportedprogram->completiontype = $this->get_completion_type($baseset);
        $exportedprogram->completionatleast = $baseset->get_completion_atleast();
        $exportedprogram->totalitems = $baseset->totalitems;
        $exportedprogram->completeditems = $baseset->completeditems;
        $exportedprogram->progress = $baseset->progresspercentage;
        $exportedprogram->totalactivities = $baseset->totalitems;
        $exportedprogram->completedactivities = $baseset->completeditems;
        $exportedprogram->unlocked = $baseset->isunlocked;
        if ($baseset->iscompleted) {
            $exportedprogram->iscompleted = true;
            $exportedprogram->showprogress = false;
            $exportedprogram->progress = 100;
        } else {
            $exportedprogram->iscompleted = false;
            $exportedprogram->showprogress = true;
            $exportedprogram->progress = $baseset->progresspercentage;
        }
    }

    /**
     * Export progress and completion data.
     *
     * @param renderer_base $output
     * @param program_tree_progress $programitemstree
     * @param stdClass $exporteddata
     * @param context $context
     */
    private function export_progress_and_completion_data(renderer_base $output, program_tree_progress $programitemstree,
        stdClass $exporteddata, context $context): void {
        $programitemslist = $programitemstree->to_list();
        foreach ($programitemslist as $programitem) {
            if ($programitem->is_base_set()) {
                $this->export_program_completion_data($exporteddata, $programitem);
            } else if ($programitem->is_set()) {
                $exporter = new program_set_progress_exporter($programitem->get_persistent(), [
                    'context' => $context,
                    'programitemset' => $programitem,
                ]);
                $exportedset = $exporter->export($output);
                unset($exportedset->items);
                $exporteddata->programsets[] = $exportedset;
            } else if ($programitem->is_course()) {
                $exporter = new program_course_progress_exporter(null, [
                    'context' => $context,
                    'programitemcourse' => $programitem,
                ]);
                $exportedcourse = $exporter->export($output);
                unset($exportedcourse->items);
                $exporteddata->programcourses[] = $exportedcourse;
            }
        }
    }

    /**
     * Export certifications and program allocation data.
     *
     * @param renderer_base $output
     * @param array $userallocations
     * @param array $certifications
     * @param context $context
     * @param stdClass $exporteddata
     */
    private function export_certifications_and_program_allocation_data(renderer_base $output, array $userallocations,
        array $certifications, context $context, stdClass $exporteddata): void {
        foreach ($userallocations as $allocation) {
            $certificationid = $allocation->get('certificationid');
            if (0 !== (int) $certificationid) {
                $relatedcertification = $certifications[$certificationid];
                $exporter = new program_certification_exporter(null, [
                    'context' => $context,
                    'certification' => $relatedcertification,
                    'programuser' => $allocation
                ]);
                $exporteddata->certifications[] = $exporter->export($output);
            }

            $exporter = new program_user_exporter($allocation, ['context' => $context]);
            $exporteddata->allocations[] = $exporter->export($output);
        }
    }

    /**
     * Export program image file and embedded program description files (if any exists).
     *
     * @param renderer_base $output
     * @param program $program
     * @param stdClass $exporteddata
     * @param context $context
     */
    private function export_program_images(renderer_base $output, program $program, stdClass $exporteddata,
        context $context): void {

        // Export program image data.
        $exporteddata->image = [];
        if ($file = $program->get_image()) {
            $exporter = new file_exporter(null, ['context' => $context, 'file' => $file]);
            $exporteddata->image[] = $exporter->export($output);
        }

        // Export data of files embedded in the program description.
        $exporteddata->descriptionfiles = [];
        foreach ($program->get_embedded_description_files() as $file) {
            $exporter = new file_exporter(null, ['context' => $context, 'file' => $file]);
            $exporteddata->descriptionfiles[] = $exporter->export($output);
        }
    }

    /**
     * Calculates and exports data related to the main button/call to action of the program in the programs overview.
     *
     * @param program_tree_progress $programitemstree
     * @param stdClass $exporteddata
     */
    private function export_program_call_to_action_data(program_tree_progress $programitemstree, stdClass $exporteddata): void {
        $baseset = $programitemstree->get_baseset();
        if ($baseset->iscompleted) {
            $exporteddata->calltoaction = 'completed';
            $exporteddata->ongoingcourseid = null;
        } else {
            $exporteddata->calltoaction = $baseset->progresspercentage <= 0 ? 'start' : 'continue';
            $exporteddata->ongoingcourseid = $programitemstree->get_ongoing_courseid();
        }
    }

    /**
     * Exports program dates in absolute form so the mobile app has to do no calculations.
     *
     * @param program $program
     * @param stdClass $exporteddata
     */
    private function export_allocation_window_absolute_dates(program $program, stdClass $exporteddata): void {
        $startdatetype = (int) $program->get('allocationstartdatetype');
        switch ($startdatetype) {
            case constants::DATE_NONE:
                $startdate = null;
                break;
            case constants::DATE_ABSOLUTE:
                $startdate = (int) $program->get('allocationstartdateabsolute');
                break;
            default:
                throw new coding_exception('Unexpected allocation window start date type.');
        }

        $enddatetype = (int) $program->get('allocationenddatetype');
        switch ($enddatetype) {
            case constants::DATE_NONE:
                $enddate = null;
                break;
            case constants::DATE_ABSOLUTE:
                $enddate = (int) $program->get('allocationenddateabsolute');
                break;
            case constants::DATE_AFTER_ALLOCATION_STARTS:
                if ($startdatetype === constants::DATE_NONE) {
                    $enddate = null;
                } else {
                    $enddaterelative = $program->get('allocationenddaterelative');
                    $enddate = strtotime('+' . $enddaterelative, $startdate);
                }
                break;
            default:
                throw new coding_exception('Unexpected allocation window end date type.');
        }

        $exporteddata->allocationstartdate = $startdate;
        $exporteddata->allocationenddate = $enddate;
    }

    /**
     * Return the timestamp when the program courses were last accessed
     *
     * @param array|null $programcourses
     * @return int|null
     */
    private function export_program_lastaccess(?array $programcourses): ?int {
        $res = null;
        foreach ($programcourses as $programcourse) {
            $courseid = $programcourse->courseid;
            $lastaccess = $this->related['lastcourseaccess'][$courseid]->timeaccess ?? null;
            if ($lastaccess !== null && ($res === null || $res < $lastaccess)) {
                $res = $lastaccess;
            }
        }
        return $res;
    }
}
