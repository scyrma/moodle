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
 * @package   theme_school
 * @copyright 2016 Moodle, moodle.org
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Get the HTML for the settings bits.
$html = theme_school_get_html_for_settings($OUTPUT, $PAGE);
require('header.php');
if (!isloggedin() && $isregistration->value == 'email') { ?>
    <input type="hidden" name="custommenu" value="nologinselfreg" id="custommenu">
<?php } ?>
<div id="page" class="container-fluid">

    <div id="page-content" class="row-fluid">
        <section id="region-main" class="span12">
            <?php
            echo $OUTPUT->login_page_header();
            echo $OUTPUT->main_content();
            echo $OUTPUT->lang_menu();
            ?>
        </section>
    </div>

    <?php
        echo $OUTPUT->theme_footer();
        echo $OUTPUT->standard_end_of_body_html();
        $PAGE->requires->js_call_amd('theme_school/login', 'init');
    ?>

</div>
</body>
</html>
