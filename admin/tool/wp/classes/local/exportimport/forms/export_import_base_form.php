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
 * Class export_import_base_form
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\forms;

use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for all export and import forms
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class export_import_base_form extends modal_form {
    /** @var int */
    protected $stage;

    /**
     * Add action buttons back/cancel/next
     *
     * @param string|null $nextlabel
     * @param bool $hasbackbutton
     */
    protected function add_buttons(?string $nextlabel = null, bool $hasbackbutton = true) {
        $mform = $this->_form;

        $buttonarray = array();
        $nextlabel = $nextlabel ?: get_string('next');
        if ($hasbackbutton) {
            $previouslabel = get_string('previous');
            $buttonarray[] = $previous = $mform->createElement('submit', 'prevbutton', $previouslabel,
                $this->prev_button_attributes(), false, ['customclassoverride' => 'btn-outline-secondary']);
        }
        $buttonarray[] = $mform->createElement('cancel');
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', $nextlabel);
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Exposes $this->_form object (usually called $mform)
     *
     * @return \MoodleQuickForm
     */
    public function get_quick_form(): \MoodleQuickForm {
        return $this->_form;
    }

    /**
     * Add validation callback to the form
     *
     * @param callable $callback function(array $data, array $files) similar to {@see moodleform::validation()}
     * @param mixed ...$params One or more params to pass to callback.
     */
    public function add_validation_callback(callable $callback, ...$params) {
        $this->_form->addFormRule(function($data, $files) use ($callback, $params) {
            return $callback($this->_form->exportValues(), $files, ...$params);
        });
    }

    /**
     * Finds an element by name, also searches inside groups
     *
     * @param string $elementname
     * @return \HTML_QuickForm_element
     * @throws \coding_exception
     */
    protected function find_element(string $elementname): \HTML_QuickForm_element {
        if (array_key_exists($elementname, $this->get_quick_form()->_elementIndex)) {
            $idx = $this->get_quick_form()->_elementIndex[$elementname];
            return $this->get_quick_form()->_elements[$idx];
        }
        foreach ($this->get_quick_form()->_elements as $element) {
            if ($element instanceof \HTML_QuickForm_group) {
                $elements = $element->getElements();
                foreach ($elements as $key => $groupelement) {
                    if ($element->getElementName($key) === $elementname) {
                        return $groupelement;
                    }
                }
            }
        }
        throw new \coding_exception('Element with name '.$elementname.' does not exist');
    }

    /**
     * Convenient function that freezes an element both in UI and in the backend, also handles elements in groups
     *
     * @param string $elementname
     * @param mixed $value
     */
    public function freeze_at(string $elementname, $value) {
        if ($value === false) {
            throw new \coding_exception('Frozen value can not be false, use 0 instead');
        }

        // Find the element, if we are setting a disabled value then append string/class to it.
        $element = $this->find_element($elementname);
        if (!$value) {
            $element->updateAttributes(['class' => 'dimmed_text']);
            $element->setLabel(new \lang_string('exportimportentityunavailable', 'tool_wp', $element->getLabel()));
        }

        $element->freeze();
        $this->_form->_constantValues = \HTML_QuickForm::arrayMerge($this->_form->_constantValues,
            [$elementname => $value]);
        $element->onQuickFormEvent('updateValue', null, $this->_form);
    }

    /**
     * Checks if the element is locked (set to constant)
     *
     * @param string $elementname
     * @return bool|mixed
     */
    protected function is_locked(string $elementname) {
        if (array_key_exists($elementname, $this->_form->_constantValues)) {
            return $this->_form->_constantValues[$elementname];
        }
        return false;
    }

    /**
     * Is form option ignored in CLI export/import
     *
     * @param \HTML_QuickForm_element $element
     * @param bool $withheaders do not ignore headers
     * @return bool
     */
    protected function is_form_element_ignored_for_cli(\HTML_QuickForm_element $element, bool $withheaders) {
        $type = $element->getType();
        if ($type === 'header') {
            return !$withheaders;
        }
        if ($element instanceof \HTML_QuickForm_submit || $element instanceof \HTML_QuickForm_static) {
            return true;
        }
        if ($type === 'html' || $type === 'hidden') {
            return true;
        }
        $name = $element->getName();
        if ($name === null) {
            return false;
        }
        return in_array($name, ['exporter', 'importid', 'entrypoint', 'entrypointid', 'sesskey', 'buttonar'])
            || preg_match('/^mform_isexpanded_/', $name)
            || preg_match('/^_qf__/', $name);
    }

    /**
     * Returns an array of available options in this form to be used in CLI with their default values
     *
     * @return array key is the option (element) name and value is a default value
     */
    public function get_elements_defaults_for_cli() {
        $elements = $this->get_form_elements_for_cli();
        $options = [];
        $defaults = $this->get_quick_form()->_defaultValues;
        foreach ($elements as $element) {
            if ($this->is_locked($element->getName()) !== false) {
                // When an element is locked, do not display it.
                continue;
            } else if (array_key_exists($element->getName(), $defaults)) {
                $options[$element->getName()] = $defaults[$element->getName()];
            } else if (array_key_exists($element->getName(), $options)) {
                // Ignore radio inputs that are not default or first.
                continue;
            } else if ($element instanceof \MoodleQuickForm_radio) {
                $options[$element->getName()] = $element->getValue();
            } else if ($element instanceof \HTML_QuickForm_checkbox) {
                $options[$element->getName()] = 0;
            } else if ($element instanceof \HTML_QuickForm_select && $element->getMultiple()) {
                $options[$element->getName()] = [];
            } else {
                $options[$element->getName()] = null;
            }
        }
        return $options;
    }

    /**
     * Returns an array of available elements in this form to be used in CLI
     *
     * @return array array where each element looks like this: ["$optionname=$value", $description]
     */
    public function get_form_elements_cli_help(): array {
        $elements = $this->get_form_elements_for_cli(true);
        $options = [];
        foreach ($elements as $element) {
            if ($element instanceof \HTML_QuickForm_header) {
                $options[] = ['-- ' . $element->_text . ' --', null];
                continue;
            }
            $label = $element->getLabel();
            if (!strlen($label) && method_exists($element, 'getText')) {
                $label = $element->getText();
            }
            if (($value = $this->is_locked($element->getName())) !== false) {
                if ($element instanceof \MoodleQuickForm_radio && $value != $element->getValue()) {
                    continue;
                }
                $label .= ' (Locked)';
            } else if ($element instanceof \MoodleQuickForm_radio) {
                $value = $element->getValue();
            } else if ($element instanceof \HTML_QuickForm_checkbox) {
                $value = '0|1';
            } else if ($element instanceof \HTML_QuickForm_select && $element->getMultiple()) {
                $value = '[...]';
            } else {
                // Usually single select or a text input.
                $value = '...';
            }
            $options[] = ["{$element->getName()}={$value}", $label];
        }
        return $options;
    }

    /**
     * Returns the list of form elements, traversing into groups, excluding hidden/buttons/etc
     *
     * See also {@see is_form_element_ignored_for_cli()}
     *
     * @param bool $returnheaders return or ignore 'header' elements (we need them for display but do not need them
     *     for actual settings)
     * @return \HTML_QuickForm_element[]
     */
    protected function get_form_elements_for_cli(bool $returnheaders = false): array {
        $elements = [];
        foreach ($this->get_quick_form()->_elements as $element) {
            if ($element instanceof \HTML_QuickForm_group) {
                foreach ($element->_elements as $subelement) {
                    $elements[] = $subelement;
                }
            } else {
                $elements[] = $element;
            }
        }
        $elements = array_filter($elements, function($element) use ($returnheaders) {
            return ($element instanceof \HTML_QuickForm_element &&
                !$this->is_form_element_ignored_for_cli($element, $returnheaders));
        });
        return array_values($elements);
    }

    /**
     * Mock the form submission.
     *
     * @param array $formdata
     * @return \stdClass $form
     */
    final public static function mock_form_submission($formdata) {
        global $USER;
        $formclass = get_called_class();
        $formidentifier = str_replace('\\', '_', $formclass);
        $formdata['_qf__' . $formidentifier] = 1;
        $oldignoresesskey = $USER->ignoresesskey ?? null;
        $USER->ignoresesskey = true;
        $form = new $formclass(null, null, 'post', '', [], true, $formdata, true);
        $USER->ignoresesskey = $oldignoresesskey;
        $form->set_data_for_modal();
        return $form;
    }
}
