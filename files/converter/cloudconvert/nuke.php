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

/**
 * Test that googledrive is configured correctly
 *
 * @package   fileconverter_googledrive
 * @copyright 2017 Andrew Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

use local_moodlecloud\common\{functions, conversion_nuker};

$PAGE->set_url(new moodle_url('/files/converter/cloudconvert/nuke.php'));
$PAGE->set_context(context_system::instance());

require_login();
require_capability('moodle/site:config', context_system::instance());

$stringsandurls = [
    [['administrationsite'], ['/admin/search.php']],
    [['plugins', 'admin'], ['/admin/category.php', ['category' => 'modules']]],
    [['type_fileconverter_plural', 'plugin'], ['/admin/category.php', ['category' => 'fileconverterplugins']]],
    [['pluginname', 'fileconverter_cloudconvert'], ['/admin/settings.php', ['section' => 'fileconvertercloudconvert']]],
    [['purge_conversions', 'fileconverter_cloudconvert'], ['/files/converter/cloudconvert/nuke.php']]
];

// Create the breadcrumb.
array_walk(
    $stringsandurls,
    function(array $stringandurl) use ($PAGE) {
    $PAGE->navbar->add(
        get_string(...$stringandurl[0]),
        new moodle_url(...$stringandurl[1])
    );
});

$strheading = get_string('purge_conversions', 'fileconverter_cloudconvert');
$PAGE->set_heading($strheading);
$PAGE->set_title($strheading);

if (optional_param('confirm', 0, PARAM_BOOL)) {
    require_sesskey();
    list($compose, $partial, $join, $split) = functions::export('compose', 'partial', 'join_on_comma', 'split_on_comma');
    conversion_nuker::nuke_conversions('cloudconvert');

    $existingrecord = $DB->get_record('config', ['name' => 'converter_plugins_sortorder']);

    if($existingrecord) {
        $compose(
            $partial('set_config', 'converter_plugins_sortorder'),
            $join,
            $partial('array_diff', $split($existingrecord->value))
        )(['cloudconvert']);
        purge_all_caches();
    }

    $msg = $OUTPUT->notification(get_string('purge_conversions_complete', 'fileconverter_cloudconvert'), 'success');
} else {
    $returl = new moodle_url($PAGE->url, array('confirm' => 'true', 'sesskey' => sesskey()));
    $msg = $OUTPUT->notification(get_string('purge_conversions_warning', 'fileconverter_cloudconvert'), 'warning');
    $msg .= $OUTPUT->continue_button($returl);
}

echo $OUTPUT->header();
echo $OUTPUT->box($msg, 'generalbox');
echo $OUTPUT->footer();
