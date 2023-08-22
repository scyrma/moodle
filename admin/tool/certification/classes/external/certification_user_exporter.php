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

namespace tool_certification\external;

use core\external\persistent_exporter;
use core_user;
use core_user\fields;
use renderer_base;
use tool_certification\certification;
use tool_certification\certification_completion;
use tool_certification\certification_user;

/**
 * Class for exporting field data.
 *
 * @package    tool_certification
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Odei Alba
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_user_exporter extends persistent_exporter {
    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class(): string {
        return certification_user::class;
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'certificationfullname' => [
                'type' => PARAM_TEXT,
            ],
            'certificationidnumber' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'userfullname' => [
                'type' => PARAM_RAW,
            ],
            'useridentity' => [
                'type' => [
                    'name' => ['type' => PARAM_TEXT],
                    'value' => ['type' => PARAM_TEXT],
                ],
                'multiple' => true,
                'optional' => true,
            ],
            'completions' => [
                'type' => certification_completion_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => true,
            ],
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {
        /** @var \context $context */
        $context = $this->related['context'];

        $certificationid = $this->persistent->get('certificationid');
        $userid = $this->persistent->get('userid');

        $certification = new certification($certificationid);
        $user = core_user::get_user($userid);

        $usercompletions = certification_completion::get_records(['certificationid' => $certificationid, 'userid' => $userid]);

        $result['certificationfullname'] = $certification->get('fullname');
        $result['certificationidnumber'] = $certification->get('idnumber');

        $result['userfullname'] = fullname($user, has_capability('moodle/site:viewfullnames', $context));
        $result['useridentity'] = [];
        $fields = fields::for_identity($context, false)->get_required_fields();
        foreach ($fields as $field) {
            $result['useridentity'][] = [
                'name' => $field,
                'value' => s($user->{$field}),
            ];
        }

        $result['completions'] = array_map(function($usercompletion) use ($output) {
            return (new certification_completion_exporter($usercompletion))->export($output);
        }, $usercompletions);

        return $result;
    }
}
