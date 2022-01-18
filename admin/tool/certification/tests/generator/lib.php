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
 * Generator for tool_certification tests.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_program\persistent\program;

/**
 * Class tool_certification_generator
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_generator extends component_generator_base {

    /**
     * Create a certification
     *
     * Used in Behat step 'Given the following "tool_certification > certifications" exist:'.
     *
     * @param array $record
     * @return certification
     */
    public function create_certification(array $record): certification {

        if (isset($record['certification_tags'])) {
            $record['certification_tags'] = explode(',', $record['certification_tags']);
        }

        $record = $this->get_date_constant($record, 'expirydatetype');
        $record = $this->get_date_constant($record, 'duedatetype');
        $record = $this->get_date_constant($record, 'startdatetype');
        $record = $this->get_date_absolute($record, 'expirydateabsolute');
        $record = $this->get_date_absolute($record, 'duedateabsolute');
        $record = $this->get_date_absolute($record, 'startdateabsolute');

        $data = (object) [
            'fullname' => 'A certification fullname',
            'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
            'certification_tags' => ['hello', 'world'],
            'autocreategroups' => \tool_program\api::GROUPS_AS_IN_PROGRAMS,
            'archived' => false,
            'shared' => 0,
        ];

        if (isset($record['certification_tags']) && !is_array($record['certification_tags'])) {
            $record['certification_tags'] = explode(',', $record['certification_tags']);
        }

        $mergeddata = array_merge((array) $data, (array) $record);

        return $this->generate_certification($mergeddata, $record['requirerecertification'] ?? 0);
    }

    /**
     * Create a certification user allocation
     *
     * Used in Behat step 'Given the following "tool_certification > certification_users" exist:'.
     *
     * @param array $record
     * @return certification_user
     */
    public function create_certification_user(array $record): certification_user {
        if (!isset($record['status']) || !strlen($record['status'])) {
            $record['status'] = \tool_certification\constants::STATUS_OVERRIDE_DEFAULT;
        }
        if (!isset($record['allocationtype']) || !strlen($record['allocationtype'])) {
            $record['allocationtype'] = \tool_certification\constants::ALLOCATION_MANUAL;
        }
        return $this->allocate_user($record['userid'], $record['certificationid']);
    }

    /**
     * Convert the date constant name to its value
     *
     * @param array $data
     * @param string $fieldname
     * @return array
     */
    protected function get_date_constant(array $data, string $fieldname): array {
        if (!empty($data[$fieldname]) && !is_numeric($data[$fieldname])) {
            $data[$fieldname] = constant('\tool_certification\constants::DATE_' . strtoupper($data[$fieldname]));
        }
        return $data;
    }

    /**
     * Convert the absolute date in human-readable form into the unix timestamp
     *
     * @param array $data
     * @param string $fieldname
     * @return array
     */
    protected function get_date_absolute(array $data, string $fieldname): array {
        if (!empty($data[$fieldname]) && !is_numeric($data[$fieldname])) {
            $data[$fieldname] = strtotime($data[$fieldname]);
        }
        return $data;
    }

    /**
     * Returns data to create a certification.
     *
     * @return stdClass
     */
    public function get_dummy_certificationdata(): stdClass {

        $program = new program(0, $this->get_dummy_program());

        return (object) [
            'fullname' => 'A certification fullname',
            'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
            'idnumber' => '1',
            'program' => $program->get('id'),
            'certification_tags' => [
                'hello', 'world'
            ],
            'startdateabsolute' => strtotime('-5 day'),
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
            'autocreategroups' => \tool_program\api::GROUPS_AS_IN_PROGRAMS,
            'archived' => false,
            'shared' => 0,
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
            'program_tags' => ['hello', 'world'],
        ];

        return (object) array_merge($newprogramdata, $programdata);
    }

    /**
     * Generates a certification.
     *
     * @param array $certificationdata
     * @param bool $requiresrecertification
     * @return certification
     */
    public function generate_certification(array $certificationdata = [], bool $requiresrecertification = false): certification {
        /** @var tool_program_generator $programgenerator */
        $programgenerator = \testing_util::get_data_generator()->get_plugin_generator('tool_program');
        if (isset($certificationdata['program'])) {
            $programid = $certificationdata['program'];
            $program = new program($programid);
            $programtenantid = $program->get('tenantid');
            if (!empty($certificationdata['tenantid'])) {
                if (!\tool_tenant\hierarchy::is_own_or_parent_shared_entity($programtenantid,
                        $program->get('shared'), $certificationdata['tenantid'])) {
                    throw new coding_exception('Certification tenant ' . s($certificationdata['tenantid']) .
                        ' does not match the program tenant ' . s($programtenantid));
                }
            } else {
                $certificationdata['tenantid'] = $programtenantid;
            }
        } else {
            // We create a dummy program.
            $tenantid = !empty($certificationdata['tenantid']) ? $certificationdata['tenantid'] :
                \tool_tenant\tenancy::get_default_tenant_id();
            $program = $programgenerator->generate_program_with_course((object)['tenantid' => $tenantid]);
            $programid = $program->get('id');
            $certificationdata['tenantid'] = $tenantid;
            $certificationdata['program'] = $programid;
        }

        // We create a dummy certification.
        $newcertificationdata = (array)$this->get_dummy_certificationdata();
        $newcertificationdata = array_merge($newcertificationdata, $certificationdata);

        if ($requiresrecertification) {
            if (empty($newcertificationdata['recertificationprogram'])) {
                // We create a dummy program.
                $program = $programgenerator->generate_program_with_course((object)['tenantid' => $certificationdata['tenantid']]);
                $programid2 = $program->get('id');
                $newcertificationdata['recertificationprogram'] = $programid2;
            }

            $newcertificationdata['requirerecertification'] = 1;
            $newcertificationdata['recertdifferentprogram'] = ($newcertificationdata['recertificationprogram'] != $programid);
            $newcertificationdata['recertstartdaterelative'] = $certificationdata['recertstartdaterelative'] ?? '1 week';
            $newcertificationdata['recertgraceperiod'] = $certificationdata['recertgraceperiod'] ?? '1 week';
            $newcertificationdata['recertgraceperiodlocked'] = $certificationdata['recertgraceperiodlocked'] ?? 0;
            $newcertificationdata['recertexpirydatetype'] = $certificationdata['recertexpirydatetype'] ?? 1;
            $newcertificationdata['recertexpirydaterelative'] = $certificationdata['recertexpirydaterelative'] ?? '1 week';
        }

        return api::create_certification((object)$newcertificationdata);
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
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => constants::ALLOCATION_MANUAL,
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
        $userdata = $this->get_dummy_userdata($certificationid, $userid);

        return api::allocate_user($certification, (object)$userdata);
    }

    /**
     * Allocates users to a certification
     *
     * @param int $certificationid
     * @param array $userids
     * @return certification_user[]
     */
    public function allocate_users_to_certification(int $certificationid, array $userids): array {
        $rv = [];
        foreach ($userids as $userid) {
            $rv[$userid] = $this->allocate_user($userid, $certificationid);
        }
        return $rv;
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
            'othertenantid' => $othertenant->id,
        ];
    }

    /**
     * Get dummy user completion data.
     *
     * @param int $certificationid
     * @param int $userid
     * @param int $programid
     * @param int $islast
     * @return stdClass
     */
    public function get_dummy_completion_data(int $certificationid, int $userid, int $programid, int $islast = 0): stdClass {
        return (object)[
            'certificationid' => $certificationid,
            'userid' => $userid,
            'expirydate' => time() + 3600,
            'timerevoked' => 0,
            'islast' => $islast,
            'programid' => $programid,
            'timecertified' => time(),
        ];
    }
}
