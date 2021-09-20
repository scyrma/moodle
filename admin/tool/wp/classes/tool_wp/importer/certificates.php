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
 * Certificate issues importer
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\importer;

use core_course_category;
use core\output\notification;
use tool_certificate\permission;
use tool_certificate\persistent\element;
use tool_certificate\persistent\page;
use tool_certificate\persistent\template;
use tool_tenant\tenancy;
use tool_tenant\tenant;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\wp_imported_entity;

/**
 * Class certificates
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certificates extends importer_base {

    /** @var string Element for selecting if certificate templates have to be imported. */
    const IMPORT_CERTIFICATE_TEMPLATES = 'import_content';
    /** @var string Element for selecting if certificate issues have to be imported. */
    const IMPORT_CERTIFICATE_ISSUES = 'import_issued';
    /** @var string Element for selecting what to import. */
    const IMPORT_TYPE = 'import_instances';
    /** @var int Element for import all templates. */
    const IMPORT_TYPE_ALL = 'all';
    /** @var int Element for import manually selected templates. */
    const IMPORT_TYPE_SELECTED = 'selected';
    /** @var int Element for import manually selected templates. */
    const IMPORT_SELECTED_TEMPLATES = 'select_templates';
    /** @var string Element for selecting course category. */
    const IMPORT_SELECT_CATEGORY = 'select_category';

    /**
     * Importer format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_WORKPLACE;
    }

    /**
     * Does this importer "work" only within one tenant?
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return false;
    }

    /**
     * Importer name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('certificates', 'tool_wp');
    }

    /**
     * Define availability of this importer. Specifically, it requires the presence of classes inside tool_certificate
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return class_exists(template::class)
            && (!strlen($this->entrypoint) || $this->is_chained_entrypoint())
            && permission::can_create();
    }

    /**
     * Called when an instance of the importer is created
     *
     * Must register all entities, potential errors and potential notices by calling
     * $this->register_entity()
     * $this->register_potential_errors()
     * $this->register_potential_notices()
     */
    protected function initialise() {
        $this->register_entity('tool_certificate_templates', [
            self::ENTITY_DEPENDENCIES => [],
            self::ENTITY_LOGSUCCESS => function(int $id, array $details) {
                $details = (object) array_map('s', $details);
                $details->url = (new \moodle_url('/admin/tool/certificate/template.php', ['id' => $id]))->out();
                return get_string('importlogsuccesscertificates', 'tool_wp', $details);
            },
            self::ENTITY_LOGERROR => function(array $details) {
                $a = (object)['name' => format_string($details['name'])];
                return get_string('importlogerror', 'tool_wp', $a);
            },
            self::ENTITY_NAMEPLURAL => get_string('certificatetemplates', 'tool_wp'),
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                // What parameters to use when importing individual entity as part of some other import.
                // May be called from certifications and tenants import.
                $defaults = [
                    self::IMPORT_CERTIFICATE_TEMPLATES => 1,
                    self::IMPORT_CERTIFICATE_ISSUES => 1,
                ];
                return array_intersect_key($settings, $defaults) + $defaults +
                    [
                        self::IMPORT_TYPE => self::IMPORT_TYPE_SELECTED,
                        self::IMPORT_SELECTED_TEMPLATES => $ids,
                        self::IMPORT_SELECT_CATEGORY => $settings[self::IMPORT_SELECT_CATEGORY],
                    ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $logdetails, int $id) {
                return format_string($logdetails['name']);
            },
        ]);

        $this->register_entity('tool_certificate_issues', [
            self::ENTITY_DEPENDENCIES => ['tool_certificate_templates', 'user'],
            self::ENTITY_LOGSUCCESS => function(int $id, array $importerdetails) {
                $a = (object)[
                    'originaluserfullname' => $importerdetails['userfullname'],
                    'template' => $importerdetails['template'],
                ];
                return get_string('importlogsuccessissue', 'tool_wp', $a);
            },
            self::ENTITY_LOGERROR => function(array $importerdetails) {
                $fullname = $importerdetails['userfullname'] ?? $importerdetails['originaluserfullname'];
                $userfullname = format_string($fullname);
                return get_string('errorcouldnotimportissue', 'tool_wp', $userfullname);
            },
            self::ENTITY_NAMEPLURAL => get_string('entitycertificateissues', 'tool_wp'),
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                $fullname = $record['userfullname'] ?? $record['originaluserfullname'];
                return get_string('entitycertificateissueuser', 'tool_wp', format_string($fullname));
            },
        ]);

        $this->register_potential_notice('tool_certificate_issues', 'codechanged',
            [
                self::NOTICE_LOG => static function(array $details, array $noticedetails) {
                    $a = (object)array_map('s', $noticedetails);
                    return get_string('codechanged', 'tool_wp', $a);
                }
            ]);
    }

    /**
     * Add settings to the import form (Step 3. Options, "what to import")
     *
     * To retrive QuickForm:
     * $mform = $form->get_quick_form()
     * To add validation use:
     * $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_settings_form $form
     */
    public function add_to_options_form(import_settings_form $form): void {
        global $OUTPUT;

        $mform = $form->get_quick_form();
        $mform->addElement('header', 'headercertificates', $this->get_name());
        $mform->setExpanded('headercertificates');

        $templatesstr = get_string('certificatetemplatesdetails', 'tool_wp');
        $mform->addElement('advcheckbox', self::IMPORT_CERTIFICATE_TEMPLATES, $templatesstr);
        $mform->setDefault(self::IMPORT_CERTIFICATE_TEMPLATES, 1);
        $mform->addHelpButton(self::IMPORT_CERTIFICATE_TEMPLATES, 'import_content', 'tool_wp');
        $form->freeze_at(self::IMPORT_CERTIFICATE_TEMPLATES, 1);

        $templatesstr = get_string('import_issued', 'tool_wp');
        $mform->addElement('advcheckbox', self::IMPORT_CERTIFICATE_ISSUES, $templatesstr);
        $mform->setDefault(self::IMPORT_CERTIFICATE_ISSUES, 1);
        $mform->addHelpButton(self::IMPORT_CERTIFICATE_ISSUES, 'import_issued', 'tool_wp');
        if ($this->get_entities_in_workplace_export_file('tool_certificate_issues')->count() == 0) {
            $form->freeze_at(self::IMPORT_CERTIFICATE_ISSUES, 0);
        }

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $selectallstr = get_string('selectalltemplatesinfile', 'tool_wp');
        $selectmanuallystr = get_string('selectmanuallycertificates', 'tool_wp');
        $mform->addElement('radio', self::IMPORT_TYPE, null, $selectallstr, self::IMPORT_TYPE_ALL);
        $mform->addElement('radio', self::IMPORT_TYPE, null, $selectmanuallystr, self::IMPORT_TYPE_SELECTED);
        $mform->setType(self::IMPORT_TYPE, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_TYPE, self::IMPORT_TYPE_ALL);

        // Templates picker to allow user to limit which templates to import, sorted by fullname.
        $templateslistinfile = array_map('format_string',
            $this->get_entities_in_workplace_export_file(template::TABLE)->get_menu('name'));
        \core_collator::asort($templateslistinfile);
        $mform->addElement('autocomplete', self::IMPORT_SELECTED_TEMPLATES,
            get_string('certificatetemplates', 'tool_wp'),
            $templateslistinfile, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECTED_TEMPLATES, PARAM_INT);
        $mform->hideIf(self::IMPORT_SELECTED_TEMPLATES, self::IMPORT_TYPE, 'noteq', self::IMPORT_TYPE_SELECTED);

        $mform->addElement('header', 'destination', get_string('coursecategory'));
        $mform->setExpanded('destination');

        // Destination category picker. If tenant is specified only show categories for this tenant.
        $tenantid = $this->get_import_tenant_id();
        if (!$tenantid) {
            $systemcontext = \context_system::instance();
            $categories = ['' => ''];
            if (has_capability('tool/certificate:manage', $systemcontext)) {
                $categories += [0 => get_string('none')];
            }
            $categories += core_course_category::make_categories_list('tool/certificate:manage');
        } else {
            $categories = self::make_tenant_categories_list($tenantid);
        }

        // We need to ensure the select element is always added so it's reported in CLI & WS tools. If there are no categories
        // then add a warning and hide the form element (add 'd-none' class).
        if (empty($categories)) {
            $mform->addElement('static', 'nocategoriesavailable', '', $OUTPUT->render(
                (new notification(get_string('nocategoriesavailable', 'tool_wp'), notification::NOTIFY_ERROR))
                    ->set_show_closebutton(false)
                )
            );

            $selectcategoryattr = ['class' => 'd-none'];
        } else {
            $selectcategoryattr = [];
        }

        $mform->addElement('select', self::IMPORT_SELECT_CATEGORY, get_string('selectcoursecategory', 'tool_wp'), $categories,
            $selectcategoryattr)->setHiddenLabel(true);
        if ($tenantid && ($tenantcategory = tenancy::get_tenants()[$tenantid]->categoryid)) {
            $mform->setDefault(self::IMPORT_SELECT_CATEGORY, $tenantcategory);
        }

        // Manage course categories link.
        if (core_course_category::has_capability_on_any('moodle/category:manage')) {
            $manageurl = new \moodle_url('/course/management.php');
            $html = \html_writer::link($manageurl, get_string('managecoursecategories', 'tool_wp'));
            $mform->addElement('static', 'managecategories', '', $html);
        }

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

        if ($data[self::IMPORT_TYPE] === self::IMPORT_TYPE_SELECTED && empty($data[self::IMPORT_SELECTED_TEMPLATES])) {
            $errors[self::IMPORT_SELECTED_TEMPLATES] = get_string('selectatleastonetemplate', 'tool_wp');
        }

        // Check that user has restore capability on the selected destination course category. Note we differentiate between
        // 'null' (nothing selected), '' (empty string selected) and '0' (system level).
        if (($data[self::IMPORT_SELECT_CATEGORY] ?? '') === '') {
            $errors[self::IMPORT_SELECT_CATEGORY] = get_string('err_required', 'form');
        } else {
            $context = $data[self::IMPORT_SELECT_CATEGORY] == 0
                ? \context_system::instance()
                : \context_coursecat::instance($data[self::IMPORT_SELECT_CATEGORY]);

            if (!permission::can_manage($context)) {
                $errors[self::IMPORT_SELECT_CATEGORY] = get_string('nopermissioncategoryimport', 'tool_wp');
            }
        }

        return $errors;
    }

    /**
     * Returns the list of available tenant categories for this user
     *
     * @param int $tenantid
     * @return array List of strings with categories and subcategories
     */
    public static function make_tenant_categories_list(int $tenantid): array {
        $tenant = new tenant($tenantid);
        $categoryid = $tenant->get('categoryid');
        if (!$categoryid) {
            return [];
        }

        $allcategories = core_course_category::make_categories_list('tool/certificate:manage');
        $tenantcategory = core_course_category::get($categoryid);

        // We need to return the intersection of all categories user has appropriate capability, and tenant category children.
        $tenantcategories = array_intersect_key($allcategories,
            array_flip($tenantcategory->get_all_children_ids()));

        return [$tenantcategory->id => $tenantcategory->get_formatted_name()]
            + $tenantcategories;
    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importiscompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importiscompleted): string {
        global $OUTPUT;

        $data = $this->get_import_settings();

        // Settings.
        $settings = [];
        $exporttemplatesstr = get_string('certificatetemplatesdetails', 'tool_wp');
        $settings[] = ['name' => $exporttemplatesstr, 'value' => !empty($data[self::IMPORT_CERTIFICATE_TEMPLATES])];

        $exportissuesstr = get_string('import_issued', 'tool_wp');
        $settings[] = ['name' => $exportissuesstr, 'value' => !empty($data[self::IMPORT_CERTIFICATE_ISSUES])];

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
    }

    /**
     * Summary of entities included in the workplace file (human-readable), displayed in the "Step 2 General settings"
     *
     * Returns array of strings, where each string will be displayed as a separate 'static' element
     * in the form.
     *
     * @return array
     */
    public function get_export_file_content_for_overview_page(): array {
        $rv = [];
        if ($c1 = $this->get_entities_in_workplace_export_file('tool_certificate_templates')->count()) {
            $rv[] = get_string('certificates', 'tool_wp') .
                get_string('entitiescountpostfix', 'tool_wp', $c1);
        }
        if ($c2 = $this->get_entities_in_workplace_export_file('tool_certificate_issues')->count()) {
            $rv[] = get_string('entitycertificateissues', 'tool_wp') .
                get_string('entitiescountpostfix', 'tool_wp', $c2);
        }
        return $rv;
    }

    /**
     * Perform the import
     *
     * @param string $entity
     * @return void
     */
    public function perform_import(string $entity): void {
        if ($entity === 'tool_certificate_templates' && $this->get_import_setting(self::IMPORT_CERTIFICATE_TEMPLATES)) {
            $this->import_templates();
        }
    }

    /**
     * Import Certificate templates
     */
    protected function import_templates() {

        $settingimporttype = $this->get_import_setting(self::IMPORT_TYPE);
        if ($settingimporttype === null) {
            return;
        }

        // Check if we are importing all programs in the file or custom ones set on the import form.
        $filter = ($settingimporttype === self::IMPORT_TYPE_ALL) ?
            [] : $this->get_import_setting(self::IMPORT_SELECTED_TEMPLATES);
        $templateentities = $this->get_entities_in_workplace_export_file('tool_certificate_templates',
        static function(array $entity) use ($filter) {
            return (empty($filter) || in_array($entity['id'], $filter));
        });

        $targetcontextid = $this->get_import_contextid();

        foreach ($templateentities as $templateentity) {
            $templateentity
                ->add_mapping_callback('contextid', function() use ($targetcontextid) {
                    return $targetcontextid;
                })
                ->set_validation_callback(function(array $record, wp_imported_entity $entity) {
                    $this->add_details_to_log(['name' => $record['name']]);
                })
                ->set_import_callback(function($record, wp_imported_entity $templateentity) {
                    $templaterecord = array_intersect_key($record, template::properties_definition());
                    ($template = new template(0, (object) $templaterecord))->save();
                    $pagesimported = $this->import_template_pages($templateentity, $template->get('id'));
                    $this->add_details_to_log(['pagescount' => $pagesimported]);
                    $elementsimported = $this->import_template_page_elements($templateentity);
                    $this->add_details_to_log(['elementscount' => $elementsimported]);

                    return $template->get('id');
                })
                ->import($this);

            // After the template has been imported, we need to handle any shared images attached to elements.
            $templateentity->import_files([
                'contextid' => \context_system::instance()->id,
                'component' => 'tool_certificate',
                'filearea' => 'image',
                'itemid' => 0,
            ], false); // Don't skip the file exists check, because we don't want to try to recreate the file.

            // Check if we need to import certificate issues.
            if ($this->get_import_setting(self::IMPORT_CERTIFICATE_ISSUES) &&
                permission::can_issue_to_anybody(\context::instance_by_id($targetcontextid))) {

                $templateissues = $this->get_entities_in_workplace_export_file('tool_certificate_issues',
                static function(array $entity) use ($templateentity) {
                    return ((int)$entity['templateid'] === $templateentity->get_original_id());
                });

                // The new id can be "-1" if we are collecting errors.
                if ($templateentity->get_new_id() && !$this->is_collecting_errors()) {
                    $template = new template($templateentity->get_new_id());
                } else {
                    // Setting to null to avoid set_import_callback to fail.
                    $template = null;
                }

                foreach ($templateissues as $templateissue) {

                    $templateissue
                        ->add_mapping('userid', 'user')
                        ->add_mapping('templateid', template::TABLE)
                        ->set_validation_callback(function ($data, wp_imported_entity $entity) use ($template) {
                            $storeddata = json_decode($data['data']);
                            $this->add_details_to_log([
                                'code' => $data['code'],
                                'originaluserfullname' => $storeddata->userfullname ?? $data['userfullname'],
                            ]);
                            if ($data['userid'] > 0) {
                                $this->add_details_to_log(['userfullname' => fullname(\core_user::get_user($data['userid']))]);
                            }
                            if ($template) {
                                $this->add_details_to_log([
                                    'template' => $template->get('name'),
                                ]);
                            }
                        })
                        ->set_import_callback(function ($data, wp_imported_entity $entity) use ($template) {
                            global $DB;

                            $data['templateid'] = $template->get('id');
                            // Certificate issue code must be unique.
                            $newcode = \tool_certificate\certificate::generate_code();
                            $this->add_notice_to_log('codechanged', ['from' => $data['code'], 'to' => $newcode]);
                            $data['code'] = $newcode;

                            return $DB->insert_record('tool_certificate_issues', $data);

                        })->import($this);

                    if ($this->is_collecting_errors() || !$templateissue->get_new_id()) {
                        continue;
                    }

                    // Import certificate issues custom fields.
                    $this->process_chained_entities('customfield_data', [], [
                        'component' => 'tool_certificate',
                        'area' => 'issue',
                        'instancemapper' => 'tool_certificate_issues',
                        'instanceid' => $templateissue->get_original_id(),
                    ]);
                }
            }
        }
    }

    /**
     * Return selected import category context id
     *
     * @return int
     * @throws \dml_exception
     */
    private function get_import_contextid(): int {
        $categoryid = $this->get_import_setting(self::IMPORT_SELECT_CATEGORY);
        if ($categoryid) {
            return \context_coursecat::instance($categoryid, IGNORE_MISSING)->id;
        }

        return \context_system::instance()->id;
    }

    /**
     * Import template pages.
     * @param wp_imported_entity $templateentity
     * @param int $templatenewid
     * @return int
     */
    protected function import_template_pages(wp_imported_entity $templateentity, int $templatenewid): int {
        $pageentities = $templateentity->get_nested_entities(page::TABLE);
        foreach ($pageentities as $pageentity) {
            $oldpageid = $pageentity['_originalid'];
            $pagerecord = array_intersect_key($pageentity, page::properties_definition());
            ($page = new page(0, (object) $pagerecord))
                ->set('templateid', $templatenewid)
                ->save();
            $this->set_mapping(page::TABLE, $oldpageid, $page->get('id'));
        }
        return count($pageentities);
    }

    /**
     * Import template page elements.
     * @param wp_imported_entity $templateentity
     * @return int
     */
    protected function import_template_page_elements(wp_imported_entity $templateentity): int {
        $elemententities = $templateentity->get_nested_entities(element::TABLE);
        foreach ($elemententities as $elemententity) {
            $oldelementid = $elemententity['_originalid'];
            $elementrecord = array_intersect_key($elemententity, element::properties_definition());
            ($element = new element(0, (object)$elementrecord))
                ->set('pageid', $this->get_mapping(page::TABLE, $elementrecord['pageid']))
                ->save();
            $this->set_mapping(element::TABLE, $oldelementid, $element->get('id'));
        }

        // Import element files.
        $templateentity->import_files_for_itemid([
            'component' => 'tool_certificate',
            'newcontextid' => $this->get_import_contextid(),
        ], element::TABLE);

        return count($elemententities);
    }
}
