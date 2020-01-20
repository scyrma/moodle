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
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\external;

use context;
use core\external\exporter;
use renderer_base;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_program\program_tree_progress;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for exporting field data.
 *
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class my_programs_progress_exporter extends exporter {
    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'userid' => 'int',
            'programs' => '\\tool_program\\persistent\\program[]',
            'userallocations' => '\\tool_program\\persistent\\program_user[]',
            'certifications' => '\\tool_certification\\certification[]',
            'usercertifications' => '\\tool_certification\\certification_user[]',
        ];
    }

    /**
     * Return the list of additional properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'programs' => [
                'type' => my_program_progress_exporter::read_properties_definition(),
                'multiple' => true,
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
        /** @var int $userid */
        $userid = $this->related['userid'];
        /** @var program[] $programs */
        $programs = $this->related['programs'];
        /** @var program_user[] $userallocations */
        $userallocations = $this->related['userallocations'];
        /** @var certification[] $certifications */
        $certifications = $this->related['certifications'];
        /** @var certification_user[] $usercertifications */
        $usercertifications = $this->related['usercertifications'];

        $exporteddata = [];
        foreach ($programs as $program) {
            $programitemstree = new program_tree_progress($program, $userid);
            $relatedallocations = $this->fetch_related_program_allocations($userallocations, (int) $program->get('id'));
            $relatedcertifications = $this->fetch_related_certifications($certifications, (int) $program->get('id'));
            $relatedusercertifications = $this->fetch_related_cert_allocations($usercertifications, $relatedcertifications);
            $exporter = new my_program_progress_exporter(null, [
                'context' => $context,
                'programitemstree' => $programitemstree,
                'program' => $program,
                'userallocations' => $relatedallocations,
                'certifications' => $relatedcertifications,
                'usercertifications' => $relatedusercertifications,
            ]);
            $exporteddata[] = $exporter->export($output);
        }

        return [
            'programs' => $exporteddata,
        ];
    }

    /**
     * Fetches allocations related to the passed program id and removes them from the original list.
     *
     * @param program_user[] $userallocations
     * @param int $programid
     * @return program_user[]
     */
    private function fetch_related_program_allocations(&$userallocations, int $programid): array {
        $relatedallocations = [];
        foreach ($userallocations as $key => $allocation) {
            if ($programid === (int) $allocation->get('programid')) {
                $relatedallocations[$allocation->get('id')] = $allocation;
                unset($userallocations[$key]);
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
    private function fetch_related_certifications(&$certifications, int $programid): array {
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
     * Fetches user certifications related to the passed program id and removes them from the original list.
     *
     * @param certification_user[] $usercertifications
     * @param certification[] $relatedcertifications
     * @return certification_user[]
     */
    private function fetch_related_cert_allocations(&$usercertifications, $relatedcertifications): array {
        $certificationids = array_map(function($relatedcertification) {
            return $relatedcertification->get('id');
        }, $relatedcertifications);

        $relatedusercertifications = [];
        foreach ($usercertifications as $key => $usercertification) {
            if (in_array($usercertification->get('certificationid'), $certificationids, true)) {
                $relatedusercertifications[$usercertification->get('id')] = $usercertification;
                unset($usercertifications[$key]);
            }
        }

        return $relatedusercertifications;
    }
}
