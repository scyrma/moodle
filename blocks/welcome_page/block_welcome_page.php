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
 * @package    block_welcome_page
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_welcome_page extends block_base {
    protected function init() {
        $this->title = get_config('block_welcome_page', 'pluginname');
    }

    public function instance_can_be_edited() {
        return false;
    }

    public function get_content() {

        // Load block content from public assets on S3.
        $this->content = $this->load_content_from_s3();
        if (!$this->content || $this->content !== null) {
            // TODO: check this error handling.
            // What if the content doesn't load?
            // just hide the block?
            return $this->content;
        }

        if (empty($this->instance)) {
            return $this->content;
        }

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
            //'contentlocation' => $CFG->block_welcome_page_content
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

    private function load_content_from_s3() {
        // TODO: load configuration from block and do this dynamically.

        // TODO: needs some error/exception handling.
        return file_get_contents(
            'https://assets.gl.moodlecloud.com/welcome/block.html'
        );
    }
}
