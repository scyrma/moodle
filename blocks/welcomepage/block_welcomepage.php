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
 * Welcome page block class.
 *
 * @package    block_welcomepage
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_welcomepage extends block_base {
    protected function init() {
        $this->title = get_string('title', 'block_welcomepage');
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

        // Check if block has been dismissed with the "Got it" button.
        $dismissed = (bool)get_config('block_welcomepage', 'dismissed');
        if ($dismissed) {
            return null;
        }

        // Did the user click on the "Got it" button? If so, set dismissed to yes.
        if (isset($_POST['dismissed'])) {
            set_config('dismissed', true, 'block_welcomepage');
        }

        // Start with local content always as a fallback.
        $this->content->text = $this->load_content_locally();

        // If we aren't using local content and we can successfully get the content from S3, use that.
        $uselocalcontent = (bool)get_config('block_welcomepage', 'uselocalcontent');
        if (!$uselocalcontent) {
            $content = $this->load_content_from_s3();
            if ($content !== false) {
                $this->content->text = $content;
            }
        }

        // Generate URL to hide the block.
        $url = $this->page->url->out(
            false,
            array('dismissed' => true),
        );

        // Dismiss button (Got It).
        $button = new single_button(
            new moodle_url($url),
            get_string('dismiss', 'block_welcomepage'),
            'post',
            'secondary'
        );

        // Add the button to the existing HTML template.
        $buttonhtml = html_writer::tag('div', $OUTPUT->render($button));

        $this->content->text = str_replace('<!--button-->', $buttonhtml, $this->content->text);

        // Add the image to the existing HTML template.
        $imageurl = '../blocks/welcomepage/content/welcome.svg'; // If using local content.

        if ($uselocalcontent == false) {
            $image = $this->load_image_from_s3();
            if ($image !== false) {
                $imageurl = $image;
            }
        }

        $imgattrs = array(
            'src' => $imageurl,
            'alt' => get_string('imagedescription', 'block_welcomepage')
        );
        $imagehtml = html_writer::empty_tag('img', $imgattrs);
        $this->content->text = str_replace('<!--image-->', $imagehtml, $this->content->text);

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
            __DIR__ . '/content/welcome.html'
        );
    }

    /**
     * Load block content from Amazon S3 public URL.
     */
    private function load_content_from_s3() {
        $contentbaseurl = get_config('block_welcomepage', 'contentbaseurl');
        return file_get_contents($contentbaseurl . 'welcome.html');
    }

    /**
     * Load block image from Amazon S3 public URL.
     */
    private function load_image_from_s3() {
        $contentbaseurl = get_config('block_welcomepage', 'contentbaseurl');
        return $contentbaseurl . 'welcome.svg';
    }
}
