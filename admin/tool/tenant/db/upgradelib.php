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
 * Upgrade scripts for "Tenant" plugin
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Odei Alba
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Remove orphaned files.
 */
function tool_tenant_upgrade_remove_orphaned_files() {
    global $DB;

    $fileareas = ['headerlogo', 'loginlogo', 'tenantselectorlogo', 'loginbackground', 'favicon'];
    [$select, $params] = $DB->get_in_or_equal($fileareas, SQL_PARAMS_NAMED);

    $params['contextid'] = context_system::instance()->id;
    $params['component'] = 'tool_tenant';

    $sql = 'SELECT f.*
              FROM {files} f
         LEFT JOIN {tool_tenant} t
                ON t.id = f.itemid
             WHERE f.component = :component
               AND f.contextid = :contextid
               AND f.filearea ' . $select . '
               AND t.id IS NULL';

    // Get all tenant files.
    $filerecords = $DB->get_records_sql($sql, $params);

    $fs = get_file_storage();
    foreach ($filerecords as $filerecord) {
        // Delete the file.
        $fs->get_file_instance($filerecord)->delete();
    }
}

/**
 * Migrate old tenant colours to scss styles.
 */
function tool_tenant_upgrade_migrate_old_tenant_colours() {
    global $DB;

    $tenants = $DB->get_records('tool_tenant');
    foreach ($tenants as $tenant) {
        $migrationscss = '';
        $mobilescss = '';

        // Get tenant colours configuration.
        $cssconfig = json_decode($tenant->cssconfig, true);
        if ($cssconfig == null) {
            continue;
        }

        // Previous links colour is stored now as the brand colour.
        if (!empty($cssconfig['primary'])) {
            $cssconfig['brand'] = $cssconfig['primary'];
        }
        // Generate scss for navbar colour.
        if (!empty($cssconfig['brand'])) {
            $migrationscss .= "/** Automatically generated CSS during upgrade to Workplace 4.0: setting navbar color as "
            . "{$cssconfig['brand']} **/\n\$navcolour: {$cssconfig['brand']}; nav.bg-white { background-color: \$navcolo"
            . "ur !important; color: color-yiq (\$navcolour); }.navbar-light .navbar-nav .nav-link, .navbar-light .navba"
            . "r-nav .dropdown-toggle { color: color-yiq(\$navcolour); &.active { color: rgba(color-yiq(\$navcolour), .9"
            . "); } &:hover, &:focus { color: rgba(color-yiq(\$navcolour), .9); background-color: lighten(\$navcolour, 5"
            . "%); } } .primary-navigation .navigation .nav-link { color: color-yiq(\$navcolour); }\n/** End of automati"
            . "cally generated SCSS for navbar **/\n\n";
        }
        // Generate scss for buttons colour (if it's different from primary).
        if (!empty($cssconfig['button']) && $cssconfig['button'] != $cssconfig['primary']) {
            $migrationscss .= "/** Automatically generated CSS during upgrade to Workplace 4.0: setting button color as "
            . "{$cssconfig['button']} **/\n\$button: {$cssconfig['button']}; .btn-primary { color: color-yiq(\$button); "
            . "background-color: \$button; border-color: darken(\$button, 10%); &:not(:disabled):not(.disabled):hover, &"
            . ":not(:disabled):not(.disabled):focus { color: color-yiq(\$button); background-color: darken(\$button, 10%"
            . "); border-color: darken(\$button, 10%); } &:not(:disabled):not(.disabled):active { color: color-yiq(\$but"
            . "ton); background-color: darken(\$button, 20%); border-color: darken(\$button, 20%); } &:not(:disabled):no"
            . "t(.disabled):focus,&:not(:disabled):not(.disabled):active:focus { box-shadow: 0 0 0 .2rem rgba(\$button, "
            . ".5); } &:disabled { color: color-yiq(\$button); background-color: lighten(\$button, 10%); border-color: l"
            . "ighten(\$button, 10%); } &.close { color: inherit; } }\n/** End of automatically generated SCSS for butto"
            . "ns **/\n\n";
            $mobilescss .= "--wp_tool_tenant_config_button: {$cssconfig['button']}; /* Moodle App buttons color. */\n";
        }
        // Generate scss for drawer colour.
        if (!empty($cssconfig['drawer'])) {
            $mobilescss .= "--wp_tool_tenant_config_drawer: {$cssconfig['drawer']}; /* Moodle App bottom navigation back"
            . "ground. */\n";
        }
        // Add generated mobile SCSS to migration SCSS.
        if (!empty($mobilescss)) {
            $mobilescss = "/** Automatically generated SCSS during upgrade to Workplace 4.0: setting Moodle App colours "
            . "**/\n:root {\n" . $mobilescss . "}\n/** End of automatically generated SCSS for Moodle App colours **/\n\n";
            $migrationscss .= $mobilescss;
        }
        // Add generated SCSS if needed.
        if (!empty($migrationscss)) {
            $migrationscss = "/** Automatically generated CSS during upgrade to Workplace 4.0: adding color-yiq functio"
            . "n **/\n@function color-yiq(\$color) { \$r: red(\$color); \$g: green(\$color); \$b: blue(\$color); \$yiq:"
            . "((\$r * 299) + (\$g * 587) + (\$b * 114)) / 1000; @if (\$yiq >= 150) { @return #212529; } @else { @retur"
            . "n #f8f9fa; } }\n/** End of automatically generated SCSS for color-yiq function **/\n\n" . $migrationscss;
            $cssconfig['customcss'] = $migrationscss . $cssconfig['customcss'];
        }

        // Unset the colours that are not used anymore.
        unset($cssconfig['primary']);
        unset($cssconfig['button']);
        unset($cssconfig['drawer']);
        unset($cssconfig['footer']);

        $DB->update_record('tool_tenant', (object) ['id' => $tenant->id, 'cssconfig' => json_encode($cssconfig)]);
    }
}

/**
 * Replace default header logo.
 */
function tool_tenant_upgrade_replace_default_logo() {
    global $CFG, $DB;

    $tenants = $DB->get_records('tool_tenant');
    $fs = \get_file_storage();

    foreach ($tenants as $tenant) {
        // Get current header logo.
        $files = $fs->get_area_files(\context_system::instance()->id, 'tool_tenant', 'headerlogo', $tenant->id, '', false);
        $logo = reset($files);
        // If it was the default logo, then update it.
        $originallogohash = '8c1efda334110fedd08ce57c4a7fcefd6610aae8';
        if ($logo && $logo->get_contenthash() === $originallogohash) {
            $fs->delete_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo', $tenant->id);
            $filerecord = [
                'contextid' => \context_system::instance()->id,
                'component' => 'tool_tenant',
                'userid' => get_admin()->id,
                'filearea' => 'headerlogo',
                'itemid' => $tenant->id,
                'filepath' => '/',
                'filename' => 'workplacelogo.png',
            ];
            $fs->create_file_from_pathname($filerecord, $CFG->dirroot . '/' . $CFG->admin . '/tool/tenant/pix/workplacelogo.png');
        }
    }
}

/**
 * When upgrading from LMS to Workplace make all custom reports available under default tenant
 *
 * @return void
 * @throws dml_exception
 */
function tool_tenant_upgrade_custom_reports() {
    global $DB;
    $DB->execute('UPDATE {reportbuilder_report} SET component=?, itemid=? WHERE component IS NULL OR component = ?',
        ['tool_tenant', \tool_tenant\tenancy::get_default_tenant_id(), '']);
}
