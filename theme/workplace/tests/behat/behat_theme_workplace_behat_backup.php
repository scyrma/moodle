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
 * Backup step definition overrides for the workplace theme.
 *
 * @package    theme_workplace
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: No MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../backup/util/ui/tests/behat/behat_backup.php');

use Behat\Gherkin\Node\TableNode as TableNode;

/**
 * Backup-related steps definitions.
 *
 * @package    theme_workplace
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_theme_workplace_behat_backup extends behat_backup {

    /**
     * Backups the specified course using the provided options. If you are interested in restoring this backup would be
     * useful to provide a 'Filename' option.
     *
     * @param string $backupcourse
     * @param TableNode $options Backup options or false if no options provided
     */
    public function i_backup_course_using_this_options($backupcourse, $options = false) {
        // We can not use other steps here as we don't know where the provided data
        // table elements are used, and we need to catch exceptions contantly.

        // Go to homepage.
        $this->getSession()->visit($this->locate_path('/?redirect=0'));
        $this->execute("behat_general::wait_until_the_page_is_ready");

        // Click the course link.
        $this->execute("behat_general::i_click_on_in_the", [$backupcourse, "link", "region-main", "region"]);

        // Click the backup link.
        $this->execute("behat_navigation::i_navigate_to_in_current_page_administration", get_string('backup'));

        // Initial settings.
        $this->fill_backup_restore_form($this->get_step_options($options, "Initial"));
        $this->execute("behat_forms::press_button", get_string('backupstage1action', 'backup'));

        // Schema settings.
        $this->fill_backup_restore_form($this->get_step_options($options, "Schema"));
        $this->execute("behat_forms::press_button", get_string('backupstage2action', 'backup'));

        // Confirmation and review, backup filename can also be specified.
        $this->fill_backup_restore_form($this->get_step_options($options, "Confirmation"));
        $this->execute("behat_forms::press_button", get_string('backupstage4action', 'backup'));

        // Waiting for it to finish.
        $this->execute("behat_general::wait_until_the_page_is_ready");

        // Last backup continue button.
        $this->execute("behat_general::i_click_on", array(get_string('backupstage16action', 'backup'), 'button'));
    }

}
