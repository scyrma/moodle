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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Main renderer
 *
 * @package    format_wplist
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Basic renderer for wplist format.
 *
 * @package    format_wplist
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class format_wplist_renderer extends format_section_renderer_base {

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Generate the starting container html for a wplist of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return '';
    }

    /**
     * Generate the closing container html for a wplist of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return '';
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the content to displayed on the right part of a section
     * before course modules are included
     *
     * @param stdClass|section_info $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return string HTML to output.
     */
    protected function edit_section($section, $course, $onsectionpage) {
        $template = new stdClass();

        $template->addcm = $this->course_section_add_cm_control($course, $section->section, 0);

        $controls = $this->section_edit_control_items($course, $section, $onsectionpage);
        $template->sectionmenu = $this->section_edit_control_menu($controls, $course, $section);

        return $this->render_from_template('format_wplist/editsection', $template);
    }

    /**
     * Generate the edit control action menu
     *
     * @param array $controls The edit control items from section_edit_control_items
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @return string HTML to output.
     */
    protected function section_edit_control_menu($controls, $course, $section) {
        $o = "";
        if (!empty($controls)) {
            $menu = new action_menu();
            $menu->set_menu_trigger($this->output->pix_icon('i/settings', '', 'core'));
            $menu->attributes['class'] .= ' section-actions';
            foreach ($controls as $value) {
                $url = empty($value['url']) ? '' : $value['url'];
                $icon = empty($value['icon']) ? '' : $value['icon'];
                $name = empty($value['name']) ? '' : $value['name'];
                $attr = empty($value['attr']) ? array() : $value['attr'];
                $class = empty($value['pixattr']['class']) ? '' : $value['pixattr']['class'];
                $alt = empty($value['pixattr']['alt']) ? '' : $value['pixattr']['alt'];
                $al = new action_menu_link_secondary(
                    new moodle_url($url),
                    new pix_icon($icon, $alt, null, array('class' => "smallicon " . $class)),
                    $name,
                    $attr
                );
                $menu->add($al);
            }

            $o .= html_writer::div($this->render($menu), 'section_action_menu',
                array('data-sectionid' => $section->id, 'title' => get_string('edit', 'moodle')));
        }

        return $o;
    }

    /**
     * Renders HTML for the menus to add activities and resources to the current course
     *
     * @param stdClass $course
     * @param int $section relative section number (field course_sections.section)
     * @param int $sectionreturn The section to link back to
     * @param array $displayoptions additional display options, for example blocks add
     *     option 'inblock' => true, suggesting to display controls vertically
     * @return string
     */
    private function course_section_add_cm_control($course, $section, $sectionreturn = null, $displayoptions = array()) {
        if ($course->id == $this->page->course->id) {
            $straddeither = get_string('addresourceoractivity');
            $ajaxcontrol = html_writer::start_tag('div', ['class' => 'mdl-right']);
            $ajaxcontrol .= html_writer::start_tag('div', ['class' => 'section-modchooser']);
            $icon = $this->output->pix_icon('plus-circle', $straddeither, 'tool_wp');
            $ajaxcontrol .= html_writer::tag('button', $icon, [
                    'class' => 'section-modchooser-link btn btn-link pt-0',
                    'data-action' => 'open-chooser',
                    'data-sectionid' => $section,
                ]
            );
            $ajaxcontrol .= html_writer::end_tag('div');
            $ajaxcontrol .= html_writer::end_tag('div');

            $this->courserenderer->course_activitychooser($course->id);
        }
        return $ajaxcontrol ?? '';
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        $template = new stdClass();
        $template->courseid = $course->id;
        $template->editing = $this->page->user_is_editing();
        $template->editsettingsurl = new moodle_url('/course/edit.php', ['id' => $course->id]);
        $template->enrolusersurl = new moodle_url('/user/index.php', ['id' => $course->id]);
        $template->incourse = true;

        if ($template->editing) {
            $template->editoff = new moodle_url($this->page->url, ['sesskey' => sesskey(), 'edit' => 'off']);
        } else {
            $template->editon = new moodle_url($this->page->url, ['sesskey' => sesskey(), 'edit' => 'on']);
        }

        /** @var format_wplist $courseformat */
        $courseformat = course_get_format($course);
        $course = $courseformat->get_course();
        $options = $courseformat->get_format_options();

        $template->sections = [];

        $modinfo = get_fast_modinfo($course);

        $template->accordion = $options['accordioneffect'];

        $context = context_course::instance($course->id);

        $opensections = [];
        $preferences = get_user_preferences('format_wplist_opensections_' . $context->id);
        if ($preferences) {
            $opensections = json_decode($preferences, true);
        } else {
            set_user_preference('format_wplist_opensections_' . $context->id, '[]');
        }

        $template->contextid = $context->id;

        $completioninfo = new completion_info($course);

        $template->courseactivityclipboard = $this->course_activity_clipboard($course, 0);
        $template->pagetitle = $this->page_title();
        $template->hasclosedsections = false;

        $numsections = $courseformat->get_last_section_number();

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {

            $sectiontemp = (object)$thissection;
            $sectiontemp->sectionnumber = $section;
            if ($section == 0) {
                $sectiontemp->expandbtn = true;
            }
            if ($template->editing) {
                if ($section > 0) {
                    $sectiontemp->move = true;
                    $sectiontemp->movetitle = get_string('movesection', 'moodle', $section);
                } else {
                    $sectiontemp->moveplaceholder = true;
                }
                $sectiontemp->editsection = $this->edit_section($thissection, $course, false);
            } else {
                // Show the section if the user is permitted to access it, OR if it's not available
                // but there is some available info text which explains the reason & should display,
                // OR it is hidden but the course has a setting to display hidden sections as unavilable.
                $showsection = $thissection->uservisible ||
                    ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo)) ||
                    (!$thissection->visible && !$course->hiddensections);
                if (!$showsection) {
                    continue;
                }
            }
            if ($section > $numsections) {
                $sectiontemp->mutedsection = true;
                if (!$template->editing) {
                    continue;
                }
            }
            $sectiontemp->availabilitymsg = $this->section_availability($thissection);
            $sectiontemp->completion = $this->course_section_completion($course, $completioninfo, $section);
            $sectiontemp->sectionname = $courseformat->get_section_name($thissection);
            $sectiontemp->name = $this->section_title($thissection, $course);
            $sectiontemp->summary = $this->format_summary_text($thissection);
            $sectiontemp->expanded = false;
            if (in_array($thissection->id, $opensections)) {
                $sectiontemp->expanded = true;
            }
            if ($template->editing) {
                $sectiontemp->expanded = true;
            }
            if (!$thissection->uservisible) {
                $sectiontemp->expanded = false;
                $sectiontemp->disableexpanding = true;
            }
            if ($sectiontemp->expanded == true) {
                $sectiontemp->toggletitle = get_string('collapsesection', 'format_wplist', $sectiontemp->sectionname);
            } else {
                $template->hasclosedsections = true;
                $sectiontemp->toggletitle = get_string('expandsection', 'format_wplist', $sectiontemp->sectionname);
            }
            $sectiontemp->coursemodules = $this->course_section_cm_wplist($course, $thissection, 0);

            $template->sections[] = $sectiontemp;
        }

        if ($template->editing and has_capability('moodle/course:update', $context)) {
            $template->addsection = $this->wplist_change_number_sections($course, 0);
        }

        echo $this->render_from_template('format_wplist/multisectionpage', $template);
    }

    /**
     * Displays availability information for the section (hidden, not available unles, etc.)
     *
     * @param section_info $section
     * @return string
     */
    public function section_availability($section) {
        $context = context_course::instance($section->course);
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context);
        $message = $this->section_availability_message($section, $canviewhidden);
        return $message ? html_writer::div($message) : null;
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page
     *
     * @param stdClass|section_info $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Renders HTML to display a wplist of course modules in a course section
     *
     * This function calls {@link core_course_renderer::course_section_cm_wplist_item()}
     *
     * @param stdClass $course course object
     * @param int|stdClass|section_info $section relative section number or section object
     * @param int $sectionreturn section number to return to
     * @param int $displayoptions
     * @return string
     */
    public function course_section_cm_wplist($course, $section, $sectionreturn = null, $displayoptions = array()) {
        $template = new stdClass();

        $template->section = $section->section;

        $modinfo = get_fast_modinfo($course);
        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }
        $completioninfo = new completion_info($course);

        // Check if we are currently in the process of moving a module with JavaScript disabled.
        $template->editing = $this->page->user_is_editing();
        $template->ismoving = $template->editing && ismoving($course->id);

        $template->modules = [];
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                if (!$mod->is_visible_on_course_page()) {
                    continue;
                }
                $template->modules[] = $this->course_section_cm_wplist_item($course,
                    $completioninfo, $mod, $sectionreturn, $displayoptions);
            }
        } else {
            $template->nomodules = true;
        }
        return $this->render_from_template('format_wplist/coursemodules', $template);
    }

    /**
     * Renders HTML to display one course module for display within a section.
     *
     * This function calls:
     * {@link core_course_renderer::course_section_cm()}
     *
     * @param stdClass $course
     * @param completion_info $completioninfo
     * @param cm_info $mod
     * @param int|null $sectionreturn
     * @param array $displayoptions
     * @return String
     */
    public function course_section_cm_wplist_item($course, &$completioninfo, cm_info $mod, $sectionreturn,
        $displayoptions = array()) {

        $template = new stdClass();
        $template->mod = $mod;
        $template->text = $mod->get_formatted_content(array('overflowdiv' => false, 'noclean' => true));
        $template->completion = $this->course_section_cm_completion($course, $completioninfo, $mod, $displayoptions);
        $template->cmname = $this->courserenderer->course_section_cm_name($mod, $displayoptions);
        $template->editing = $this->page->user_is_editing();
        $template->availability = $this->courserenderer->course_section_cm_availability($mod, $displayoptions);

        if ($template->editing) {
            $editactions = course_get_cm_edit_actions($mod, $mod->indent, $sectionreturn);
            $template->editoptions = $this->course_section_cm_edit_actions($editactions, $mod, $displayoptions);
            $template->editoptions .= $mod->afterediticons;
            $template->moveicons = $this->course_get_cm_move($mod, $sectionreturn);
        }

        return $this->render_from_template('format_wplist/coursemodule', $template);
    }

    /**
     * Render the course module move icon.
     *
     * @param  cm_info $mod
     * @param  int $sr Section return ID
     * @return String HTML to be returned
     */
    public function course_get_cm_move(cm_info $mod, $sr = null) {
        $template = new stdClass();

        $modcontext = context_module::instance($mod->id);
        $hasmanageactivities = has_capability('moodle/course:manageactivities', $modcontext);

        $template->movetitle = get_string('movecoursemodule', 'moodle');

        if ($hasmanageactivities) {
            return $this->render_from_template('format_wplist/movecoursemodule', $template);
        }
        return '';
    }

    /**
     * calculates and renders the section completion progressbar
     *
     * @param stdClass $course
     * @param completion_info $completioninfo
     * @param int $section Section number
     */
    private function course_section_completion($course, &$completioninfo, $section) {
        $template = new stdClass();

        $template->sectionnumber = $section;

        $completionok = array(COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS);
        $completionfail = array(COMPLETION_COMPLETE_FAIL, COMPLETION_INCOMPLETE);
        $output = '';
        $modinfo = get_fast_modinfo($course);
        if ($completioninfo === null) {
            $completioninfo = new completion_info($course);
        }

        $completionmodcount = 0;
        $completedmodscount = 0;
        if (!isset($modinfo->sections[$section])) {
            return '';
        }
        foreach ($modinfo->sections[$section] as $modnumber) {
            $mod = $modinfo->cms[$modnumber];
            $completion = $completioninfo->is_enabled($mod);
            if ($completion == COMPLETION_TRACKING_NONE) {
                continue;
            }
            $completionmodcount++;
            $completiondata = $completioninfo->get_data($mod, true);
            if (in_array($completiondata->completionstate, $completionok)) {
                $completedmodscount++;
            }
        }

        if ($completionmodcount > 0) {
            $template->hascompletion = true;
            $template->percentagecompleted = round(100 * ($completedmodscount / $completionmodcount));
            $template->completionmodcount = $completionmodcount;
            $template->completedmodscount = $completedmodscount;
        }
        return $this->render_from_template('format_wplist/sectioncompletion', $template);
    }

    /**
     * Renders html for completion box on course page
     *
     * If completion is disabled, returns empty string
     * If completion is automatic, returns an icon of the current completion state
     * If completion is manual, returns a form (with an icon inside) that allows user to
     * toggle completion
     *
     * @param stdClass $course course object
     * @param completion_info $completioninfo completion info for the course, it is recommended
     *     to fetch once for all modules in course/section for performance
     * @param cm_info $mod module to show completion for
     * @param array $displayoptions display options, not used in core
     * @return string
     */
    public function course_section_cm_completion($course, &$completioninfo, cm_info $mod, $displayoptions = array()) {
        global $CFG, $USER;

        if (!empty($displayoptions['hidecompletion']) || !isloggedin() || isguestuser() || !$mod->uservisible) {
            return "";
        }
        if ($completioninfo === null) {
            $completioninfo = new completion_info($course);
        }
        $completion = $completioninfo->is_enabled($mod);

        if ($completion == COMPLETION_TRACKING_NONE) {
            return "";
        }

        $isediting = $this->page->user_is_editing();
        $istrackeduser = $completioninfo->is_tracked_user($USER->id);

        $completionicon = '';

        $completiondata = $completioninfo->get_data($mod, true);
        if ($isediting) {
            switch ($completion) {
                case COMPLETION_TRACKING_MANUAL :
                    $completionicon = 'manual-enabled';
                    break;
                case COMPLETION_TRACKING_AUTOMATIC :
                    $completionicon = 'auto-enabled';
                    break;
            }
        } else {
            if ($completion == COMPLETION_TRACKING_MANUAL) {
                switch($completiondata->completionstate) {
                    case COMPLETION_INCOMPLETE:
                        $completionicon = 'manual-n' . ($completiondata->overrideby ? '-override' : '');
                        break;
                    case COMPLETION_COMPLETE:
                        $completionicon = 'manual-y' . ($completiondata->overrideby ? '-override' : '');
                        break;
                }
            } else {
                switch($completiondata->completionstate) {
                    case COMPLETION_INCOMPLETE:
                        $completionicon = 'auto-n' . ($completiondata->overrideby ? '-override' : '');
                        break;
                    case COMPLETION_COMPLETE:
                        $completionicon = 'auto-y' . ($completiondata->overrideby ? '-override' : '');
                        break;
                    case COMPLETION_COMPLETE_PASS:
                        $completionicon = 'auto-pass';
                        break;
                    case COMPLETION_COMPLETE_FAIL:
                        $completionicon = 'auto-fail';
                        break;
                }
            }
        }
        $template = new stdClass();
        $template->sectionnumber = $mod->get_section_info()->section;
        $template->mod = $mod;
        $template->completionicon = $completionicon;
        $template->courseid = $course->id;

        if ($completionicon) {
            $formattedname = $mod->get_formatted_name(['escape' => false]);
            $template->hascompletion = true;

            if ($isediting) {
                $template->editing = true;
            }

            if (\core_availability\info::completion_value_used($course, $mod->id)) {
                $template->reloadonchange = true;
            }

            if (!$isediting && $istrackeduser && $completiondata->overrideby) {
                $args = new stdClass();
                $args->modname = $formattedname;
                $overridebyuser = \core_user::get_user($completiondata->overrideby, '*', MUST_EXIST);
                $args->overrideuser = fullname($overridebyuser);
                $imgalt = get_string('completion-alt-' . $completionicon, 'completion', $args);
            } else {
                $imgalt = get_string('completion-alt-' . $completionicon, 'completion', $formattedname);
            }
            $template->imgalt = $imgalt;

            if ($completion == COMPLETION_TRACKING_MANUAL) {
                $template->self = true;
                $template->newstate =
                    $completiondata->completionstate == COMPLETION_COMPLETE
                    ? COMPLETION_INCOMPLETE
                    : COMPLETION_COMPLETE;

                if ($completiondata->completionstate == COMPLETION_COMPLETE) {
                    $template->checked = true;
                }
            } else {
                $template->auto = true;
                if ($completionicon == 'auto-y' || $completionicon == 'auto-pass') {
                    $template->checked = true;
                }
            }
        }
        return $this->render_from_template('format_wplist/completionicon', $template);
    }

    /**
     * Renders HTML for displaying the sequence of course module editing buttons
     *
     * @see course_get_cm_edit_actions()
     *
     * @param action_link[] $actions Array of action_link objects
     * @param cm_info $mod The module we are displaying actions for.
     * @param array $displayoptions additional display options:
     *     ownerselector => A JS/CSS selector that can be used to find an cm node.
     *         If specified the owning node will be given the class 'action-menu-shown' when the action
     *         menu is being displayed.
     *     constraintselector => A JS/CSS selector that can be used to find the parent node for which to constrain
     *         the action menu to when it is being displayed.
     *     donotenhance => If set to true the action menu that gets displayed won't be enhanced by JS.
     * @return string
     */
    public function course_section_cm_edit_actions($actions, cm_info $mod = null, $displayoptions = array()) {
        global $CFG;

        if (empty($actions)) {
            return '';
        }

        $template = new stdClass();
        $template->controls = [];

        foreach ($actions as $action) {
            if ($action instanceof action_menu_link) {
                $action->add_class('cm-edit-action');
            }
        }

        foreach ($actions as $key => $action) {
            if ($key === 'moveright' || $key === 'moveleft' ) {
                continue;
            }
            if (empty($action->url)) {
                continue;
            }

            $control = new stdClass();
            $control->icon = $action->icon;
            $control->attributes = '';
            if (is_array($action->attributes)) {
                foreach ($action->attributes as $name => $value) {
                    $control->attributes .= s($name) . '="' . s($value) . '"';
                }
            }
            $control->url = $action->url->out(false);
            $control->string = $action->text;
            $template->controls[] = $control;
        }

        return $this->render_from_template('format_wplist/editactivity', $template);
    }

    /**
     * Returns controls in the bottom of the page to increase/decrease number of sections
     *
     * @param stdClass $course
     * @param int|null $sectionreturn
     * @return string
     */
    private function wplist_change_number_sections($course, $sectionreturn = null) {
        $coursecontext = context_course::instance($course->id);
        if (!has_capability('moodle/course:update', $coursecontext)) {
            return '';
        }

        $format = course_get_format($course);
        $maxsections = $format->get_max_sections();
        $lastsection = $format->get_last_section_number();

        if ($lastsection >= $maxsections) {
            return '';
        }

        $template = new stdClass();
        $url = new moodle_url('/course/changenumsections.php',
            ['courseid' => $course->id, 'insertsection' => 0, 'sesskey' => sesskey()]);

        if ($sectionreturn !== null) {
            $url->param('sectionreturn', $sectionreturn);
        }
        $template->url = $url->out(false);
        $template->attributes = [['name' => 'new-sections', 'value' => $maxsections - $lastsection]];

        return $this->render_from_template('format_wplist/change_number_sections', $template);
    }
}
