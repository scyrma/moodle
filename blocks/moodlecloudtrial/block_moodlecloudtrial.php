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
 * MoodleCloud Trial block class.
 *
 * @package    block_moodlecloudtrial
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_moodlecloudtrial extends block_base {

    protected function init() {
        $this->title = get_string('title', 'block_moodlecloudtrial');
    }

    public function instance_can_be_edited() {
        return true;
    }

    /**
     * Load the block content. Note this is HTML and images as
     * stored in the content folder. This is usually loaded from S3,
     * but falls back to this folder if there is an issue. The file styles.css
     * can be used to further style the content where needed.
     *
     * @return stdClass
     * @throws dml_exception
     */
    public function get_content() {
        global $OUTPUT, $USER;

        $this->content = new stdClass();

        // Only for the primary site administrator.
        // Presumed to be a site admin and have a User ID of 2.
        if (!is_siteadmin() || $USER->id != 2) {
            return null;
        }

        if (!$this->instance->visible) {
            return null;
        }

        if (!$this->is_free_trial()) {
            return null;
        }

        // Start with local content always as a fallback.
        $this->content->text = $this->load_content_locally();

        // If we aren't using local content and we can successfully get the content from S3, use that.
        $uselocalcontent = (bool)get_config('block_moodlecloudtrial', 'uselocalcontent');
        if (!$uselocalcontent) {
            $content = $this->load_content_from_s3();
            if ($content !== false) {
                $this->content->text = $content;
            }
        }

        // Show days left on trial.
        $daysleft = $this->calculate_days_left_on_trial();
        $this->content->text = str_replace('<!--daysleft-->', $daysleft, $this->content->text);

        // Generate URL to the customer portal (upgrade plan tab).
        global $dynamicsite, $clusterconfig;
        $url = "https://" . $clusterconfig['primarydomain'] . "/auth/moodlecloud/portal.php?gotoupgradetab=1";

        // Upgrade button and link to customer portal.
        $button = new single_button(
            new moodle_url($url),
            get_string('upgrade', 'block_moodlecloudtrial'),
            'get',
            'primary',
            ['class' => 'moodlecloudtrial-upgrade-button']
        );

        // Add the button to the existing HTML template.
        $buttonhtml = html_writer::tag('div', $OUTPUT->render($button), ['class' => 'moodlecloud-trial-button']);

        $this->content->text = str_replace('<!--button-->', $buttonhtml, $this->content->text);

        return $this->content;
    }

    /**
     * Locations where block can be displayed.
     *
     * @return array
     */
    public function applicable_formats() {
        return array('all' => true);
    }

    /**
     * Don't show the block title.
     *
     * @return boolean
     */
    public function hide_header() {
        if ($this->instance->visible) {
            // Show the header when not visible to identify the block when hidden.
            return true;
        } else {
            return false;
        }

    }

    /**
     * Indicate that this block has configuration settings.
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Load block content locally.
     */
    private function load_content_locally(): bool|string {
        return file_get_contents(
            __DIR__ . '/content/trial.html'
        );
    }

    /**
     * Load block content from Amazon S3 public URL.
     */
    private function load_content_from_s3() {
        $contentbaseurl = get_config('block_moodlecloudtrial', 'contentbaseurl');
        return file_get_contents($contentbaseurl . 'trial.html');
    }

    private function calculate_days_left_on_trial(): float|int {
        global $clusterconfig;

        $now = new \DateTime();
        $trialStart = new \DateTime("@" . $clusterconfig['trialstart']);
        $trialEnd = $trialStart->add(new \DateInterval('P' . $clusterconfig['trialduration'] . 'D'));

        $interval = $now->diff($trialEnd);
        return abs($interval->format('%a'));
    }

    private function is_free_trial() : bool {
        global $USER;

        return defined('MOODLECLOUD_TRIAL_START') &&
               defined('MOODLECLOUD_TRIAL_DURATION') &&
               is_siteadmin($USER);
    }
}
