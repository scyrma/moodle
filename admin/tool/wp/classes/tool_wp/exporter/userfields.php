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
 * User profile fields exporter
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\exporter;

use moodle_exception;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;

/**
 * Exporter class
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class userfields extends exporter_base {

    /** @var string Name to use for exported entity. */
    const ENTITY_NAME = 'userfields';

    /** @var string Element to define which user we are exporting for. */
    const EXPORT_USERID = 'userid';

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
        return get_string('profilefields', 'admin');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return '';
    }

    /**
     * Define availability of exporter, can only be used as a chained exporter
     *
     * @return bool
     */
    public function is_available(): bool {
        return $this->is_chained_entrypoint();
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
     * Initialise the exporter, register all entities
     */
    public function initialise() {
        $this->register_entity(self::ENTITY_NAME, [
            self::ENTITY_INDIVIDUALEXPORT => static function(array $ids, array $settings): array {
                if (empty($settings[self::EXPORT_USERID])) {
                    throw new moodle_exception('missingrequiredfield', 'error', '', null, self::EXPORT_USERID);
                }

                return [
                    self::EXPORT_USERID => $settings[self::EXPORT_USERID],
                ];
            },
        ]);
    }

    /**
     * Add elements to the export form
     *
     * @param export_settings_form $form
     */
    public function add_to_options_form(export_settings_form $form): void {
        return;
    }

    /**
     * Summary of the export settings for the review step and also for the report page
     *
     * @param bool $exportcompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $exportcompleted): string {
        return '';
    }

    /**
     * Returns the list of entities that will be exported
     *
     * @param string $entityname
     * @return array[]
     */
    public function get_instances_for_review_step(string $entityname): array {
        return [];
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        global $DB;

        $userfieldsdata = $DB->get_records('user_info_data', ['userid' => $this->get_export_setting(self::EXPORT_USERID)]);
        foreach ($userfieldsdata as $userfielddata) {
            $this->prepare_data_for_workplace_export(self::ENTITY_NAME, (array) $userfielddata)
                ->add_mappings('userid', 'user')
                ->add_mappings('fieldid', 'userfield')
                ->export();
        }
    }
}
