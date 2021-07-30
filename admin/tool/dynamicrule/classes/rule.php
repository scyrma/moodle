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
 * Rule persistent.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die();

/**
 * Rule persistent class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class rule extends \core\persistent {

    /** @var string table. */
    const TABLE = 'tool_dynamicrule';

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param \stdClass $record If set will be passed to {@see self::from_record()}.
     */
    public function __construct(int $id = 0, \stdClass $record = null) {
        if ($record) {
            $record = (object)array_intersect_key((array)$record, self::properties_definition());
        }
        if ($id && $record) {
            debugging('Either id or record need to be specified in the persistent constructor but not both',
                DEBUG_DEVELOPER);
        }
        parent::__construct($id, $record);
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'name' => array(
                'type' => PARAM_TEXT,
                'description' => 'The rule name.',
            ),
            'tenantid' => array(
                'type' => PARAM_INT,
                'description' => 'Tenant id.',
                'default' => 0,
            ),
            'enabled' => array(
                'type' => PARAM_INT,
                'description' => 'Only enabled rules are processed.',
                'default' => 0,
            ),
            'archived' => array(
                'type' => PARAM_INT,
                'description' => 'Archived rules are available to reports only.',
                'default' => 0,
            ),
            'matchlimit' => array(
                'type' => PARAM_INT,
                'description' => 'How many times the rule can be triggered within matchinterval.',
                'default' => 0,
            ),
            'matchinterval' => array(
                'type' => PARAM_INT,
                'description' => 'Interval in seconds for which matchlimit is applied.',
                'default' => 0,
            ),
            'timecreated' => array(
                'type' => PARAM_INT,
                'description' => 'Time the rule was created.',
            ),
            'timemodified' => array(
                'type' => PARAM_INT,
                'description' => 'Time the rule was modified.',
            ),
            'component' => array(
                'type' => PARAM_COMPONENT,
                'description' => 'Component the rule belongs to.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'componentarea' => array(
                'type' => PARAM_TEXT,
                'description' => 'Component area the rule belongs to.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'itemid' => array(
                'type' => PARAM_INT,
                'description' => 'Component instance the rule belongs to.',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
        );
    }

    /**
     * Return the formatted name.
     *
     * @return string
     */
    public function get_formatted_name() : string {
        return format_string($this->get('name'), true, ['context' => \context_system::instance(), 'escape' => false]);
    }

    /**
     * Return if the rule is enabled.
     *
     * @return bool
     */
    public function is_enabled() : bool {
        return (bool)$this->get('enabled');
    }

    /**
     * Return if the rule is archived.
     *
     * @return bool
     */
    public function is_archived() : bool {
        return (bool)$this->get('archived');
    }

    /**
     * Return if the rule is broken.
     * @deprecated in WP-1583
     *
     * @return bool
     */
    public function is_broken() : bool {
        debugging('Function is_broken() should not be used', DEBUG_DEVELOPER);
    }

    /**
     * Returns true if rule has conditions, false otherwise.
     *
     * @return bool
     */
    public function has_conditions() : bool {
        return condition::record_exists_select('ruleid = ?', [$this->get('id')]);
    }

    /**
     * Returns true if rule has outcomes, false otherwise.
     *
     * @return bool
     */
    public function has_outcomes() : bool {
        return outcome::record_exists_select('ruleid = ?', [$this->get('id')]);
    }

    /**
     * Hook to execute after rule a delete.
     *
     * This cleans up conditions, matches and outcomes.
     *
     * @param bool $result Whether or not the delete was successful.
     * @return void
     */
    protected function after_delete($result): void {
        global $DB;
        if ($result) {
            // If rule deletion was successful, delete related records in other tables.
            $DB->delete_records(condition::TABLE, ['ruleid' => $this->get('id')]);
            $DB->delete_records(outcome::TABLE, ['ruleid' => $this->get('id')]);
            $DB->delete_records('tool_dynamicrule_match', ['ruleid' => $this->get('id')]);
        }
    }
}
