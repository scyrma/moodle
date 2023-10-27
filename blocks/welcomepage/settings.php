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
 * Welcome page block settings.
 *
 * @package    block_welcomepage
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Base URL on S3 to get content.
    $settings->add(
        new admin_setting_configtext(
            'block_welcomepage/contentbaseurl',
            get_string('contentbaseurl', 'block_welcomepage'),
            get_string('contentbaseurlexplanation', 'block_welcomepage'),
            get_string('contentbaseurldefault', 'block_welcomepage'), PARAM_TEXT)
    );

    // Whether to use local content (in the content) folder. Useful for testing.
    $settings->add(
        new admin_setting_configcheckbox(
            'block_welcomepage/uselocalcontent',
            get_string('uselocalcontent', 'block_welcomepage'),
            get_string('uselocalcontentexplanation', 'block_welcomepage'),
            get_string('uselocalcontentdefault', 'block_welcomepage'))
    );

    // Has the admin user dismissed the block?
    $settings->add(
        new admin_setting_configcheckbox(
            'block_welcomepage/dismissed',
            get_string('dismissed', 'block_welcomepage'),
            get_string('dismissedexplanation', 'block_welcomepage'),
            get_string('dismisseddefault', 'block_welcomepage'))
    );
}
