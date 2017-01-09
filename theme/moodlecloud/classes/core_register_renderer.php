<?php
// This file is part of Moodle - http://moodle.org/
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

require_once($CFG->dirroot . '/theme/bootstrapbase/renderers.php');

/**
 * Clean core renderers.
 *
 * @package    theme_moodlecloud
 * @copyright  2015 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_moodlecloud_core_register_renderer extends core_register_renderer {
    public function registeredonhublisting($hubs) {
        global $CFG;
        $table = new html_table();
        $table->head = array(get_string('hub', 'hub'), get_string('operation', 'hub'));
        $table->size = array('80%', '20%');

        foreach ($hubs as $hub) {
            if ($hub->huburl == HUB_MOODLEORGHUBURL) {
                $hub->hubname = get_string('registeredmoodleorg', 'hub', $hub->hubname);
            }
            $hublink = html_writer::tag('a', $hub->hubname, array('href' => $hub->huburl));
            $hublinkcell = html_writer::tag('div', $hublink, array('class' => 'registeredhubrow'));
            if (false && $hub->huburl == HUB_MOODLEORGHUBURL) {
                $unregisterbuttonhtml = '&nbsp';
            } else {
                $unregisterhuburl = new moodle_url("/" . $CFG->admin . "/registration/index.php",
                                array('sesskey' => sesskey(), 'huburl' => $hub->huburl,
                                    'unregistration' => 1));
                $unregisterbutton = new single_button($unregisterhuburl,
                                get_string('unregister', 'hub'));
                $unregisterbutton->class = 'centeredbutton';
                $unregisterbuttonhtml = $this->output->render($unregisterbutton);
            }

            // Add button cells.
            $cells = array($hublinkcell, $unregisterbuttonhtml);
            $row = new html_table_row($cells);
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }
}
