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
 * Tool Dynamic Rule webservice functions.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'tool_dynamicrule_enable_rule' => array(
        'classname'    => tool_dynamicrule\external::class,
        'methodname'   => 'enable_rule',
        'description'  => 'Enable the rule',
        'type'         => 'write',
        'capabilities' => 'tool/dynamicrule:manage',
        'ajax'         => true,
    ),
    'tool_dynamicrule_can_enable_rule' => array(
        'classname'    => tool_dynamicrule\external::class,
        'methodname'   => 'can_enable_rule',
        'description'  => 'Returns true if user can enable the rule',
        'type'         => 'write',
        'capabilities' => 'tool/dynamicrule:manage',
        'ajax'         => true,
    ),
    'tool_dynamicrule_disable_rule' => array(
        'classname'    => tool_dynamicrule\external::class,
        'methodname'   => 'disable_rule',
        'description'  => 'Disable the rule',
        'type'         => 'write',
        'capabilities' => 'tool/dynamicrule:manage',
        'ajax'         => true,
    ),
    'tool_dynamicrule_archive_rule' => array(
        'classname'    => tool_dynamicrule\external::class,
        'methodname'   => 'archive_rule',
        'description'  => 'Archive the rule',
        'type'         => 'write',
        'capabilities' => 'tool/dynamicrule:manage',
        'ajax'         => true,
    ),
    'tool_dynamicrule_unarchive_rule' => array(
        'classname'    => tool_dynamicrule\external::class,
        'methodname'   => 'unarchive_rule',
        'description'  => 'Unarchive the rule',
        'type'         => 'write',
        'capabilities' => 'tool/dynamicrule:manage',
        'ajax'         => true,
    ),
    'tool_dynamicrule_delete_rule' => array(
        'classname'    => tool_dynamicrule\external::class,
        'methodname'   => 'delete_rule',
        'description'  => 'Delete the rule',
        'type'         => 'write',
        'capabilities' => 'tool/dynamicrule:manage',
        'ajax'         => true,
    ),
    'tool_dynamicrule_duplicate_rule' => array(
        'classname'    => tool_dynamicrule\external::class,
        'methodname'   => 'duplicate_rule',
        'description'  => 'Duplicate the rule',
        'type'         => 'write',
        'capabilities' => 'tool/dynamicrule:manage',
        'ajax'         => true,
    ),
    'tool_dynamicrule_count_matching_users' => array(
        'classname'    => tool_dynamicrule\external::class,
        'methodname'   => 'count_matching_users',
        'description'  => 'Count matching users to the rule',
        'type'         => 'read',
        'capabilities' => 'tool/dynamicrule:manage',
        'ajax'         => true,
    ),
    'tool_dynamicrule_delete_condition' => array(
        'classname'    => tool_dynamicrule\external::class,
        'methodname'   => 'delete_condition',
        'description'  => 'Delete condition instance',
        'type'         => 'write',
        'capabilities' => 'tool/dynamicrule:manage',
        'ajax'         => true,
    ),
    'tool_dynamicrule_delete_outcome' => array(
        'classname'    => tool_dynamicrule\external::class,
        'methodname'   => 'delete_outcome',
        'description'  => 'Delete outcome instance',
        'type'         => 'write',
        'capabilities' => 'tool/dynamicrule:manage',
        'ajax'         => true,
    ),
    'tool_dynamicrule_potential_badge_selector' => [
        'classname' => tool_dynamicrule\external::class,
        'methodname' => 'potential_badge_selector',
        'description' => 'get list of badges',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_dynamicrule_potential_competency_selector' => [
        'classname' => tool_dynamicrule\external::class,
        'methodname' => 'potential_competency_selector',
        'description' => 'get list of competencies',
        'type' => 'read',
        'ajax' => true,
    ],
);
