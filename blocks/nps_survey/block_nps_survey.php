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
 * NPS Survey block class.
 *
 * @package    block_nps_survey
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_nps_survey extends block_base {
    protected function init() {
        $this->title = get_config('block_nps_survey', 'surveytitle');
        if (empty($this->title)) {
            $this->title = get_string('surveytitledefault', 'block_nps_survey');
        }
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->instance)) {
            return $this->content;
        }

        $surveytext = get_config('block_nps_survey', 'surveytext');
        if (empty($surveytext)) {
            $surveytext = get_string('surveytextdefault', 'block_nps_survey');
        }

        $surveylink = get_config('block_nps_survey', 'surveylink');
        if (empty($surveylink)) {
            $surveylink = get_string('surveylinkdefault', 'block_nps_survey');
        }

        $this->content->text = '
            <div class="info">' . $surveytext . '</div>
            <br/>
            <a href="' . $surveylink . '" target="_blank">
                <strong>» Provide feedback</strong>
            </a>
        ';

        return $this->content;
    }

    /**
     * Return the plugin config settings for external functions.
     *
     * @return stdClass the configs for both the block instance and plugin
     * @since Moodle 3.8
     */
    public function get_config_for_external() {
        global $CFG;

        // Return all settings for all users since it is safe (no private keys, etc..).
        $configs = (object)[
            'surveylink' => $CFG->block_nps_survey_link
        ];

        return (object)[
            'instance' => new stdClass(),
            'plugin' => $configs,
        ];
    }

    /**
     * Locations where block can be displayed.
     *
     * @return array
     */
    public function applicable_formats() {
        return array('my' => true);
    }
}
