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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Callbacks for plugin tool_program
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use block_myteams\userinfo_section;
use block_myteams\userinfo_section_item;
use core\output\inplace_editable;
use core_user\output\myprofile\category;
use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_program\program_tree_progress;
use tool_tenant\tenancy;
use tool_organisation\organisation;

/**
 * Serve the embedded files.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param context $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return void|false the file not found, just send the file otherwise and do not return anything
 */
function tool_program_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if (CONTEXT_SYSTEM !== (int) $context->contextlevel) {
        return;
    }

    $allowedfileareas = [
        'program_description',
        'program_image'
    ];
    if (!in_array($filearea, $allowedfileareas, true)) {
        return;
    }

    require_login();

    $itemid = array_shift($args);
    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'tool_program', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return;
    }
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Get icon mapping for font-awesome.
 */
function tool_program_get_fontawesome_icon_map() {
    return [
        'tool_program:t/circle' => 'fa-circle',
    ];
}

/**
 * Fragment to render program progress overview html.
 *
 * @param array $args
 * @return string
 */
function tool_program_output_fragment_program_overview(array $args) {
    $context = $args['context'];
    if (CONTEXT_SYSTEM !== (int) $context->contextlevel) {
        throw new moodle_exception('restrictedcontextexception', 'error');
    }

    $allocationid = $args['allocationid'];
    $programuser = new program_user($allocationid);
    $user = $programuser->get_user();
    $program = $programuser->get_program();
    permission::require_can_view_user_programs_progress($user->id, $program);

    global $PAGE;

    $renderable = new \tool_catalogue\output\program_cover_modal($program, $user->id);
    $output = $PAGE->get_renderer('tool_catalogue');

    return $output->render($renderable);
}

/**
 * Inplace editable functionality
 *
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 * @return inplace_editable
 */
function tool_program_inplace_editable(string $itemtype, int $itemid, string $newvalue) {
    $context = context_system::instance();
    external_api::validate_context($context);
    $newvalue = clean_param($newvalue, PARAM_TEXT);

    switch ($itemtype) {
        case 'programname':
            $program = new program($itemid);
            permission::require_can_edit_details($program);
            $program->set('fullname', $newvalue);
            $program->update();
            $edithint = get_string('editprogramname', 'tool_program');
            $url = new moodle_url('/admin/tool/program/edit.php', ['id' => $itemid]);
            $displayvalue = format_string($newvalue);
            $editlabel = get_string('newvaluefor', 'form', $displayvalue);
            $displayvalue = html_writer::link($url, $displayvalue);
            // Update program name in all events for this program.
            api::update_program_name_in_calendar_events($itemid, $newvalue);
            break;
        case 'setname':
            $programset = new program_set($itemid);
            $program = $programset->get_program();
            permission::require_can_edit_details($program);
            $programset->set('name', $newvalue);
            $programset->update();
            $itemid = $programset->get('id');
            $edithint = get_string('editsetname', 'tool_program');
            $displayvalue = format_string($newvalue);
            $editlabel = get_string('newnameforset', 'tool_program', $displayvalue);
            break;
        default:
            throw new coding_exception('Unexpected tool_program inplace editable item type');
    }

    return new inplace_editable('tool_program', $itemtype, $itemid, true, $displayvalue, $newvalue, $edithint, $editlabel);
}

/**
 * Callback for the tool_wp_potential_users_selector web service
 *
 * @param string $area
 * @param int $itemid
 * @return array
 */
function tool_program_potential_users_selector($area, $itemid) {
    if ($area !== 'allocate' || !$itemid) {
        return null;
    }

    $program = new program($itemid);
    permission::require_can_allocate_anybody($program);

    $join = '';
    $where = tenancy::get_users_subquery(false, false, 'u.id');
    $params = [];

    // Exclude users already allocated to the program.
    $join .= ' LEFT JOIN {' . program_user::TABLE . '} pru' .
        ' ON pru.userid = u.id AND programid = :programid AND allocationtype = :programallocation';
    $where .= ' AND pru.id IS NULL';
    $params['programid'] = $itemid;
    $params['programallocation'] = constants::ALLOCATION_MANUAL;

    if (class_exists('\\tool_organisation\\organisation')) {
        // Check if only can manage his own users and show only these users.
        if (!permission::has_allocateuser_capability($program->get_context()) &&
                permission::can_allocate_anybody_as_organisation_manager()) {
            $user = organisation::get_user_with_jobs();
            [$where0, $params0] = $user->get_managed_users_select('u', organisation::PERM_ALLOCATE_PROGRAMS);
            $paramsmerged = array_merge($params0, $params);
            return [$join, $where0 . ' AND ' . $where, $paramsmerged];
        }
    }

    return [$join, $where, $params];
}

