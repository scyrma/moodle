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
 * File for class program_overview_view.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\output;

defined('MOODLE_INTERNAL') || die();

use context_system;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use tool_certification\api as certificationapi;
use tool_certification\certification_completion;
use tool_certification\certification_user;
use tool_program\api;
use tool_program\external\program_overview_view_exporter;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_program\program_tree_progress;

/**
 * Class program_overview_view
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_progress_overview implements templatable, renderable {
    /**
     * @var stdClass
     */
    protected $user;
    /**
     * @var program
     */
    protected $program;
    /**
     * @var program_tree_progress
     */
    private $programtreeprogress;

    /**
     * edit_program_view constructor.
     *
     * @param stdClass $user
     * @param program $program
     * @param program_tree_progress $programtreeprogress
     */
    public function __construct(stdClass $user, program $program, program_tree_progress $programtreeprogress) {
        $this->user = $user;
        $this->program = $program;
        $this->programtreeprogress = $programtreeprogress;
    }

    /**
     * Implementation of exporter from templatable interface
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $certifications = api::get_certifications_by_userid($this->user->id);
        $certallocations = certification_user::get_records(['userid' => $this->user->id]);
        $userpreferences = get_user_preferences('tool_program_program_status_filter') ?? 'all';
        $programenrolledcourses[$this->program->get('id')] = $this->programtreeprogress->get_user_enrolments();
        $relateddata = [
            'context' => context_system::instance(),
            'user' => $this->user,
            'programs' => [$this->program],
            'programsallocations' => program_user::get_records(['userid' => $this->user->id]),
            'programstreeprogress' => [$this->program->get('id') => $this->programtreeprogress],
            'certifications' => $certifications,
            'certificationscompletion' => $this->get_certifications_completion($certifications),
            'certificationsallocations' => $certallocations,
            'certificationsallocationsstatus' => $this->get_certification_allocations_status($certallocations),
            'programstatusfilterstr' => get_string($userpreferences, 'tool_program'),
            'showfilters' => false,
            'programsenrolledcourses' => $programenrolledcourses,
            'courses' => [],
            'coursesprogress' => [],
            'coursescompletion' => [],
            'coursesmodals' => []
        ];
        $exporter = new program_overview_view_exporter(null, $relateddata);
        return $exporter->export($output);
    }

    /**
     * Get certification allocation status.
     *
     * @param array $allocations
     * @return array
     */
    private function get_certification_allocations_status(array $allocations): array {
        $status = [];
        foreach ($allocations as $allocation) {
            $allocationid = $allocation->get('id');
            $certificationid = $allocation->get('certificationid');
            $statuses = certificationapi::get_user_allocation_status($certificationid, $this->user->id);
            $status[$allocationid] = $statuses[0]['statusint'] ?? -1;
        }
        return $status;
    }

    /**
     * Get certifications completion.
     *
     * @param array $certifications
     * @return array
     */
    private function get_certifications_completion(array $certifications): array {
        $completions = [];
        foreach ($certifications as $certification) {
            $certid = $certification->get('id');
            $params = ['userid' => $this->user->id, 'certificationid' => $certid, 'timerevoked' => 0, 'islast' => 1];
            $completion = certification_completion::get_record($params);
            if ($completion) {
                $completions[$certid] = $completion;
            }
        }
        return $completions;
    }
}
