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
 * Class exporter_selector_form
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\forms;

use core_collator;
use core\output\notification;
use tool_tenant\tenancy;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\helper;

/**
 * Step 1. Select exporter
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class exporter_selector_form extends export_base_form {
    /** @var int */
    protected $stage = 1;

    /**
     * Form definition
     */
    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $mform->setDisableShortforms();

        // Get list of available exporters, it empty then notify user and exit early.
        $exporters = helper::get_all_exporters();
        if (empty($exporters)) {
            $mform->addElement('html', $OUTPUT->render(
                (new notification(get_string('exportersunavailable', 'tool_wp'), notification::NOTIFY_ERROR))
                    ->set_show_closebutton(false)
                )
            );

            return;
        }

        $mform->addElement('header', 'exporterheader', get_string('selectexporter', 'tool_wp'));

        $mform->addElement('hidden', 'entrypoint');
        $mform->setType('entrypoint', PARAM_ALPHANUMEXT);

        $mform->addElement('hidden', 'entrypointid');
        $mform->setType('entrypointid', PARAM_INT);

        $elements = [];

        core_collator::asort_objects_by_method($exporters, 'get_name');
        foreach ($exporters as $plugin) {
            $type = $plugin->is_format(exporter_base::FORMAT_WORKPLACE) ? get_string('csvwpcolumn', 'tool_wp') : '';
            $exporterdesc = $OUTPUT->render_from_template('tool_wp/exporterimporter_selector', ['name' => $plugin->get_name(),
                'type' => $type, 'iconurl' => $plugin->get_icon_url(), 'description' => $plugin->get_description()]
            );

            $exporterclass = get_class($plugin);
            $elements[] = $mform->createElement('radio', 'exporter', '', $exporterdesc, $exporterclass);

            // If user can switch tenant and no tenant is required, then allow them to create export for site or current tenant.
            if (tenancy::is_site_multi_tenant() && \tool_tenant\permission::can_switch_tenant() && !$plugin->is_tenant_required() &&
                    $exporterclass !== \tool_wp\tool_wp\exporter\site::class) {

                $strexportcreatefrom = get_string('exportercreatefrom', 'tool_wp');

                $tenantselect = [
                    $mform->createElement('html', $strexportcreatefrom),
                    $mform->createElement('select', "exportertenant_$exporterclass", $strexportcreatefrom, [
                        0 => get_string('site'),
                        tenancy::get_tenant_id() => get_string('exportercreatefromcurrenttenant', 'tool_wp'),
                    ]),
                    $mform->createElement('html', $OUTPUT->help_icon('exportercreatefrom', 'tool_wp')),
                ];
                $elements[] = $mform->createElement('group', "exportertenantgroup[$exporterclass]", '', $tenantselect,
                    '&nbsp;', false);
                $mform->hideIf("exportertenantgroup[$exporterclass]", 'exporter', 'neq', $exporterclass);
            }
        }

        $mform->addGroup($elements, 'exporters', '', \html_writer::div('', 'w-100'), false);

        // Get the first exporter, and set it as the default.
        $firstexporter = reset($exporters);
        $mform->setDefault('exporter', get_class($firstexporter));

        $this->add_buttons(null, false);
    }

    /**
     * Allow exporter to perform validation on their form data
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['exporter'])) {
            $errors['exporters'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Process the form submission
     *
     * @return int|mixed
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $processed = parent::process_dynamic_submission();
        $exporter = $processed['exporter'];

        // For convenience, we'll simplify the exporter tenant property name rather than using that returned from the form.
        return array_merge($processed, [
            'exportertenant' => $data->{"exportertenant_{$exporter}"} ?? 0,
        ]);
    }

    /**
     * Access validation
     *
     * @throws \moodle_exception
     */
    public function check_access_for_dynamic_submission(): void {
        \tool_wp\permission::require_can_use_export_import();
    }
}
