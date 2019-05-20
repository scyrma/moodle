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
 * Renderer to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    theme_moodlecloud
 * @copyright  2019 Michael Hawkins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_moodlecloud\output;

use moodle_url, html_writer;

defined('MOODLE_INTERNAL') || die;

/**
 * Renderer extension to include old Clean style outputs within the Classic BS4 templates
 *
 * @package    theme_moodlecloud
 * @copyright  2019 Michael Hawkins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \core_renderer {

    /**
     * Add a footnote to the page.
     *
     * @return string
     */
    public function footnote() {
        $return = '';

        if (!empty($this->page->theme->settings->footnote)) {
            $return = '<div class="footnote text-center">' .format_text($this->page->theme->settings->footnote) . '</div>';
        }

        return $return;
    }

    /**
     * Determine whether the navbar colour is inverted.
     * @return bool
     */
    public function is_navbar_inverted() {
        return !empty($this->page->theme->settings->invert);
    }

    /**
     * Wrapper for header elements. Overridden to handle logo correctly.
     *
     * @return string HTML to display the main header.
     */
    public function full_header() {
        global $PAGE;

        $header = new \stdClass();
        $header->settingsmenu = $this->context_header_settings_menu();
        $header->contextheader = $this->context_header();
        $header->hasnavbar = empty($PAGE->layout_options['nonavbar']);
        $header->navbar = $this->navbar();
        $header->pageheadingbutton = $this->page_heading_button();
        $header->courseheader = $this->course_header();
        $header->needslogo = $this->needs_logo();
        return $this->render_from_template('core/full_header', $header);
    }

    /**
     * Determine whether to display the logo, if one is defined.
     *
     * @return bool
     */
    private function needs_logo() {
        $needed = false;

        if (!empty($this->page->theme->settings->logo) &&
                ($this->page->pagelayout == 'frontpage' || $this->page->pagelayout == 'login')) {
            $needed = true;
        }

        return $needed;
    }

    /**
     * Create the HTML for page footer links.
     */
    public function get_footerlinks() {
        $links = [];

        if (($doclink = $this->page_doc_link())) {
            $links[] = $doclink;
        }

        // Add support link for teachers.
        if (has_capability('moodle/site:doclinks', $this->page->context)) {
            $title = get_string('supportforums', 'theme_moodlecloud');
            $link = new moodle_url('https://moodle.org/community');
            $links[] = html_writer::link($link, $title, array('target' => '_blank'));
        }

        if (is_siteadmin()) {
            $title = get_string('faq', 'theme_moodlecloud');
            $link = new moodle_url('https://moodle.com/cloud/faq');
            $links[] = html_writer::link($link, $title, array('target' => '_blank'));
        }

        return implode(' | ', $links);
    }

    /**
     * Prepare the MoodleCloud portal link button for site owners.
     *
     * @return string HTML for the portal link button, or empty string.
     */
    public function portal_link() {
        global $USER, $CFG;

        if (isset($USER->auth) && $USER->auth === 'moodlecloud') {
            $url = new moodle_url('/auth/moodlecloud/portal.php');
            $title = get_string('cloudportallink', 'theme_moodlecloud');
            $alt = get_string('cloudlogo', 'theme_moodlecloud');
            $text = get_string('yourportal', 'theme_moodlecloud');
            $theme = \theme_config::load('moodlecloud');
            $imageurl = $theme->image_url('moodlecloud-logo-inverted', 'theme');
            $imghtml = html_writer::img($imageurl, $alt);
            $linkhtml = html_writer::link($url->out(), sprintf("%s %s", $imghtml, $text),
                array('id' => 'portal-link', 'title' => $title, 'target' => '_blank'));

            return html_writer::div($linkhtml, '', array('id' => 'portal-link-container'));
        } else {
            return '';
        }
    }

    /**
     * Prepare Google Analytics template for page.
     *
     * @return string
     */
    public function get_gatc() {
        // We need the global and region property as well as the plan to output.
        if ((defined('MOODLECLOUD_GA_GLOBAL_PROPERTY') && MOODLECLOUD_GA_GLOBAL_PROPERTY) &&
            (defined('MOODLECLOUD_GA_REGION_PROPERTY') && MOODLECLOUD_GA_REGION_PROPERTY) &&
            (defined('MOODLECLOUD_PLAN') && MOODLECLOUD_PLAN)
        ) {
            return $this->render_from_template('theme_moodlecloud/google_analytics', array(
                'ga_global_property' => MOODLECLOUD_GA_GLOBAL_PROPERTY,
                'ga_region_property' => MOODLECLOUD_GA_REGION_PROPERTY,
                'ga_plan' => MOODLECLOUD_PLAN
            ));
        }
    }
}
