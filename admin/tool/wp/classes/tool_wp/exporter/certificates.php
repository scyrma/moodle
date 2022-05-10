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
 * Certificate issues exporter
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín <mikel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\exporter;

use context_coursecat;
use context_system;
use core_course_category;
use tool_certificate\permission;
use tool_certificate\persistent\element;
use tool_certificate\persistent\page;
use tool_certificate\persistent\template;
use tool_tenant\tenant;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\local\exportimport\helper;

/**
 * Class certificates
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín <mikel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certificates extends exporter_base {

    /** @var string Element for selecting what to export. */
    const EXPORT_TYPE = 'export_instances';
    /** @var string Element for selecting if certificate templates have to be exported. */
    const EXPORT_CERTIFICATE_TEMPLATES = 'export_content';
    /** @var string Element for selecting if issued certificates have to be exported. */
    const EXPORT_ISSUED_CERTIFICATES = 'export_issued';
    /** @var string Element for selecting which categories to export templates from. */
    const EXPORT_SELECTED_CATEGORIES = 'select_categories';
    /** @var string Element for selecting what templates to export. */
    const EXPORT_SELECTED_TEMPLATES = 'select_templates';
    /** @var int Element for export all templates that the user is able to access. */
    const EXPORT_TYPE_ALL = 'all';
    /** @var int Element for export manually selected categories. */
    const EXPORT_TYPE_CAT_MANUALLY = 'incategories';
    /** @var int Element for export manually selected templates. */
    const EXPORT_TYPE_MANUALLY = 'selected';

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
        return get_string('certificates', 'tool_wp');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exporterdesc', 'tool_wp');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/certificates', 'theme')->out(false);
    }

    /**
     * Define availability of this exporter. Specifically, it requires the presence of classes inside tool_certificate
     *
     * By default every exporter is avilable for general export and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return class_exists(template::class)
            && (!strlen($this->entrypoint) || $this->is_chained_entrypoint())
            && permission::can_create();
    }

    /**
     * Does this exporter "work" only within one tenant?
     *
     * For example, departments, programs, etc can only exist inside a tenant - return true,
     * but course categories, courses, cohorts can exist outside of tenants - return false.
     *
     * The export of tenants themselves should return false.
     *
     * Exporters can override.
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return false;
    }

    /**
     * Initialise the class, register entities that can be exported from other places
     */
    protected function initialise() {
        $this->register_entity('tool_certificate_templates', [
            self::ENTITY_INDIVIDUALEXPORT => function(array $ids, array $settings) {
                // What parameters to use when exporting individual entity as part of some other export.
                // May be called from tenants export.
                $defaults = [
                    self::EXPORT_TYPE => self::EXPORT_TYPE_MANUALLY,
                    self::EXPORT_CERTIFICATE_TEMPLATES => 1,
                    self::EXPORT_ISSUED_CERTIFICATES => 1,
                    self::EXPORT_SELECTED_CATEGORIES => [],
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::EXPORT_SELECTED_TEMPLATES => $ids,
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return format_string($record['name']);
            },
        ]);
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

        $mform->addElement('header', 'content', get_string('content', 'tool_wp'));
        $mform->setExpanded('content');

        $templatesstr = get_string('export_content', 'tool_wp');
        $mform->addElement('advcheckbox', self::EXPORT_CERTIFICATE_TEMPLATES, $templatesstr);
        $mform->setDefault(self::EXPORT_CERTIFICATE_TEMPLATES, 1);
        $mform->addHelpButton(self::EXPORT_CERTIFICATE_TEMPLATES, 'export_content', 'tool_wp');
        $form->freeze_at(self::EXPORT_CERTIFICATE_TEMPLATES, 1);

        $templatesstr = get_string('export_issued', 'tool_wp');
        $mform->addElement('advcheckbox', self::EXPORT_ISSUED_CERTIFICATES, $templatesstr);
        $mform->setDefault(self::EXPORT_ISSUED_CERTIFICATES, 1);
        $mform->addHelpButton(self::EXPORT_ISSUED_CERTIFICATES, 'export_issued', 'tool_wp');

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $selectallstr = get_string('selectalltemplates', 'tool_wp');
        $selectcatmanuallystr = get_string('selectmanuallycategories', 'tool_wp');
        $selectmanuallystr = get_string('selectmanuallycertificates', 'tool_wp');
        $mform->addElement('radio', self::EXPORT_TYPE, null, $selectallstr, self::EXPORT_TYPE_ALL);
        $mform->addElement('radio', self::EXPORT_TYPE, null, $selectcatmanuallystr, self::EXPORT_TYPE_CAT_MANUALLY);
        // Categories picker.
        $mform->addElement('autocomplete', self::EXPORT_SELECTED_CATEGORIES, '', $this->get_template_categories_list(),
            ['multiple' => true]);
        $mform->setType(self::EXPORT_SELECTED_CATEGORIES, PARAM_INT);
        $mform->hideIf(self::EXPORT_SELECTED_CATEGORIES, self::EXPORT_TYPE, 'noteq', self::EXPORT_TYPE_CAT_MANUALLY);

        $mform->addElement('radio', self::EXPORT_TYPE, null, $selectmanuallystr, self::EXPORT_TYPE_MANUALLY);
        // Templates picker.
        $mform->addElement('autocomplete', self::EXPORT_SELECTED_TEMPLATES, '', $this->get_templates_list(), ['multiple' => true]);
        $mform->setType(self::EXPORT_SELECTED_TEMPLATES, PARAM_INT);
        $mform->hideIf(self::EXPORT_SELECTED_TEMPLATES, self::EXPORT_TYPE, 'noteq', self::EXPORT_TYPE_MANUALLY);

        $mform->setType(self::EXPORT_TYPE, PARAM_ALPHANUM);
        $mform->setDefault(self::EXPORT_TYPE, self::EXPORT_TYPE_ALL);

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Perform some extra moodle validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        if ($data[self::EXPORT_TYPE] === self::EXPORT_TYPE_MANUALLY && empty($data[self::EXPORT_SELECTED_TEMPLATES])) {
            $errors[self::EXPORT_SELECTED_TEMPLATES] = get_string('selectatleastonetemplate', 'tool_wp');
        }
        if ($data[self::EXPORT_TYPE] === self::EXPORT_TYPE_CAT_MANUALLY && empty($data[self::EXPORT_SELECTED_CATEGORIES])) {
            $errors[self::EXPORT_SELECTED_CATEGORIES] = get_string('selectatleastonecategory', 'tool_wp');
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

        // Settings.
        $settings = [];
        $exporttemplatesstr = get_string('certificatetemplatesdetails', 'tool_wp');
        $settings[] = ['name' => $exporttemplatesstr, 'value' => !empty($data[self::EXPORT_CERTIFICATE_TEMPLATES])];

        $exportissuesstr = get_string('export_issued', 'tool_wp');
        $settings[] = ['name' => $exportissuesstr, 'value' => !empty($data[self::EXPORT_ISSUED_CERTIFICATES])];

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
        if ($entityname === 'tool_certificate_templates') {
            return array_map(function(template $template) {
                return $template->to_record();
            }, $this->get_templates_to_export());
        }
        return [];
    }

    /**
     * Recursively retrieve all sub-category contexts of given category context
     *
     * @param \context_coursecat $context
     * @return \context_coursecat[]
     */
    protected static function get_subcategory_contexts(\context_coursecat $context): array {
        $contexts = [$context];
        foreach ($context->get_child_contexts() as $child) {
            if ($child instanceof \context_coursecat) {
                $contexts = array_merge($contexts, self::get_subcategory_contexts($child));
            }
        }

        return $contexts;
    }

    /**
     * Returns list of templates selected to export
     *
     * @return template[]
     */
    public function get_templates_to_export(): array {
        global $DB;

        $templates = [];

        switch ($this->get_export_setting(self::EXPORT_TYPE)) {
            case self::EXPORT_TYPE_ALL:
                $selected = $this->get_templates_list();

                if (!empty($selected)) {
                    [$where, $params] = $DB->get_in_or_equal(array_keys($selected), SQL_PARAMS_NAMED);
                    $templates = template::get_records_select("id $where", $params);
                }
                break;
            case self::EXPORT_TYPE_MANUALLY:
                $selected = $this->get_export_setting(self::EXPORT_SELECTED_TEMPLATES);

                if (!empty($selected)) {
                    [$where, $params] = $DB->get_in_or_equal($selected, SQL_PARAMS_NAMED);
                    $templates = template::get_records_select("id $where", $params);
                }
                break;
            case self::EXPORT_TYPE_CAT_MANUALLY:
                $categories = $this->get_export_setting(self::EXPORT_SELECTED_CATEGORIES, []);
                if (empty($categories)) {
                    return [];
                }

                $contexts = array_map(static function(int $categoryid): \context_coursecat {
                    return \context_coursecat::instance($categoryid);
                }, $categories);

                // For each category context, we need to also retrieve the contexts of each sub-category (recursively).
                $allcontexts = [];
                foreach ($contexts as $context) {
                    $allcontexts = array_merge($allcontexts, self::get_subcategory_contexts($context));
                }

                // Now we can get our context ID's.
                $allcontextids = array_map(static function(\context_coursecat $context): int {
                    return $context->id;
                }, $allcontexts);

                [$where, $params] = $DB->get_in_or_equal($allcontextids, SQL_PARAMS_NAMED);
                $templates = template::get_records_select("contextid $where", $params);

                break;
            default:
                throw new \coding_exception('Invalid export option');
        }

        return $templates;
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {

        // Check whether user has selected to export templates.
        if (!empty($this->get_export_setting(self::EXPORT_TYPE))) {
            // Get all templates.
            foreach ($this->get_templates_to_export() as $template) {

                // Get all pages.
                $pages = page::get_records(['templateid' => $template->get('id')]);
                $elements = $this->get_pages_elements($pages);

                $excludefields = ['timecreated', 'timemodified'];
                $exportedentity = $this->prepare_data_for_workplace_export(template::TABLE, (array)$template->to_record())
                    ->add_mappings('contextid', 'context')
                    ->add_nested_entities(page::TABLE, helper::persistents_to_array($pages), $excludefields)
                    ->add_mappings_for_nested_entities(page::TABLE, 'templateid', template::TABLE)
                    ->add_nested_entities(element::TABLE, helper::persistents_to_array($elements), $excludefields)
                    ->add_mappings_for_nested_entities(element::TABLE, 'pageid', page::TABLE)
                    ->exclude_fields($excludefields);

                // Export element files (note: they can be stored in multiple file areas).
                foreach ($elements as $element) {
                    $instance = \tool_certificate\element::instance(0, $element->to_record());

                    $exportedentity->add_area_files(
                        $instance->get_template()->get_context(),
                        'tool_certificate',
                        'element',
                        $instance->get_id()
                    );
                    $exportedentity->add_area_files(
                        $instance->get_template()->get_context(),
                        'tool_certificate',
                        'elementaux',
                        $instance->get_id()
                    );

                    // Image elements can have shared files.
                    if ($instance instanceof \certificateelement_image\element && $file = $instance->get_shared_file()) {
                        $exportedentity->add_file($file);
                    }
                }
                $exportedentity->export();

                // Check if issued certificates have to be exported.
                if ($this->get_export_setting(self::EXPORT_ISSUED_CERTIFICATES) &&
                    permission::can_issue_to_anybody(\context::instance_by_id($template->get('contextid')))) {
                    $issuedcertificates = $this->get_issued_certificates($template->get('id'));

                    foreach ($issuedcertificates as $issuedcertificate) {
                        $this->prepare_data_for_workplace_export('tool_certificate_issues', (array) $issuedcertificate)
                            ->exclude_fields(['timemodified'])
                            ->add_mappings('userid', 'user')
                            ->add_mappings('templateid', template::TABLE)
                            ->export();

                        // Export certificate issue custom fields.
                        $this->process_chained_entities('customfield_data', [], [
                            'component' => 'tool_certificate',
                            'area' => 'issue',
                            'instanceid' => $issuedcertificate['id'],
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Get all elements in template pages.
     * @param array $pages
     * @return element[]
     */
    private function get_pages_elements(array $pages): array {
        global $DB;

        $pagesids = array_map(
            function($p) {
                return $p->get('id');
            }, $pages) ?? [];
        [$insql, $inparams] = $DB->get_in_or_equal($pagesids, SQL_PARAMS_QM, 'param', true, true);
        return element::get_records_select("pageid {$insql}", $inparams);
    }

    /**
     * Returns the list of issued certificates for a given certificate template id
     *
     * @param int $templateid
     * @return array
     */
    private function get_issued_certificates(int $templateid): array {
        global $DB;

        // Filter issues by components from UI: tool_certificate and tool_dynamicrule.
        $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        [$sql, $params] = \tool_reportbuilder\db::sql_fullname('u', $viewfullnames);
        $sql1 = "
            SELECT ci.*, $sql AS userfullname
            FROM {tool_certificate_issues} ci
            JOIN {user} u ON u.id = ci.userid
            WHERE ci.templateid = :templateid
            AND (ci.component = 'tool_certificate' OR ci.component = 'tool_dynamicrule')
        ";
        $params['templateid'] = $templateid;
        $issuedcertificates = $DB->get_records_sql($sql1, $params);

        return array_map(function($object) {
            return (array) $object;
        }, array_values($issuedcertificates));
    }

    /**
     * Returns a list of templates the user is able to export
     *
     * @return string[] array of template ID => name
     */
    private function get_templates_list(): array {
        global $DB;

        // Include templates from all categories user can access.
        $contextids = [];
        foreach ($this->get_template_categories_list() as $categoryid => $name) {
            $contextids[] = context_coursecat::instance($categoryid)->id;
        }

        // If they can view templates at the system level, include those too.
        $contextsystem = context_system::instance();
        if (permission::can_view_templates_in_context($contextsystem)) {
            $contextids[] = $contextsystem->id;
        }

        if (empty($contextids)) {
            return [];
        }

        [$select, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        return $DB->get_records_select_menu('tool_certificate_templates', "contextid {$select}", $params, 'name', 'id,name');
    }

    /**
     * Returns a list of course categories user can access
     *
     * @return string[] array of category ID => name
     */
    private function get_template_categories_list(): array {
        $categories = core_course_category::make_categories_list();

        // If we are exporting for a tenant, filter categories to those within the tenant's own category.
        $tenantid = $this->get_export_tenant_id();
        if ($tenantid && $category = (new tenant($tenantid))->get_category()) {
            $categories = array_filter($categories, static function(int $categoryid) use ($category): bool {
                return ($categoryid == $category->id || in_array($categoryid, $category->get_all_children_ids()))
                    && permission::can_view_templates_in_context(context_coursecat::instance($categoryid));
            }, ARRAY_FILTER_USE_KEY);
        }

        return $categories;
    }
}