/**
 * Extends user "my profile" navigation nodes.
 *
 * @param tree $tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass $course
 */
function tool_program_myprofile_navigation(tree $tree, $user, $iscurrentuser, $course) {
    if (!array_key_exists('learning', $tree->__get('categories'))) {
        // Create the category.
        $learningstr = get_string('coursesadmintab', 'tool_wp');
        $category = new category('learning', $learningstr, null);
        $tree->add_category($category);
    } else {
        // Get the existing category.
        $category = $tree->__get('categories')['learning'];
    }

    $canviewreports = permission::can_view_user_programs_progress($user->id);

    if ($canviewreports) {
        // Display active programs.
        $programs = api::get_programs_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        if (!empty($programs)) {
            $urlreport = new moodle_url('/admin/tool/program/programsprogress.php',
                ['userid' => $user->id, 'type' => constants::STATUS_OPEN]);
            $strparams = [
                'href' => $urlreport->out(),
                'count' => count($programs),
            ];
            $node = new node('learning', 'activeprograms', '', null, null,
                get_string('activeprogramslink', 'tool_program', $strparams));
            $category->add_node($node);
        }

        // Display overdue programs.
        $programs = api::get_programs_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        if (!empty($programs)) {
            $urlreport = new moodle_url('/admin/tool/program/programsprogress.php',
                ['userid' => $user->id, 'type' => constants::STATUS_OVERDUE]);
            $strparams = [
                'href' => $urlreport->out(),
                'count' => count($programs),
            ];
            $node = new node('learning', 'overdueprograms', '', null, null,
                get_string('overdueprogramslink', 'tool_program', $strparams));
            $category->add_node($node);
        }

        // Display completed programs.
        $programs = api::get_programs_by_status_and_userid(constants::STATUS_COMPLETED, $user->id);
        if (!empty($programs)) {
            $urlreport = new moodle_url('/admin/tool/program/programsprogress.php',
                ['userid' => $user->id, 'type' => constants::STATUS_COMPLETED]);
            $strparams = ['href' => $urlreport->out(), 'count' => count($programs)];
            $node = new node('learning', 'completedprograms', '', null, null,
                get_string('completedprogramslink', 'tool_program', $strparams));
            $category->add_node($node);
        }
    }
}

/**
 * Callback for the tool_dynamicrule/rules_list can_view function.
 *
 * @param string $area
 * @param int $itemid
 * @return bool
 */
function tool_program_can_view_dynamic_rules(string $area, int $itemid): bool {
    if ($area === 'program') {
        $program = new program($itemid);
        return permission::can_view_details($program);
    }
    return false;
}

/**
 * Callback for tool_certificate - the fields available for the certificates
 */
function tool_program_tool_certificate_fields() {
    if (!class_exists('tool_certificate\customfield\issue_handler')) {
        return;
    }
    $handler = tool_certificate\customfield\issue_handler::create();

    $handler->ensure_field_exists('programid', 'numeric',
        get_string('displayprogramid', 'tool_program'), false, 1);

    $handler->ensure_field_exists('programname', 'text',
        get_string('displayprogramname', 'tool_program'),
        true,
        get_string('previewprogramname', 'tool_program')
    );

    $handler->ensure_field_exists('programcompletiondate', 'date',
        get_string('displaycompletiondate', 'tool_program'),
        true,
        userdate(strtotime(date('Y-01-01')), get_string('strftimedatefullshort')), // This is 01/01/<curentyear>.
        ['includetime' => false]
    );

    if (!$handler->find_field_by_shortname('programcompletedcourses')) {
        $courses = ['A course example', 'Second course example', 'Yet another course completed'];
        $display = \html_writer::start_tag('ul');
        foreach ($courses as $c) {
            $display .= \html_writer::tag('li', $c);
        }
        $display .= \html_writer::end_tag('ul');

        $handler->ensure_field_exists('programcompletedcourses', 'textarea',
            get_string('displaycompletedcourses', 'tool_program'),
            true,
            $display
        );
    }
}

