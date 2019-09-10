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
 * Class for exporting program course data.
 *
 * @package    tool_program
 * @copyright  2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\external;

use context;
use core\external\exporter;
use renderer_base;
use stdClass;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_program\constants;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_program\program_tree_progress;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for exporting field data.
 *
 * @copyright  2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_overview_view_exporter extends exporter {
    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'user' => 'stdClass',
            'programs' => '\\tool_program\\persistent\\program[]',
            'programstreeprogress' => '\\tool_program\\program_tree_progress[]',
            'programsallocations' => '\\tool_program\\persistent\\program_user[]',
            'certifications' => '\\tool_certification\\certification[]',
            'certificationscompletion' => '\\tool_certification\\certification_completion[]',
            'certificationsallocations' => '\\tool_certification\\certification_user[]',
            'certificationsallocationsstatus' => 'int[]',
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'id' => [
                'type' => PARAM_INT,
            ],
            'programs' => [
                'type' => PARAM_RAW,
            ],
            'programscount' => [
                'type' => PARAM_TEXT,
            ],
            'viewasmode' => [
                'type' => PARAM_BOOL,
            ],
        ];
    }

    /**
     * Other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        global $USER;

        /** @var context $context */
        $context = $this->related['context'];
        /** @var stdClass $user */
        $user = $this->related['user'];
        /** @var int $userid */
        $userid = (int) $user->id;
        /** @var program[] $programs */
        $programs = $this->related['programs'];
        /** @var program_tree_progress[] $programstreeprogress */
        $programstreeprogress = $this->related['programstreeprogress'];
        /** @var program_user[] $programsallocations */
        $programsallocations = $this->related['programsallocations'];
        /** @var certification[] $certifications */
        $certifications = $this->related['certifications'];
        /** @var array $certificationscompletion */
        $certificationscompletion = $this->related['certificationscompletion'];
        /** @var certification_user[] $certificationsallocations */
        $certificationsallocations = $this->related['certificationsallocations'];
        /** @var array $certificationsallocationsstatus */
        $certificationsallocationsstatus = $this->related['certificationsallocationsstatus'];

        $exportedprograms = [];
        foreach ($programs as $program) {
            // Fetch data specifically related to this program.
            $programid = (int) $program->get('id');
            $relatedprogramallocations = $this->fetch_related_program_allocations($programsallocations, $programid);
            $relatedcertifications = $this->fetch_program_certifications($certifications, $programid);
            $relatedcertallocations = $this->fetch_related_cert_allocations($certificationsallocations, $relatedcertifications);

            $relateddata = [
                'context' => $context,
                'user' => $user,
                'program' => $program,
                'programtreeprogress' => $programstreeprogress[$programid],
                'programallocations' => $relatedprogramallocations,
                'certifications' => $relatedcertifications,
                'certificationscompletion' => $certificationscompletion,
                'certificationallocations' => $relatedcertallocations,
                'certificationsallocationsstatus' => $certificationsallocationsstatus,
            ];
            $exporter = new program_overview_program_exporter(null, $relateddata);

            $exportedprograms[] = $exporter->export($output);
        }

        // Order programs by due date < due date NOT SET < completed.
        if ($exportedprograms) {
            $sortcompletion = [];
            $sortduedate = [];
            foreach ($exportedprograms as $key => $row) {
                $sortcompletion[$key] = $row->programiscompleted ?: 0;
                $sortduedate[$key] = $this->get_smaller_duedate_timestamp($row->origin) ?: constants::MAX_DATE;
            }
            array_multisort($sortcompletion, SORT_ASC, $sortduedate, SORT_ASC, $exportedprograms);
        }

        return [
            'id' => $userid,
            'programs' => array_values($exportedprograms),
            'programscount' => (string) count($exportedprograms),
            'viewasmode' => $userid !== (int) $USER->id,
        ];
    }

    /**
     * Fetches allocations related to the passed program id and removes them from the original list.
     *
     * @param program_user[] $allocations
     * @param int $programid
     * @return program_user[]
     */
    private function fetch_related_program_allocations(&$allocations, int $programid): array {
        $relatedallocations = [];
        foreach ($allocations as $key => $allocation) {
            if ($programid === (int) $allocation->get('programid')) {
                $relatedallocations[$allocation->get('id')] = $allocation;
                unset($allocations[$key]);
            }
        }

        return $relatedallocations;
    }

    /**
     * Fetches certifications related to the passed program id and removes them from the original list.
     *
     * @param certification[] $certifications
     * @param int $programid
     * @return certification[]
     */
    private function fetch_program_certifications(&$certifications, int $programid): array {
        $relatedcertifications = [];
        foreach ($certifications as $key => $certification) {
            if ($programid === (int) $certification->get('program')) {
                $relatedcertifications[$certification->get('id')] = $certification;
                unset($certifications[$key]);
            }
        }

        return $relatedcertifications;
    }

    /**
     * Fetches certification allocations related to the passed program allocations and removes them from the original list.
     *
     * @param certification_user[] $usercertifications
     * @param certification[] $relatedcertifications
     * @return certification_user[]
     */
    private function fetch_related_cert_allocations(&$usercertifications, $relatedcertifications): array {
        $certificationids = array_map(static function($relatedcertification) {
            return $relatedcertification->get('id');
        }, $relatedcertifications);

        $programcertificationusers = [];
        foreach ($usercertifications as $key => $usercertification) {
            if (in_array($usercertification->get('certificationid'), $certificationids, true)) {
                $programcertificationusers[$usercertification->get('id')] = $usercertification;
                unset($usercertifications[$key]);
            }
        }

        return $programcertificationusers;
    }

    /**
     * Returns the smaller duedate timestamp given an allocations array with due date timestamps.
     *
     * @param stdClass[] $allocations
     * @return int|null Return null if due date was not set (timestamp equals zero)
     */
    private function get_smaller_duedate_timestamp(array $allocations): ?int {
        $duedates = array_map(static function($allocation) {
            return $allocation->duedatetimestamp;
        }, $allocations);

        $duedates = array_filter($duedates, static function($duedate) {
            return $duedate > 0;
        });

        return !empty($duedates) ? min($duedates) : null;
    }
}
