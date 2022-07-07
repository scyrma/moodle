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

namespace tool_tenant;

use core_user\form\profile_category_form;

/**
 * Various hooks and callbacks for limiting user profile fields categories to tenants
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class profile_manager {
    /** @var string */
    const TENANT_ALL = 'all';
    /** @var string */
    const TENANT_ONLY = 'only';
    /** @var string */
    const TENANT_EXCEPT = 'except';
    /** @var string */
    const AVAILABILITY = 'availability';
    /** @var string */
    const ONLYTENANTS = 'onlytenants';
    /** @var string */
    const EXCEPTTENANTS = 'excepttenants';

    /**
     * Sets tenant availability for a profile field category
     *
     * @param \stdClass $data object that has property 'id' and can contain properties
     *     self::AVAILABILITY, self::ONLYTENANTS, self::EXCEPTTENANTS
     */
    public static function save_category_config(\stdClass $data): void {
        $availability = $data->{self::AVAILABILITY} ?? self::TENANT_ALL;
        if (!in_array($availability, [self::TENANT_EXCEPT, self::TENANT_ONLY])) {
            $avdata = null;
        } else {
            $onlytenants = ($availability == self::TENANT_ONLY) ? ($data->{self::ONLYTENANTS} ?? []) : [];
            $excepttenants = ($availability == self::TENANT_EXCEPT) ? ($data->{self::EXCEPTTENANTS} ?? []) : [];
            $avdata = json_encode([
                self::AVAILABILITY => $availability,
                self::ONLYTENANTS => $onlytenants,
                self::EXCEPTTENANTS => $excepttenants,
            ]);
        }
         set_config('tool_tenant_user_info_category_'.$data->id, $avdata);
    }

    /**
     * Retrieves tenant availability for a profile field category
     *
     * @param int $id category id, if not specified the default object will be returned
     * @param \stdClass $cfg
     * @return array array containing properties self::AVAILABILITY, self::ONLYTENANTS, self::EXCEPTTENANTS
     */
    public static function get_category_config(?int $id = null, ?\stdClass $cfg = null): array {
        global $CFG;
        $cfg = $cfg ?? $CFG;
        $defaults = [
            self::AVAILABILITY => self::TENANT_ALL,
            self::ONLYTENANTS => [],
            self::EXCEPTTENANTS => [],
        ];
        if ($id) {
            $value = $cfg->{'tool_tenant_user_info_category_'.$id} ?? null;
            if ($value && ($data = @json_decode($value, true))) {
                return $data + $defaults;
            }
        }
        return $defaults;
    }

    /**
     * Hook used in {@see profile_category_form::definition()}
     *
     * @param profile_category_form $form
     * @param \MoodleQuickForm $mform
     */
    public static function category_definition(profile_category_form $form, \MoodleQuickForm $mform) {
        $mform->addElement('radio', self::AVAILABILITY, null,
            get_string('profilecategory_alltenants', 'tool_tenant'),
            self::TENANT_ALL);
        $mform->addElement('radio', self::AVAILABILITY, null,
            get_string('profilecategory_onlyfollowingtenants', 'tool_tenant') . '...',
            self::TENANT_ONLY);

        $options = [
            'ajax' => 'tool_tenant/form-potential-tenant-selector',
            'multiple' => true,
            'valuehtmlcallback' => function ($tenantid) {
                return tenancy::get_tenant_name_from_id($tenantid);
            }
        ];
        $mform->addElement('autocomplete', self::ONLYTENANTS,
            get_string('selecttenants', 'tool_tenant'), [], $options)
            ->setHiddenLabel(true);

        $mform->addElement('radio', self::AVAILABILITY, null,
            get_string('profilecategory_exceptfollowingtenants', 'tool_tenant') . '...',
            self::TENANT_EXCEPT);
        $mform->addElement('autocomplete', self::EXCEPTTENANTS,
            get_string('selecttenants', 'tool_tenant'), [], $options)
            ->setHiddenLabel(true);

        $mform->hideIf(self::ONLYTENANTS, self::AVAILABILITY, 'ne', self::TENANT_ONLY);
        $mform->hideIf(self::EXCEPTTENANTS, self::AVAILABILITY, 'ne', self::TENANT_EXCEPT);

    }

    /**
     * Hook used in {@see profile_category_form::validation()}
     *
     * @param profile_category_form $form
     * @param array $data
     * @param array $files
     * @param array $errors
     * @return array
     */
    public static function category_validation(profile_category_form $form, array $data, array $files, array $errors): array {
        // No validation required but let's leave this hook in core in case we need it in the future.
        return $errors;
    }

    /**
     * Hook used in {@see profile_category_form::set_data_for_dynamic_submission()}
     *
     * @param profile_category_form $form
     */
    public static function category_set_data_for_dynamic_submission(profile_category_form $form) {
        $id = $form->optional_param('id', 0, PARAM_INT);
        $form->set_data(self::get_category_config($id));
    }

    /**
     * Hook used in {@see profile_category_form::process_dynamic_submission()}
     *
     * @param profile_category_form $form
     * @param \stdClass $data
     */
    public static function category_process_dynamic_submission(profile_category_form $form, \stdClass $data) {
        self::save_category_config($data);
    }

    /**
     * Hook used in user/profile/index.php to display the tenants availability for a profile field category
     *
     * @param array $categories array of categories data for the template that was built in index.php
     * @return array the same array as $categories with additional data ('tenantinfo')
     */
    public static function add_tenant_info(array $categories): array {
        global $OUTPUT;
        foreach (array_keys($categories) as $i) {
            $config = self::get_category_config($categories[$i]['id']);
            $categories[$i]['tenantinfo'] = '';

            $tenants = tenancy::get_tenants();
            if ($config[self::AVAILABILITY] == self::TENANT_ONLY) {
                $showtenants = array_filter($tenants, function($t) use ($config) {
                    return in_array($t->id, $config[self::ONLYTENANTS]);
                });
            } else if ($config[self::AVAILABILITY] == self::TENANT_EXCEPT) {
                $sharedtenantid = sharedspace::get_shared_space_id();
                $showtenants = array_filter($tenants, function($t) use ($config, $sharedtenantid) {
                    return !in_array($t->id, $config[self::EXCEPTTENANTS]) && $t->id != $sharedtenantid;
                });
            } else {
                continue;
            }
            if ($showtenants) {
                $list = array_map(function($t) {
                    return format_string($t->name, true, \context_system::instance());
                }, $showtenants);
                $categories[$i]['tenantinfo'] = $OUTPUT->render_from_template('tool_tenant/profile_field_tenants',
                    [
                        'id' => $categories[$i]['id'],
                        'hastenants' => count($showtenants),
                        'shortlist' => join(', ', array_slice($list, 0, 3)),
                        'fulllist' => (count($list) > 3) ? join(', ', $list) : '',
                        'hasmoretenants' => max(count($list) - 3, 0),
                    ]);
            } else {
                $categories[$i]['tenantinfo'] = '';
            }
        }
        return $categories;
    }

    /**
     * Is profile field category currently visible
     *
     * This function checks if the profile category available for the current tenant
     * "Current tenant" is not necessary the tenant of the current user. It can be 0 for the admin pages,
     * or respective tenant when viewing tenant report or user from another tenant
     *
     * See also {@see config::get_tenant_id()}
     *
     * @param int $id
     * @param \stdClass|null $cfg
     * @return bool
     */
    protected static function is_category_visible(int $id, ?\stdClass $cfg = null): bool {
        global $CFG;
        $tenantid = config::get_tenant_id();
        // Special treatment for shared space - it should allow to view all custom profile fields.
        // However we can not call sharedspace::get_shared_space_id() because the $CFG may not be available,
        // so we have to re-implement it here.
        $sharedspaceid = $cfg ? ($cfg->tool_tenant_shared_tenant_id ?? 0) : ($CFG->tool_tenant_shared_tenant_id ?? 0);
        if (!$tenantid || $tenantid == $sharedspaceid) {
            // Special case, configuration page or admin report, show all user profile fields.
            return true;
        }
        $config = self::get_category_config($id, $cfg);
        if ($config[self::AVAILABILITY] == self::TENANT_ONLY) {
            return in_array($tenantid, $config[self::ONLYTENANTS]);
        } else if ($config[self::AVAILABILITY] == self::TENANT_EXCEPT) {
            return !in_array($tenantid, $config[self::EXCEPTTENANTS]);
        }
        return true;
    }

    /** @var array */
    protected static $fieldscache = null;

    /**
     * Creates a map of profile fields shortname->categoryid
     *
     * @return array
     */
    protected static function get_fields_categories_map(): array {
        global $DB;
        if (self::$fieldscache === null) {
            self::$fieldscache = $DB->get_records_menu('user_info_field', [], '', 'shortname, categoryid');
        }
        return self::$fieldscache;
    }

    /**
     * Resets static cache (used in phpunit)
     */
    public static function reset_caches() {
        self::$fieldscache = null;
    }

    /**
     * Checks if given profile field is currently visible (category is available to the current tenant)
     *
     * @param string $shortname
     * @param \stdClass|null $cfg object to be used instead of $CFG (only for config building)
     * @return bool
     */
    protected static function is_field_visible(string $shortname, ?\stdClass $cfg = null): bool {
        return self::is_category_visible(self::get_fields_categories_map()[$shortname] ?? 0, $cfg);
    }

    /**
     * Filter the list of fields to leave only ones visible for the current tenant
     *
     * Hook used in {@see profile_get_user_fields_with_data()}
     *
     * @param array $fields array of field objects (with the 'categoryid' property)
     * @param int $userid
     * @return array
     */
    public static function filter_field_objects_list(array $fields, int $userid = 0): array {
        if ($userid) {
            if (self::$nextusertenantid) {
                config::push_for_tenant(self::$nextusertenantid);
            } else {
                config::push_for_user($userid);
            }
        }
        self::$nextusertenantid = null;
        $res = array_filter($fields, function(\stdClass $field) {
            return self::is_category_visible($field->categoryid);
        });
        if ($userid) {
            config::pop();
        }
        return $res;
    }

    /** @var int */
    static protected $nextusertenantid = null;

    /**
     * Hook in core function {@see profile_save_data()}
     *
     * When profile_save_data() is executed for the user who has only just been created
     * the user tenant will be "Default tenant" because the actual tenant is only allocated
     * after the user_created event is triggered. However the user record is not available in
     * the filter_field_objects_list() function.
     *
     * Remember the tenant for the next call of {@see self::filter_field_objects_list()}
     *
     * @param \stdClass $newuser
     */
    public static function before_profile_save_data(\stdClass $newuser) {
        if ($tenantid = \tool_tenant\manager::guess_future_user_tenant($newuser)) {
            self::$nextusertenantid = $tenantid;
        }
    }

    /**
     * Checks if the profile field is visible for the current tenant
     *
     * @param \stdClass $field object containing at least 'categoryid' property
     * @return bool
     */
    public static function is_field_object_visible(\stdClass $field): bool {
        return self::is_category_visible($field->categoryid);
    }

    /**
     * Filter the list of fields to leave only ones visible for the current tenant
     *
     * @param array $fieldnames array of field shortnames
     * @param bool $withprefix if specified, will assume that custom user profile fields names start with "profile_field_"
     * @param \stdClass $cfg if specified, use instead of $CFG (for config hook only)
     * @return array
     */
    public static function filter_fields_list(array $fieldnames, bool $withprefix = false, ?\stdClass $cfg = null): array {
        return array_filter($fieldnames, function(string $fieldname) use ($withprefix, $cfg) {
            if ($withprefix) {
                return !preg_match("/^profile_field_(.*)$/", $fieldname, $matches) ||
                    self::is_field_visible($matches[1], $cfg);
            }
            return self::is_field_visible($fieldname, $cfg);
        });
    }

    /**
     * Hook used in {@see get_config()} - changes some config values for the current tenant
     *
     * 'showuseridentity' is filtered to contain only profile fields currently visible
     *
     * @param array $config
     */
    public static function calculate_config_for_tenant(array &$config) {
        $identity = explode(',', $config['showuseridentity']);
        $identity = self::filter_fields_list($identity, true, (object)$config);
        $config['showuseridentity'] = join(',', $identity);
    }

    /**
     * Returns an array of all custom field records, even those that are hidden from the current tenant
     *
     * @return \profile_field_base[]
     */
    public static function profile_get_all_user_fields(): array {
        config::push_for_tenant(0);
        $userprofilefields = profile_get_user_fields_with_data(0);
        config::pop();
        return $userprofilefields;
    }
}