/**
 * Get the current user preferences that are available
 *
 * @return mixed Array representing current options along with defaults
 */
function tool_program_user_preferences() {
    $preferences['tool_program_program_status_filter'] = [
        'null' => NULL_NOT_ALLOWED,
        'default' => 'all',
        'type' => PARAM_ALPHA,
        'choices' => [
            'all',
            'completed',
            'notcompleted',
            'courses',
            'programs',
        ]
    ];

    $preferences['tool_program_program_view_filter'] = [
        'null' => NULL_NOT_ALLOWED,
        'default' => 'viewcards',
        'type' => PARAM_ALPHA,
        'choices' => [
            'viewcards',
            'viewlist'
        ]
    ];

    $preferences['tool_program_program_sort_filter'] = [
        'null' => NULL_NOT_ALLOWED,
        'default' => 'duedate',
        'type' => PARAM_ALPHA,
        'choices' => [
            'duedate',
            'programname',
            'lastaccess'
        ]
    ];

    return $preferences;
}

/**
 * Callback for calendar event action
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid
 * @return \core_calendar\local\event\entities\action_interface|\core_calendar\local\event\value_objects\action
 */
function tool_program_core_calendar_provide_event_action(calendar_event $event,
                                                         \core_calendar\action_factory $factory, $userid = 0) {
    // TODO After WP-1960 is done we need to scroll down to the selected program.
    $str = get_string('gotoprogram', 'tool_program');
    return $factory->create_instance($str, new \moodle_url('/my', [], 'programs'), 5, true);
}

/**
 * Callback for tool_wp, return list of role shortnames this component defines.
 */
function tool_program_workplace_roles() {
    return ['tool_program_manager'];
}

/**
 * Callback for theme_workplace, return list of workplace menu items to be added to the launcher.
 *
 * @return array[] The array containing the workplace menu items where each item is an array with keys:
 *                 url => moodle_url where item will redirect
 *                 name => string name shown in the launcher
 *                 imageurl => string url for the icon shown in the launcher
 *                 isglobal (optional) => bool to indicate if item is displayed in the global section.
 */
function tool_program_theme_workplace_menu_items(): array {
    global $OUTPUT;

    $menuitems = [];
    if (permission::check_access()) {
        $menuitems[] = [
            'url' => new moodle_url("/admin/tool/program/index.php"),
            'name' => get_string('programs', 'tool_program'),
            'imageurl' => $OUTPUT->image_url('icon', 'tool_program')->out(false),
        ];
    }
    return $menuitems;
}

/**
 * Callback for block_myteams, return list of user sections to be added to the MyTeams block report.
 *
 * @param int $userid
 * @return userinfo_section[]
 */
function tool_program_block_myteams_user_section(int $userid): array {
    // Generate the "Programs" userinfo section.
    $sectionname = get_string('programs', 'tool_program');
    $sectionlink = new moodle_url('/admin/tool/program/programsprogress.php', ['userid' => $userid]);
    $programssection = new userinfo_section($sectionname, $sectionlink, 20);

    // Get user program allocation data from program API.
    $programallocations = array_filter(api::get_user_allocations($userid), function($userallocation) {
        // Only return programs with direct allocation type or through DR directly to programs.
        return $userallocation->get('allocationtype') === constants::ALLOCATION_MANUAL ||
            $userallocation->get('allocationtype') === constants::ALLOCATION_DYNAMIC;
    });

    // Feed the sections items.
    foreach ($programallocations as $programallocation) {
        $program = $programallocation->get_program();
        $programprogress = new program_tree_progress($program, $userid);

        $progress = get_string('progress', 'tool_program', $programprogress->get_program_progress_as_percentage());
        $item = new userinfo_section_item($program->get_formatted_name(), $progress);

        // Retrieve all user allocations to check overdue programs and add badge.
        $statuses = api::get_user_allocation_statuses($program->get('id'), $userid, 0);
        foreach ($statuses as $status) {
            if ($status['status'] === constants::STATUS_OVERDUE) {
                $item->add_badge(get_string('overdue', 'tool_program'), 'danger');
                $item->set_overdue(true);
            }
        }

        $programssection->add_item($item);
    }

    return [$programssection];
}
