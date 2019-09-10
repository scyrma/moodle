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
 * Generator for tool_certification tests.
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use tool_certification\certification;
use tool_certification\certification_completion;
use tool_certification\certification_user;
use tool_certification\constants;

/**
 * Class tool_certification_generator
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certification_generator extends component_generator_base {
    /**
     * Returns data to create a certification.
     *
     * @return stdClass
     */
    public function get_dummy_certificationdata(): stdClass {
        return (object) [
            'fullname' => 'A certification fullname',
            'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
            'idnumber' => '1',
            'certification_tags' => [
                'hello', 'world'
            ],
            'startdateabsolute' => strtotime('-7 day'),
            'duedateabsolute' => 0,
            'expirydateabsolute' => strtotime('+7 month'),
            'startdatetype' => constants::DATE_ABSOLUTE,
            'duedatetype' => constants::DATE_AFTER_START_DATE,
            'expirydatetype' => constants::DATE_ABSOLUTE,
            'startdaterelative' => '',
            'duedaterelative' => '1 week',
            'expirydaterelative' => '',
            'allocationstartdatetype' => constants::DATE_NONE,
            'allocationstartdateabsolute' => 0,
            'allocationenddatetype' => constants::ALLOCATION_NOT_SET,
            'allocationenddateabsolute' => 0,
            'archived' => false,
        ];
    }

    /**
     * Returns data to create a program.
     *
     * @param array $programdata
     * @return stdClass
     */
    public function get_dummy_program(array $programdata = []): stdClass {
        $newprogramdata = [
            'fullname' => 'A program fullname',
            'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
            'idnumber' => '14',
            'program_tags' => ['hello', 'world']
        ];

        return (object) array_merge($newprogramdata, $programdata);
    }

    /**
     * Generates a certification.
     *
     * @param array $certificationdata
     * @return certification
     */
    public function generate_certification(array $certificationdata = []): certification {
        if (isset($certificationdata['program'])) {
            $programid = $certificationdata['program'];
        } else {
            // We create a dummy program.
            $programdata = $this->get_dummy_program();
            $program = \tool_program\api::create_program($programdata);
            $programid = $program->get('id');
        }

        // We create a dummy certification.
        $newcertificationdata = (array)$this->get_dummy_certificationdata();
        $newcertificationdata['program'] = $programid;
        unset($newcertificationdata['certification_tags']);

        $certificationtags = '';
        if (isset($certificationdata['certification_tags'])) {
            $certificationtags = $certificationdata['certification_tags'];
            unset($certificationdata['certification_tags']);
        }

        $newcertificationdata = array_merge($newcertificationdata, $certificationdata);
        if (empty($newcertificationdata['tenantid'])) {
            $newcertificationdata['tenantid'] = \tool_tenant\tenancy::get_default_tenant_id();
        }
        $certification = new certification(0, (object) $newcertificationdata);
        $certification->create();
        $id = $certification->get('id');

        // Check if tool_dynamicrule is installed.
        if (class_exists('\\tool_dynamicrule\\rules_list')) {
            \tool_certification\api::add_default_dynamicrule_conditions_to_certification($id, $certification->get('tenantid'));
        }

        // Save certification tags.
        $context = context_system::instance();
        core_tag_tag::set_item_tags('tool_certification', 'tool_certification', $id, $context, $certificationtags);

        return $certification;
    }

    /**
     * Get dummy user data.
     *
     * @param int $certificationid
     * @param int $userid
     * @return array
     */
    public function get_dummy_userdata(int $certificationid, int $userid): array {
        return [
            'certificationid' => $certificationid,
            'userid' => $userid,
            'status' => 1,
            'allocationtype' => \tool_certification\constants::ALLOCATION_MANUAL,
            'startdate' => strtotime('-7 day'),
            'startdatelocked' => 0,
            'duedate' => strtotime('+7 day'),
            'duedatelocked' => 0,
            'enddate' => strtotime('+7 day'),
            'enddatelocked' => 0,
            'expirydate' => 0,
            'expirydatelocked' => 0,
        ];
    }

    /**
     * Allocate user.
     *
     * @param int $userid
     * @param int $certificationid
     * @return certification_user
     */
    public function allocate_user(int $userid, int $certificationid): certification_user {
        $certification = new certification($certificationid);
        $program = $certification->get_certification_program();

        // We allocate user to this certification.
        $data = $this->get_dummy_userdata($certificationid, $userid);
        $newcertuser = new certification_user(0, (object) $data);
        $newcertuser->create();

        // We allocate user into the certification program.
        $data = (object)[
            'userid' => $newcertuser->get('userid'),
            'certificationid' => $certificationid,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
            'startdate' => $newcertuser->get('startdate'),
            'enddate' => $newcertuser->get('enddate'),
            'duedate' => $newcertuser->get('duedate')
        ];
        $programuser = \tool_program\api::allocate_user($program, $data);
        if (!$programuser) {
            throw new moodle_exception('errorallocatinguserintorelatedprogram', 'tool_certification');
        }

        return $newcertuser;
    }

    /**
     * Completes a certification.
     *
     * @param certification $certification
     * @param int $userid
     * @param int $expirydate
     * @return certification_completion
     */
    public function complete_certification(certification $certification, int $userid,
                                           int $expirydate = 0): certification_completion {
        $certcompletion = new certification_completion(0, (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $userid,
            'expirydate' => $expirydate,
            'timerevoked' => 0
        ]);
        $certcompletion->create();
        return $certcompletion;
    }

    /**
     * Assigns edit capability.
     * @param int $userid
     * @param context $context
     * @return void
     * @throws coding_exception
     */
    public function assign_edit_capability(int $userid, context $context): void {
        // We assign capability to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('tool/certification:edit', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $userid, $context->id);
    }

    /**
     * Assigns allocateuser capability.
     * @param int $userid
     * @param context $context
     * @return void
     * @throws coding_exception
     */
    public function assign_allocateuser_capability(int $userid, context $context): void {
        // We assign capability to user.
        $roleid = create_role('Dummy role 2', 'dummyrole2', 'dummy role 2 description');
        assign_capability('tool/certification:allocateuser', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $userid, $context->id);
    }

    /**
     * Creates tenant and assigns user.
     * @return object
     */
    public function create_tenant_and_user() {
        // We retrieve default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        // Create one user, he will be allocated to default tenant.
        $user = phpunit_util::get_data_generator()->create_user();
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = phpunit_util::get_data_generator()->get_plugin_generator('tool_tenant');
        // Create one more tenant.
        $othertenant = $tenantgenerator->create_tenant([]);
        return (object)[
            'user' => $user,
            'defaulttenantid' => $defaulttenantid,
            'othertenantid' => $othertenant->id
        ];
    }
}
