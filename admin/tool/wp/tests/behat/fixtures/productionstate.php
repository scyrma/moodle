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
 * Fixture for behat test "Retrieving the production state for the site"
 *
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 Marina Glancy
 * @package   tool_wp
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// This file does not need require_login, skip codechecker here.
// @codingStandardsIgnoreLine
require_once(__DIR__.'/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

global $CFG, $PAGE, $OUTPUT;
$PAGE->set_url('/admin/tool/wp/tests/behat/fixtures/productionstate.php');
$PAGE->set_context(context_system::instance());

echo $OUTPUT->header();
$syscontext = context_system::instance();
$url = moodle_url::make_file_url("/pluginfile.php", "/$syscontext->id/tool_wp/wp/0/wp.txt");
echo html_writer::link($url, 'Download me');
echo $OUTPUT->footer();
