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
 *  Callbacks for plugin tool_certification
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use core\output\inplace_editable;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_certification\permission;
use tool_organisation\organisation;

/**
 * Inplace editable function.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 * @return inplace_editable
 */
function tool_certification_inplace_editable(string $itemtype, int $itemid, string $newvalue) {
    $context = context_system::instance();
    external_api::validate_context($context);
    $newvalue = clean_param($newvalue, PARAM_TEXT);

    switch ($itemtype) {
        case 'certificationname':
            $certification = new certification($itemid);
            permission::require_can_edit_details($certification);
            $certification->set('fullname', $newvalue);
            $certification->update();
            $edithint = get_string('editcertificationname', 'tool_certification');
            $newvaluefor = get_string('newvaluefor', 'tool_certification');
            $url = new \moodle_url('/admin/tool/certification/edit.php', ['id' => $itemid]);
            $displayvalue = format_string($newvalue);
            $editlabel = $newvaluefor . $displayvalue;
            $displayvalue = \html_writer::link($url, $displayvalue);
            // Update certification name in all calendar events for this certification.
            api::update_certification_name_in_calendar_events($itemid, $newvalue);
            break;
        default:
            throw new coding_exception('Unexpected tool_certification inplace editable item type');
    }
    return new inplace_editable('tool_certification', $itemtype, $itemid, true, $displayvalue, $newvalue, $edithint, $editlabel);
}

/**
 * Callback for the tool_wp_potential_users_selector web service.
 *
 * @param string $area
 * @param int $itemid
 * @return array
 */
function tool_certification_potential_users_selector(string $area, int $itemid) {
    if ($area !== 'allocate') {
        return null;
    }

    if ($itemid) {
        $certification = new certification($itemid);
        permission::require_can_allocate_anybody($certification);
    }

    $join = '';
    $where = \tool_tenant\tenancy::get_users_subquery(false, false, 'u.id');
    $params = [];
    if ($itemid) {
        // Exclude users already allocated to the certification.
        $join .= ' LEFT JOIN {' . certification_user::TABLE . '} cu ON cu.userid = u.id AND certificationid = :certificationid';
        $where .= ' AND cu.id IS NULL';
        $params['certificationid'] = $itemid;
    }

    $user = organisation::get_user_with_jobs();
    $canallocate = $user && $user->is_manager(organisation::PERM_ALLOCATE_PROGRAMS);
    // Check if only can manage his own users and show only these users.
    if (!permission::has_allocateuser_capability(context_system::instance()) && $canallocate) {
        list($where0, $params0) = $user->get_managed_users_select('u', organisation::PERM_ALLOCATE_PROGRAMS);

        $paramsmerged = array_merge($params0, $params);
        return [$join, $where0 .' AND '. $where, $paramsmerged];
    }

    return [$join, $where, $params];
}

/**
 * Extends user "my profile" navigation nodes.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass $course
 */
