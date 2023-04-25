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

namespace tool_reportbuilder\local\filter;

use coding_exception;
use core_reportbuilder\local\helpers\database;
use core_tag_tag;
use MoodleQuickForm;
use stdClass;
use tool_reportbuilder\filter_base;

/**
 * Class containing logic for the tags filter
 *
 * The following array properties should be passed to the {@see \tool_reportbuilder\report_filter::get_options} method when
 * defining this filter in a report:
 *
 * ['component' => 'mycomponent', 'itemtype' => 'myitems']
 *
 * @package     tool_reportbuilder
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tags extends filter_base {

    /**
     * When converting a report containing this condition to core_reportbuilder how should the values be converted
     *
     * @param array $values values stored for this condition in the tool_reportbuilder custom report
     * @param \core_reportbuilder\local\filters\base $newcondition
     * @return array values to be stored for the converted condition in the core_reportbuilder custom report
     */
    public function convert_condition_values(array $values, \core_reportbuilder\local\filters\base $newcondition): array {
        if ($newcondition instanceof \tool_wp\reportbuilder\local\filters\tags) {
            $value = array_key_exists($this->name, $values) ? $values[$this->name] : '';
            $newconditionname = $newcondition->get_filter_persistent()->get('uniqueidentifier');
            return [$newconditionname => $value];
        }

        // The datasource mapped this condition to something else, not "tags", so
        // datasource should provide the mapping in this case.
        return parent::convert_condition_values($values, $newcondition);
    }

    /**
     * Setup form
     *
     * @param MoodleQuickForm $mform
     * @throws coding_exception If component/itemtype options are missing
     */
    public function setup_form(MoodleQuickForm $mform) {
        global $DB;

        $this->common_header($mform);

        $options = $this->reportfilter->get_options();
        if (!array_key_exists('component', $options) || !array_key_exists('itemtype', $options)) {
            throw new coding_exception('Missing \'component\' and/or \'itemtype\' in filter options');
        }

        $sql = 'SELECT DISTINCT t.id, t.name, t.rawname
                  FROM {tag} t
                  JOIN {tag_instance} ti ON ti.tagid = t.id
                 WHERE ti.component = :component AND ti.itemtype = :itemtype
              ORDER BY t.name';

        // Transform tag records into appropriate display name, for selection in the autocomplete element.
        $tagoptions = array_map(static function(stdClass $record): string {
            return core_tag_tag::make_display_name($record);
        }, $DB->get_records_sql($sql, ['component' => $options['component'], 'itemtype' => $options['itemtype']]));

        $mform->addElement('autocomplete', $this->name, get_string('tags'), $tagoptions, ['multiple' => true])
            ->setHiddenLabel(true);

        $this->common_footer($mform);
    }

    /**
     * Return filter SQL
     *
     * @param array|null $values
     * @return array
     */
    public function get_sql_filter(?array $values): array {
        global $DB;

        $tags = $values[$this->name] ?? [];
        if (empty($tags)) {
            return ['', []];
        } else if (!is_array($tags)) {
            $tags = [(int) $tags];
        }

        $fieldsql = $this->reportfilter->get_field_sql();
        $options = $this->reportfilter->get_options();

        [$tagsql, $tagparams] = $DB->get_in_or_equal($tags, SQL_PARAMS_NAMED,
            database::generate_param_name() . '_');

        $paramcomponent = database::generate_param_name();
        $paramitemtype = database::generate_param_name();

        $tagparams = array_merge($tagparams, [
            $paramcomponent => $options['component'],
            $paramitemtype => $options['itemtype'],
        ]);

        $tagtablealias = database::generate_alias();
        $taginstancetablealias = database::generate_alias();

        $containstagsql = "EXISTS (
            SELECT 1
              FROM {tag} {$tagtablealias}
              JOIN {tag_instance} {$taginstancetablealias} ON {$taginstancetablealias}.tagid = {$tagtablealias}.id
             WHERE {$tagtablealias}.id {$tagsql}
               AND {$taginstancetablealias}.component = :{$paramcomponent}
               AND {$taginstancetablealias}.itemtype = :{$paramitemtype}
               AND {$taginstancetablealias}.itemid = {$fieldsql}
        )";

        return [$containstagsql, $tagparams];
    }

    /**
     * Get filter label (this is never called but is an abstract method of the base class)
     *
     * @param array $data
     * @return string
     */
    public function get_label(array $data): string {
        return 'tags';
    }
}
