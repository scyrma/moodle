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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_catalogue\local;

use tool_catalogue\table\settings_table_base;

/**
 * Class admin_setting
 *
 * @package     tool_catalogue
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class setting_fields_list extends \admin_setting {

    /** @var string The class of the management table to use */
    protected string $tableclass;

    /**
     * Constructor
     *
     * @param string $tableclass The class of the management table to use
     * @param string $name The name of the setting
     * @param string $visiblename The visible name of the setting
     * @param string $description The description of the setting
     * @param string $defaultsetting The default value for the setting
     */
    public function __construct(
        string $tableclass,
        string $name,
        string $visiblename,
        string $description = '',
        string $defaultsetting = '',
    ) {
        parent::__construct($name, $visiblename, $description, $defaultsetting);
        $this->tableclass = $tableclass;
    }

    /**
     * Always returns true, does nothing
     *
     * @return true
     */
    public function get_setting(): bool {
        return true;
    }

    /**
     * Always returns true, does nothing
     *
     * @return true
     */
    public function get_defaultsetting(): bool {
        return true;
    }

    // phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
    /**
     * Always returns '', does not write anything
     *
     * @param mixed $data Unused
     * @return string Always returns ''
     */
    public function write_setting($data): string {
        // Do not write any setting.
        return '';
    }

    /**
     * Checks if $query is one of the available editors
     *
     * @param string $query The string to search for
     * @return bool Returns true if found, false if not
     */
    public function is_related($query) {
        if (parent::is_related($query)) {
            return true;
        }

        $classname = $this->tableclass;
        /** @var settings_table_base $table */
        $table = new $classname(basename($this->name));
        return $table->is_related($query);
    }

    /**
     * Builds the XHTML to display the control
     *
     * @param string $data Unused
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = ''): string {
        global $OUTPUT;
        $table = new $this->tableclass();
        if (!($table instanceof settings_table_base)) {
            throw new \coding_exception("{$this->tableclass} must be an instance of \\tool_catalogue\\table\\settings_table_base");
        }
        $description = strlen(trim($this->description ?? '')) ?
            $OUTPUT->container(highlight($query, $this->description), 'description') : '';
        return $OUTPUT->heading(highlight($query, $this->visiblename), 3, 'main') .
             highlight($query, $table->get_content()) .
             $description;
    }
}
