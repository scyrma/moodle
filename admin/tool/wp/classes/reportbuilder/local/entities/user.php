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

declare(strict_types=1);

namespace tool_wp\reportbuilder\local\entities;

use core_component;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;

/**
 * User entity class implementation, containing Workplace specific methods and additional column/filters
 *
 * @package    tool_wp
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Paul Holden <paulh@moodle.com>
 * @author     2019 Marina Glancy <marina@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user extends \core_reportbuilder\local\entities\user {

    /**
     * Returns column that corresponds to the given identity field
     *
     * @param string $identityfieldname either field from the table 'user' or a shortname of a user profile field,
     *     in which case it starts with 'profile_field_'
     * @return column
     */
    public function get_identity_column(string $identityfieldname): column {
        if (preg_match("/^profile_field_(?<shortname>.*)$/", $identityfieldname, $matches)) {
            $identityfieldname = 'profilefield_' . $matches['shortname'];
        }

        return $this->get_column($identityfieldname);
    }

    /**
     * Returns filter that corresponds to the given identity field
     *
     * @param string $identityfieldname either field from the table 'user' or a shortname of a user profile field,
     *     in which case it starts with 'profile_field_'
     * @return filter
     */
    public function get_identity_filter(string $identityfieldname): filter {
        if (preg_match("/^profile_field_(?<shortname>.*)$/", $identityfieldname, $matches)) {
            $identityfieldname = 'profilefield_' . $matches['shortname'];
        }

        return $this->get_filter($identityfieldname);
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $filters = parent::get_all_filters();
        $tablealias = $this->get_table_alias('user');

        // TODO Remove when MDL-74453 lands.
        // Authentication method filter.
        $filters[] = (new filter(
            select::class,
            'auth',
            new lang_string('authentication', 'moodle'),
            $this->get_entity_name(),
            "{$tablealias}.auth"
        ))
            ->set_options_callback(static function(): array {
                $plugins = core_component::get_plugin_list('auth');
                $enabled = get_string('pluginenabled', 'core_plugin');
                $disabled = get_string('plugindisabled', 'core_plugin');
                $authoptions = [$enabled => [], $disabled => []];

                foreach ($plugins as $pluginname => $unused) {
                    $plugin = get_auth_plugin($pluginname);
                    if (is_enabled_auth($pluginname)) {
                        $authoptions[$enabled][$pluginname] = $plugin->get_title();
                    } else {
                        $authoptions[$disabled][$pluginname] = $plugin->get_title();
                    }
                }
                return $authoptions;
            });

        return $filters;
    }
}
