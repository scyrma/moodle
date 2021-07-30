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
 * Class dynamic_rules
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\local\helpers;

use Closure;
use context_system;
use tool_dynamicrule\rule;
use tool_program\persistent\program;
use tool_tenant\hierarchy;

defined('MOODLE_INTERNAL') || die();

/**
 * Dynamic rules helper
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class dynamic_rules {
    /**
     * Returns format fullname callback given a program id.
     *
     * @return Closure
     */
    public static function get_program_fullname_callback(): Closure {
        return static function($programid) {
            $program = new \tool_program\persistent\program($programid);
            $formatparams = ['context' => context_system::instance(), 'escape' => false];
            return format_string($program->get('fullname'), true, $formatparams);
        };
    }

    /**
     * Returns a program that exists, not archived and either belongs to the same tenant as the rule or is shared in a parent tenant
     *
     * @param int $programid
     * @param rule $rule
     * @return program
     */
    public static function get_program_if_valid(?int $programid, rule $rule): ?program {
        if (!$programid) {
            return null;
        }
        [$select, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
            $rule->get('tenantid'));
        $select .= ' AND archived=:archived AND id=:id';
        $params += [
            'id' => $programid,
            'archived' => 0
        ];
        $records = program::get_records_select($select, $params);
        return $records ? reset($records) : null;
    }

    /**
     * Returns options array for program selector.
     *
     * @return array
     */
    public static function get_selector_options(): array {
        return [
            'ajax'     => 'tool_program/form_potential_program_selector',
            'multiple' => false,
            'class'    => 'select_program_field'
        ];
    }
}
