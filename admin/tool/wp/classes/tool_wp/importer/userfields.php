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
 * User profile fields importer
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\importer;

use moodle_exception;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\wp_imported_entity;

/**
 * Importer class
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class userfields extends importer_base {

    /** @var string Name to use for imported entity. */
    const ENTITY_NAME = 'userfields';

    /** @var string Element to define which user we are importing for. */
    const IMPORT_USERID = 'userid';

    /**
     * Importer format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_WORKPLACE;
    }

    /**
     * Importer name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('profilefields', 'admin');
    }

    /**
     * Define availability of importer, can only be used as a chained importer
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
     * Initialise the importer, register all entities/error/notices
     */
    protected function initialise() {
        $this->register_entity(self::ENTITY_NAME, [
            self::ENTITY_DEPENDENCIES => [],
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                if (empty($settings[self::IMPORT_USERID])) {
                    throw new moodle_exception('missingrequiredfield', 'error', '', null, self::IMPORT_USERID);
                }

                return [
                    self::IMPORT_USERID => $settings[self::IMPORT_USERID],
                ];
            },
            self::ENTITY_LOGSUCCESS => static function(int $id, array $details): ?string {
                return null;
            },
            self::ENTITY_LOGERROR => static function(array $details): string {
                return get_string('exportimportuserfieldserror', 'tool_wp', format_string($details['data']));
            },
            self::ENTITY_NAMEPLURAL => $this->get_name(),
        ]);

        // Register a notice if we encounter an unknown user (for instance if user mapper doesn't find user in import tenant).
        $this->register_potential_notice(self::ENTITY_NAME, 'unknownuser', [
            self::NOTICE_LOG => static function(array $details, array $noticedetails): string {
                return get_string('unknownuser');
            },
        ]);
    }

    /**
     * Add elements to the import form
     *
     * @param import_settings_form $form
     */
    public function add_to_options_form(import_settings_form $form): void {
        return;
    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importiscompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importiscompleted): string {
        return '';
    }

    /**
     * Summary of entities included in the workplace file (human-readable), displayed in the "Step 2 General settings"
     *
     * @return array
     */
    public function get_export_file_content_for_overview_page(): array {
        return [];
    }

    /**
     * Perform the import
     *
     * @param string $entityname
     * @return void
     */
    public function perform_import(string $entityname): void {
        if (strcmp($entityname, self::ENTITY_NAME) !== 0) {
            return;
        }

        // Retrieve any profile fields for the user currently being imported.
        $userfieldsdata = $this->get_entities_in_workplace_export_file(self::ENTITY_NAME, function(array $entity): bool {
            return $entity['userid'] == $this->get_import_setting(self::IMPORT_USERID);
        });

        foreach ($userfieldsdata as $userfielddata) {
            $userfielddata
                ->add_mapping('fieldid', 'userfield')
                ->set_validation_callback(function(array $data): void {
                    $this->add_details_to_log(['data' => $data['data']]);

                    // Make sure that userid field contains mappings to existing user.
                    if (!$this->get_mapping('user', $data['userid'], IGNORE_MISSING)) {
                        $this->add_notice_to_log('unknownuser');
                    }
                })
                ->set_import_callback(function(array $data, wp_imported_entity $entity): int {
                    global $DB;

                    $data['userid'] = $this->get_mapping('user', $data['userid']);

                    return $DB->insert_record('user_info_data', (object) $data);
                })
                ->import($this);
        }
    }
}
