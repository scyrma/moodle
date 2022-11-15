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
 * Cohorts exporter
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\exporter;

use context_coursecat;
use context_system;
use core_user\fields;
use core_course_category;
use tool_tenant\tenancy;
use tool_tenant\tenant;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/cohort/lib.php');

/**
 * Exporter class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cohorts extends exporter_base {

    /** @var string */
    public const EXPORT_CONTENT = 'export_content';
    /** @var string Element for selecting to export cohort members. */
    public const EXPORT_USERS = 'export_users';
    /** @var string Element for selecting what to export. */
    public const EXPORT_INSTANCES = 'export_instances';
    /** @var string Element for export all cohorts. */
    public const EXPORT_INSTANCES_ALL = 'all';
    /** @var string Element for export all system cohorts. */
    public const EXPORT_INSTANCES_ALL_SYSTEM = 'allsystem';
    /** @var string Element for export manually selected course categories. */
    public const EXPORT_INSTANCES_CATEGORY = 'incategories';
    /** @var string Element for export manually selected cohorts. */
    public const EXPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for selecting what to export. */
    public const EXPORT_SELECT_CATEGORIES = 'select_categories';
    /** @var string Element for selecting what to export. */
    public const EXPORT_SELECT_COHORTS = 'select_cohorts';

    /**
     * Exporter format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_WORKPLACE;
    }

    /**
     * Exporter name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('cohorts', 'cohort');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exporterdescriptioncohorts', 'tool_wp');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/workplace-file', 'theme')->out(false);
    }

    /**
     * Initialise the class, register entities that can be exported from other places
     */
    protected function initialise() {
        $this->register_entity('cohort', [
            self::ENTITY_INDIVIDUALEXPORT => function(array $ids, array $settings) {
                // What parameters to use when exporting individual entity as part of some other export.
                // May be called from programs and tenants export.
                $defaults = [
                    self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_SELECTED,
                    self::EXPORT_CONTENT => 1,
                    self::EXPORT_USERS => 1,
                    self::EXPORT_SELECT_CATEGORIES => [],
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::EXPORT_SELECT_COHORTS => $ids,
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return format_string($record['name']);
            },
        ]);
    }

    /**
     * Does this exporter "work" only within one tenant?
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return false;
    }

    /**
     * Allows to mark exporter as not available
     *
     * By default every exporter is available for general export and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) &&
            (has_capability('moodle/cohort:manage', \context_system::instance()) ||
                \core_course_category::has_capability_on_any('moodle/cohort:manage'));
    }

    /**
     * Add elements to the export form
     *
     * To retrieve QuickForm:
     *   $mform = $form->get_quick_form();
     * To add form validation:
     *   $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param export_settings_form $form
     */
    public function add_to_options_form(export_settings_form $form): void {
        $mform = $form->get_quick_form();

        $selectall = get_string('allcohorts', 'tool_wp');
        $selectallsystem = get_string('allsystemcohorts', 'tool_wp');
        $selectcategory = get_string('selectmanuallycategories', 'tool_wp');
        $selectmanually = get_string('selectmanually', 'tool_wp');

        $mform->addElement('header', 'exporterheader', get_string('content', 'tool_wp'));
        $mform->setExpanded('exporterheader');

        $mform->addElement('advcheckbox', self::EXPORT_CONTENT, get_string('cohortdetails', 'tool_wp'));
        $mform->addHelpButton(self::EXPORT_CONTENT, 'cohortdetails', 'tool_wp');
        $mform->setDefault(self::EXPORT_CONTENT, 1);
        $form->freeze_at(self::EXPORT_CONTENT, 1);

        $mform->addElement('advcheckbox', self::EXPORT_USERS, get_string('cohortmembers', 'tool_wp'));
        $mform->addHelpButton(self::EXPORT_USERS, 'cohortmembers', 'tool_wp');
        $mform->setDefault(self::EXPORT_USERS, 1);

        $mform->addElement('header', 'coursesinstances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('coursesinstances');

        // All cohorts.
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectall, self::EXPORT_INSTANCES_ALL);
        $mform->addHelpButton(self::EXPORT_INSTANCES, 'allcohorts', 'tool_wp');

        // All system cohorts. Should be available only when user can manage context system cohorts.
        if (!$this->get_export_tenant_id() && has_capability('moodle/cohort:manage', \context_system::instance())) {
            $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectallsystem, self::EXPORT_INSTANCES_ALL_SYSTEM);
        }

        // Cohorts in selected category.
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectcategory, self::EXPORT_INSTANCES_CATEGORY);
        $mform->addElement('autocomplete', self::EXPORT_SELECT_CATEGORIES, get_string('categories'),
            $this->get_cohort_category_list(), ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::EXPORT_SELECT_CATEGORIES, PARAM_INT);
        $mform->hideIf(self::EXPORT_SELECT_CATEGORIES, self::EXPORT_INSTANCES, 'noteq', self::EXPORT_INSTANCES_CATEGORY);

        // Set default tenant category.
        $tenantid = $this->get_export_tenant_id();
        if ($tenantid && $tenantcategory = tenancy::get_tenants()[$tenantid]->categoryid) {
            $mform->setDefault(self::EXPORT_SELECT_CATEGORIES, $tenantcategory);
        }

        // Select manually.
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectmanually, self::EXPORT_INSTANCES_SELECTED);
        $mform->addElement('autocomplete', self::EXPORT_SELECT_COHORTS, get_string('cohorts', 'cohort'),
            $this->get_cohorts_list(), ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::EXPORT_SELECT_COHORTS, PARAM_INT);
        $mform->hideIf(self::EXPORT_SELECT_COHORTS, self::EXPORT_INSTANCES, 'noteq', self::EXPORT_INSTANCES_SELECTED);

        $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_ALL);

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Returns a list of cohorts for autocomplete element
     *
     * @return array
     */
    private function get_cohorts_list(): array {
        global $DB;

        // Include cohorts from all categories user can access.
        $contextids = [];
        foreach ($this->get_cohort_category_list() as $categoryid => $name) {
            $contextids[] = context_coursecat::instance($categoryid)->id;
        }

        // If they can view cohorts at the system level, include those too.
        $contextsystem = context_system::instance();
        if (!$this->get_export_tenant_id() && has_capability('moodle/cohort:manage', $contextsystem)) {
            $contextids[] = $contextsystem->id;
        }

        if (empty($contextids)) {
            return [];
        }

        [$select, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        return $DB->get_records_select_menu('cohort', "contextid {$select}", $params, 'name', 'id,name');
    }

    /**
     * Perform some extra moodle validation. Called from $form->add_validation_callback().
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_CATEGORY &&
            empty($data[self::EXPORT_SELECT_CATEGORIES])) {
            $errors[self::EXPORT_SELECT_CATEGORIES] = get_string('selectatleastonecategory', 'tool_wp');
        }

        if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_SELECTED && empty($data[self::EXPORT_SELECT_COHORTS])) {
            $errors[self::EXPORT_SELECT_COHORTS] = get_string('selectatleastonecohort', 'tool_wp');
        }

        return $errors;
    }

    /**
     * Summary of the export settings for the review step and also for the report page
     *
     * @param bool $exportcompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $exportcompleted): string {
        global $OUTPUT;

        $data = $this->get_export_settings();
        $settings = [];

        $settingsstr = get_string('cohortdetails', 'tool_wp');
        $settings[] = ['name' => $settingsstr, 'value' => 1];

        $str = get_string('cohortmembers', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => !empty($data[self::EXPORT_USERS])];

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
    }

    /**
     * Returns the list of entities that will be exported
     *
     * @param string $entityname
     * @return array array where each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW
     *     callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        if ($entityname === 'cohort') {
            return array_map(function($record) {
                return (array)$record;
            }, $this->get_cohorts_to_export($this->get_export_settings()));
        }
        return [];
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        global $DB;
        $data = $this->get_export_settings();

        if (!empty($data[self::EXPORT_INSTANCES])) {
            $cohorts = $this->get_cohorts_to_export($data);
            foreach ($cohorts as $cohort) {
                $export = $this->prepare_data_for_workplace_export('cohort', (array)$cohort)
                    ->exclude_fields(['timecreated', 'timemodified']);

                // Check if cohort members need to be exported.
                if (!empty($data[self::EXPORT_USERS])) {

                    // We add user fullname property so it can be referenced in the importer.
                    $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
                    [$sql, $params] = fields::get_sql_fullname('u', $viewfullnames);

                    $sql1 = "
                            SELECT cm.*, $sql AS userfullname
                            FROM {cohort_members} cm
                            JOIN {user} u ON u.id = cm.userid
                            WHERE cm.cohortid = :cohortid
                        ";
                    $params['cohortid'] = $cohort->id;
                    $users = $DB->get_records_sql($sql1, $params);

                    foreach ($users as $user) {
                        $this->prepare_data_for_workplace_export('cohort_members', (array)$user)
                            ->add_mappings('userid', 'user')
                            ->add_mappings('cohortid', 'cohort')
                            ->export();
                    }
                }

                $export->export();
            }
        }
    }

    /**
     * Returns list of cohorts selected to export
     *
     * @param array $data
     * @return array
     */
    public function get_cohorts_to_export(array $data): array {
        global $DB;

        switch ($data[self::EXPORT_INSTANCES]) {
            case self::EXPORT_INSTANCES_ALL:
                // All cohorts user can access.
                $selected = $this->get_cohorts_list();
                $cohorts = $DB->get_records_list('cohort', 'id', array_keys($selected));
                break;
            case self::EXPORT_INSTANCES_ALL_SYSTEM:
                // All system cohorts.
                $cohorts = $DB->get_records('cohort', ['contextid' => \context_system::instance()->id]);
                break;
            case self::EXPORT_INSTANCES_CATEGORY:
                // Cohorts in selected category.
                $contexts = array_map(static function(int $categoryid): context_coursecat {
                    return context_coursecat::instance($categoryid);
                }, $this->get_export_setting(self::EXPORT_SELECT_CATEGORIES, []));

                // For each category context, we need to also retrieve the contexts of each sub-category (recursively).
                $allcontexts = [];
                foreach ($contexts as $context) {
                    $allcontexts = array_merge($allcontexts, self::get_subcategory_contexts($context));
                }

                // Now we can get our context ID's.
                $allcontextids = array_map(static function(context_coursecat $context): int {
                    return $context->id;
                }, $allcontexts);

                $cohorts = $DB->get_records_list('cohort', 'contextid', $allcontextids);
                break;
            case self::EXPORT_INSTANCES_SELECTED:
                // Manually selected cohorts.
                $cohorts = $DB->get_records_list('cohort', 'id', $data[self::EXPORT_SELECT_COHORTS]);
                break;
            default:
                throw new \coding_exception('Invalid export option');
        }

        // Check if user has permission to export these cohorts (TODO: improve, this is kind of inefficient).
        $listofcohorts = [];
        foreach ($cohorts as $cohort) {
            $context = \context::instance_by_id($cohort->contextid);
            if (has_capability('moodle/cohort:manage', $context)) {
                $listofcohorts[] = $cohort;
            }
        }

        return $listofcohorts;
    }

    /**
     * Recursively retrieve all sub-category contexts of given category context
     *
     * @param context_coursecat $context
     * @return context_coursecat[]
     */
    protected static function get_subcategory_contexts(context_coursecat $context): array {
        $contexts = [$context];
        foreach ($context->get_child_contexts() as $child) {
            if ($child instanceof context_coursecat) {
                $contexts = array_merge($contexts, self::get_subcategory_contexts($child));
            }
        }

        return $contexts;
    }

    /**
     * Return a list of course categories user can access
     *
     * @return string[]
     */
    private function get_cohort_category_list(): array {
        $categories = core_course_category::make_categories_list('moodle/cohort:manage');

        // If we are exporting for a tenant, filter categories to those within the tenant's own category.
        $tenantid = $this->get_export_tenant_id();
        if ($tenantid && $category = (new tenant($tenantid))->get_category()) {
            $categories = array_filter($categories, static function(int $categoryid) use ($category): bool {
                return $categoryid == $category->id || in_array($categoryid, $category->get_all_children_ids());
            }, ARRAY_FILTER_USE_KEY);
        }

        return $categories;
    }
}
