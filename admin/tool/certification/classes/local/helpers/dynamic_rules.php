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
 * Class dynamic_rules
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\helpers;

use Closure;
use context_system;
use tool_certification\certification;

defined('MOODLE_INTERNAL') || die();

/**
 * Dynamic rules helper
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dynamic_rules {
    /**
     * Returns format fullname callback given a program id.
     *
     * @return Closure
     */
    public static function get_certification_fullname_callback(): Closure {
        return static function($certificationid) {
            $certification = new certification($certificationid);
            $formatparams = ['context' => context_system::instance(), 'escape' => false];
            return format_string($certification->get('fullname'), true, $formatparams);
        };
    }

    /**
     * Returns options array for certification selector.
     *
     * @return array
     */
    public static function get_selector_options(): array {
        return [
            'ajax'     => 'tool_certification/form_potential_certification_selector',
            'multiple' => false,
            'class'    => 'select_certification'
        ];
    }
}