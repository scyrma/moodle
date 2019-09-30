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
 *  Callbacks for plugin tool_certification
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\inplace_editable;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_certification\permission;
use tool_organisation\organisation;
use tool_certification\local\reports\users_table;
use tool_reportbuilder\system_report_factory;

defined('MOODLE_INTERNAL') || die();

/**
 * Fragment to reload users list in certification manager.
 *
 * @param array $args
 * @return string
 */
function tool_certification_output_fragment_certifications_manager_users_list(array $args) {
    $certificationid = $args['id'];
    $context = $args['context'];
    $certification = new certification($certificationid);
    \tool_certification\permission::require_can_view_allocated_users($certification);

    // Check if tool_reportbuilder is installed.
    if (class_exists('\\tool_reportbuilder\\system_report_factory')) {
        // Users list.
        $report = system_report_factory::create(users_table::class, ['id' => $certificationid]);
        $userstable = $report->output();
    } else {
        $str = get_string('reportbuilderuserlist', 'tool_certification');
        $userstable = \html_writer::tag('div', $str, array('class' => 'alert alert-warning'));
    }

    ob_start();
    echo $userstable;
    return ob_get_clean();
}

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

    list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u');
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