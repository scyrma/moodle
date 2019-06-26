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
 * Plugin callbacks.
 *
 * @package     tool_wp
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get icon mapping for font-awesome.
 */
function tool_wp_get_fontawesome_icon_map() {
    return [
        'tool_wp:archive' => 'fa-archive',
        'tool_wp:restorearchived' => 'fa-repeat',
        'tool_wp:bar-chart' => 'fa-bar-chart',
        'tool_wp:arrow-circle-left' => 'fa-arrow-circle-left',
        'tool_wp:plus-circle' => 'fa-plus-circle',
        'tool_wp:angle-right' => 'fa-angle-right',
        'tool_wp:angle-left' => 'fa-angle-left',
        'tool_wp:exclamation-triangle' => 'fa-exclamation-triangle',
        'tool_wp:paper-plane-o' => 'fa-paper-plane-o',
        'tool_wp:folder-open-o' => 'fa-folder-open-o',
        'tool_wp:calendar-o' => 'fa-calendar-o',
        'tool_wp:check-circle-o' => 'fa-check-circle-o',
        'tool_wp:hourglass-end' => 'fa-hourglass-end',
        'tool_wp:sort-amount-asc' => 'fa-sort-amount-asc',
        'tool_wp:sort-amount-desc' => 'fa-sort-amount-desc',
        'tool_wp:toggle-off' => 'fa-toggle-off',
        'tool_wp:toggle-on' => 'fa-toggle-on',
        'tool_wp:userpreferences' => 'fa-sliders',
        'tool_wp:bookmark' => 'fa-bookmark',
    ];
}