function tool_certification_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (!array_key_exists('learning', $tree->__get('categories'))) {
        // Create the category.
        $learningstr = get_string('coursesadmintab', 'tool_wp');
        $category = new core_user\output\myprofile\category('learning', $learningstr, null);
        $tree->add_category($category);
    } else {
        // Get the existing category.
        $category = $tree->__get('categories')['learning'];
    }

    $canviewreports = permission::can_view_user_progress($user->id);

    if ($iscurrentuser || $canviewreports) {
        // Display ongoing certifications.
        $certs = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        if (!empty($certs)) {
            $urlreport = new moodle_url('/admin/tool/certification/report.php',
                ['userid' => $user->id, 'type' => constants::STATUS_OPEN]);
            $params = [
                'href' => $urlreport->out(),
                'count' => count($certs),
            ];
            $certstr = get_string('ongoingcertificationslink', 'tool_certification', $params);
            $node = new core_user\output\myprofile\node('learning', 'ongoingcertifications', '', null, null,
                rtrim($certstr, ', '));
            $category->add_node($node);
        }

        // Display overdue certifications.
        $certs = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        if (!empty($certs)) {
            $urlreport = new moodle_url('/admin/tool/certification/report.php',
                ['userid' => $user->id, 'type' => constants::STATUS_OVERDUE]);
            $params = [
                'href' => $urlreport->out(),
                'count' => count($certs),
            ];
            $certstr = get_string('overduecertificationslink', 'tool_certification', $params);
            $node = new core_user\output\myprofile\node('learning', 'overduecertifications', '', null, null,
                rtrim($certstr, ', '));
            $category->add_node($node);
        }

        // Display expired certifications.
        $certs = api::get_certifications_by_status_and_userid(constants::STATUS_EXPIRED, $user->id);
        if (!empty($certs)) {
            $urlreport = new moodle_url('/admin/tool/certification/report.php',
                ['userid' => $user->id, 'type' => constants::STATUS_EXPIRED]);
            $params = [
                'href' => $urlreport->out(),
                'count' => count($certs),
            ];
            $certstr = get_string('expiredcertificationslink', 'tool_certification', $params);
            $node = new core_user\output\myprofile\node('learning', 'expiredcertifications', '', null, null,
                rtrim($certstr, ', '));
            $category->add_node($node);
        }

        // Display certified certifications.
        $certs = api::get_certifications_by_status_and_userid(constants::STATUS_CERTIFIED, $user->id);
        if (!empty($certs)) {
            $urlreport = new moodle_url('/admin/tool/certification/report.php',
                ['userid' => $user->id, 'type' => constants::STATUS_CERTIFIED]);
            $params = [
                'href' => $urlreport->out(),
                'count' => count($certs),
            ];
            $certstr = get_string('certifiedcertificationslink', 'tool_certification', $params);
            $node = new core_user\output\myprofile\node('learning', 'certifiedcertifications', '', null, null,
                rtrim($certstr, ', '));
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
function tool_certification_can_view_dynamic_rules(string $area, int $itemid): bool {
    if ($area === 'certification') {
        $certification = new certification($itemid);
        return permission::can_view_details($certification);
    }
    return false;
}

/**
 * Callback for tool_certificate - the fields available for the certificates
 */
function tool_certification_tool_certificate_fields() {
    if (!class_exists('tool_certificate\customfield\issue_handler')) {
        return;
    }

    $handler = tool_certificate\customfield\issue_handler::create();

    $handler->ensure_field_exists('certificationid', 'numeric',
        get_string('displaycertificationid', 'tool_certification'), false, 1);

    $handler->ensure_field_exists('certificationname', 'text',
        get_string('displaycertificationname', 'tool_certification'),
        true,
        get_string('previewcertificationname', 'tool_certification')
    );

    $handler->ensure_field_exists('certificationdate', 'date',
        get_string('displaycertificationdate', 'tool_certification'),
        true,
        userdate(strtotime(date('Y-01-01')), get_string('strftimedatefullshort')), // This is 01/01/<curentyear>.
        ['includetime' => false]
    );

    $handler->ensure_field_exists('certificationexpirydate', 'date',
        get_string('displayexpirydate', 'tool_certification'),
        true,
        userdate(strtotime(date('Y-01-01')), get_string('strftimedatefullshort')), // This is 01/01/<curentyear>.
        ['includetime' => false]
    );

    $handler->ensure_field_exists('expirydatetimestamp', 'date',
        get_string('displayexpirydatetimestamp', 'tool_certification'),
        false,
        time(),
        ['includetime' => false]
    );
}

/**
 * Callback for calendar event action
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid
 * @return \core_calendar\local\event\entities\action_interface|\core_calendar\local\event\value_objects\action
 */
function tool_certification_core_calendar_provide_event_action(calendar_event $event,
                                                         \core_calendar\action_factory $factory, $userid = 0) {
    // TODO After WP-1960 is done we need to scroll down to the selected certification program.
    $str = get_string('gotocertification', 'tool_certification');
    return $factory->create_instance($str, new \moodle_url('/my', [], 'programs'), 5, true);
}

/**
 * Callback for tool_wp, return list of role shortnames this component defines.
 */
function tool_certification_workplace_roles() {
    return ['tool_certification_manager'];
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
function tool_certification_theme_workplace_menu_items(): array {
    global $OUTPUT;

    $menuitems = [];
    if (permission::check_access()) {
        $menuitems[] = [
            'url' => new moodle_url("/admin/tool/certification/index.php"),
            'name' => get_string('certifications', 'tool_certification'),
            'imageurl' => $OUTPUT->image_url('icon', 'tool_certification')->out(false),
        ];
    }
    return $menuitems;
}
