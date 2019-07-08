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
 * Overridden fontawesome icons.
 *
 * @package     theme_workplace
 * @copyright   2019 Moodle
 * @author      Bas Brands <bas@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace theme_workplace\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Class overriding some of the Moodle default FontAwesome icons.
 *
 *
 * @package    theme_workplace
 * @copyright  2019 Moodle
 * @author     Bas Brands <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class icon_system_fontawesome extends \core\output\icon_system_fontawesome {

    /**
     * @var array $map Cached map of moodle icon names to font awesome icon names.
     */
    private $map = [];


    /**
     * Change the core icon map
     * @return Array replaced icons.
     */
    public function get_core_icon_map() {
        $iconmap = parent::get_core_icon_map();

        $iconmap['theme:a/wp-alert'] = 'wp-alert';
        $iconmap['theme:a/wp-appearance'] = 'wp-appearance';
        $iconmap['theme:a/wp-archive'] = 'wp-archive';
        $iconmap['theme:a/wp-arrow-down'] = 'wp-arrow-down';
        $iconmap['theme:a/wp-arrow-left-circle'] = 'wp-arrow-left-circle';
        $iconmap['theme:a/wp-arrow-left'] = 'wp-arrow-left';
        $iconmap['theme:a/wp-arrow-right-circle'] = 'wp-arrow-right-circle';
        $iconmap['theme:a/wp-arrow-right'] = 'wp-arrow-right';
        $iconmap['theme:a/wp-arrow-up'] = 'wp-arrow-up';
        $iconmap['theme:a/wp-backup'] = 'wp-backup';
        $iconmap['theme:a/wp-badge'] = 'wp-badge';
        $iconmap['theme:a/wp-bar-chart'] = 'wp-bar-chart';
        $iconmap['theme:a/wp-bookmark'] = 'wp-bookmark';
        $iconmap['theme:a/wp-calendar'] = 'wp-calendar';
        $iconmap['theme:a/wp-certificate'] = 'wp-certificate';
        $iconmap['theme:a/wp-chat'] = 'wp-chat';
        $iconmap['theme:a/wp-check-list'] = 'wp-check-list';
        $iconmap['theme:a/wp-chevron-double-left'] = 'wp-chevron-double-left';
        $iconmap['theme:a/wp-chevron-double-right'] = 'wp-chevron-double-right';
        $iconmap['theme:a/wp-chevron-left'] = 'wp-chevron-left';
        $iconmap['theme:a/wp-chevron-right'] = 'wp-chevron-right';
        $iconmap['theme:a/wp-close-circle'] = 'wp-close-circle';
        $iconmap['theme:a/wp-code'] = 'wp-code';
        $iconmap['theme:a/wp-clipboard'] = 'wp-clipboard';
        $iconmap['theme:a/wp-close'] = 'wp-close';
        $iconmap['theme:a/wp-cog'] = 'wp-cog';
        $iconmap['theme:a/wp-competencies'] = 'wp-competencies';
        $iconmap['theme:a/wp-completed'] = 'wp-completed';
        $iconmap['theme:a/wp-course'] = 'wp-course';
        $iconmap['theme:a/wp-dashboard'] = 'wp-dashboard';
        $iconmap['theme:a/wp-dashboard'] = 'wp-drag-and-drop';
        $iconmap['theme:a/wp-duplicate'] = 'wp-duplicate';
        $iconmap['theme:a/wp-export'] = 'wp-export';
        $iconmap['theme:a/wp-eye-slash'] = 'wp-eye-slash';
        $iconmap['theme:a/wp-eye'] = 'wp-eye';
        $iconmap['theme:a/wp-exclamation-circle'] = 'wp-exclamation-circle';
        $iconmap['theme:a/wp-file'] = 'wp-file';
        $iconmap['theme:a/wp-filter'] = 'wp-filter';
        $iconmap['theme:a/wp-folder-completed'] = 'wp-folder-completed';
        $iconmap['theme:a/wp-folder'] = 'wp-folder';
        $iconmap['theme:a/wp-grades'] = 'wp-grades';
        $iconmap['theme:a/wp-grid'] = 'wp-grid';
        $iconmap['theme:a/wp-hourglass'] = 'wp-hourglass';
        $iconmap['theme:a/wp-info-circle'] = 'wp-info-circle';
        $iconmap['theme:a/wp-launcher'] = 'wp-launcher';
        $iconmap['theme:a/wp-list'] = 'wp-list';
        $iconmap['theme:a/wp-locked'] = 'wp-locked';
        $iconmap['theme:a/wp-logout'] = 'wp-logout';
        $iconmap['theme:a/wp-menu'] = 'wp-menu';
        $iconmap['theme:a/wp-messages'] = 'wp-messages';
        $iconmap['theme:a/wp-pencil'] = 'wp-pencil';
        $iconmap['theme:a/wp-pie-chart'] = 'wp-pie-chart';
        $iconmap['theme:a/wp-pin'] = 'wp-pin';
        $iconmap['theme:a/wp-plus-circle'] = 'wp-plus-circle';
        $iconmap['theme:a/wp-plus-square'] = 'wp-plus-square';
        $iconmap['theme:a/wp-plus'] = 'wp-plus';
        $iconmap['theme:a/wp-refresh'] = 'wp-refresh';
        $iconmap['theme:a/wp-reset'] = 'wp-reset';
        $iconmap['theme:a/wp-search'] = 'wp-search';
        $iconmap['theme:a/wp-send-report'] = 'wp-send-report';
        $iconmap['theme:a/wp-share-arrow'] = 'wp-share-arrow';
        $iconmap['theme:a/wp-sliders'] = 'wp-sliders';
        $iconmap['theme:a/wp-sort-asc'] = 'wp-sort-asc';
        $iconmap['theme:a/wp-sort-desc'] = 'wp-sort-desc';
        $iconmap['theme:a/wp-switch-roles'] = 'wp-switch-roles';
        $iconmap['theme:a/wp-time-left'] = 'wp-time-left';
        $iconmap['theme:a/wp-toggle-off'] = 'wp-toggle-off';
        $iconmap['theme:a/wp-toggle-on'] = 'wp-toggle-on';
        $iconmap['theme:a/wp-tools'] = 'wp-tools';
        $iconmap['theme:a/wp-trash'] = 'wp-trash';
        $iconmap['theme:a/wp-unarchive'] = 'wp-unarchive';
        $iconmap['theme:a/wp-user'] = 'wp-user';
        $iconmap['theme:a/wp-users'] = 'wp-users';
        $iconmap['theme:a/wp-user-plus'] = 'wp-user-plus';

        // Overridden fontawesome icons.
        $iconmap['core:i/dashboard'] = 'wp-dashboard';
        $iconmap['core:i/calendar'] = 'wp-calendar';
        $iconmap['core:i/privatefiles'] = 'wp-file';
        $iconmap['core:t/preferences'] = 'wp-tools';
        $iconmap['tool_wp:userpreferences'] = 'wp-sliders';
        $iconmap['core:i/notifications'] = 'wp-bell';
        $iconmap['core:t/message'] = 'wp-messages';
        $iconmap['core:i/users'] = 'wp-users';
        $iconmap['core:i/user'] = 'wp-user';
        $iconmap['core:i/badge'] = 'wp-badge';
        $iconmap['core:i/grades'] = 'wp-grades';
        $iconmap['core:t/grades'] = 'wp-grades';
        $iconmap['core:i/course'] = 'wp-course';
        $iconmap['core:i/competencies'] = 'wp-competencies';
        $iconmap['core:i/switchrole'] = 'wp-switch-roles';
        $iconmap['core:a/logout'] = 'wp-logout';
        $iconmap['core:i/search'] = 'wp-search';
        $iconmap['core:a/search'] = 'wp-search';
        $iconmap['core:i/lock'] = 'wp-locked';
        $iconmap['core:t/locked'] = 'wp-locked';
        $iconmap['core:e/tick'] = 'wp-completed';
        $iconmap['core:t/right'] = 'wp-arrow-right';
        $iconmap['core:t/left'] = 'wp-arrow-left';
        $iconmap['core:a/view_tree_active'] = 'wp-folder';
        $iconmap['core:t/circle'] = 'wp-dashboard-check-list';
        $iconmap['core:e/insert_time'] = 'wp-time-left';
        $iconmap['core:i/folder'] = 'wp-dashboard-set';
        $iconmap['core:i/completeprogram'] = 'wp-check-list';
        $iconmap['core:i/dragdrop'] = 'wp-drag-and-drop';
        $iconmap['core:i/move_2d'] = 'wp-drag-and-drop';
        $iconmap['core:t/sort_desc'] = 'wp-sort-desc';
        $iconmap['core:t/sort_asc'] = 'wp-sort-asc';
        $iconmap['core:t/editstring'] = 'wp-pencil';
        $iconmap['core:t/editinline'] = 'wp-pencil';
        $iconmap['core:t/passwordunmask-edit'] = 'wp-pencil';
        $iconmap['core:i/edit'] = 'wp-pencil';
        $iconmap['core:b/document-edit'] = 'wp-pencil';
        $iconmap['core:i/settings'] = 'wp-cog';
        $iconmap['theme:fp/setting'] = 'wp-cog';
        $iconmap['core:t/contextmenu'] = 'wp-cog';
        $iconmap['core:t/dropdown'] = 'wp-cog';
        $iconmap['core:t/edit_menu'] = 'wp-cog';
        $iconmap['core:t/edit'] = 'wp-cog';
        $iconmap['core:a/setting'] = 'wp-cog';
        $iconmap['core:e/manage_files'] = 'wp-duplicate';
        $iconmap['tool_wp:archive'] = 'wp-archive';
        $iconmap['core:i/show'] = 'wp-eye-slash';
        $iconmap['core:t/show'] = 'wp-eye-slash';
        $iconmap['core:e/show_invisible_characters'] = 'wp-eye-slash';
        $iconmap['core:i/hide'] = 'wp-eye';
        $iconmap['core:t/hide'] = 'wp-eye';
        $iconmap['core:t/passwordunmask-reveal'] = 'wp-eye';
        $iconmap['core:i/enrolusers'] = 'wp-user-plus';
        $iconmap['tool_wp:bar-chart'] = 'wp-bar-chart';
        $iconmap['tool_wp:arrow-circle-left'] = 'wp-arrow-left-circle';
        $iconmap['core:i/trash'] = 'wp-trash';
        $iconmap['core:t/delete'] = 'wp-trash';
        $iconmap['core:b/edit-delete'] = 'wp-trash';
        $iconmap['core:i/delete'] = 'wp-trash';
        $iconmap['tool_wp:plus-circle'] = 'wp-plus-circle';
        $iconmap['core:a/view_list_active'] = 'wp-list';
        $iconmap['core:t/add'] = 'wp-plus';
        $iconmap['core:i/up'] = 'wp-arrow-up';
        $iconmap['core:i/down'] = 'wp-arrow-down';
        $iconmap['tool_wp:angle-right'] = 'wp-chevron-right';
        $iconmap['tool_wp:angle-left'] = 'wp-chevron-left';
        $iconmap['core:t/messages'] = 'wp-chat';
        $iconmap['tool_wp:paper-plane-o'] = 'wp-send-report';
        $iconmap['core:e/text_color_picker'] = 'wp-appearance';
        $iconmap['tool_wp:folder-open-o'] = 'wp-folder-completed';
        $iconmap['tool_wp:calendar-o'] = 'wp-certificate';
        $iconmap['tool_wp:check-circle-o'] = 'wp-check-list';
        $iconmap['tool_wp:hourglass-end'] = 'wp-time-left';
        $iconmap['tool_wp:exclamation-triangle'] = 'wp-alert';
        $iconmap['tool_wp:bar-chart'] = 'wp-bar-chart';
        $iconmap['tool_wp:restorearchived'] = 'wp-unarchive';
        $iconmap['tool_wp:toggle-off'] = 'wp-toggle-off';
        $iconmap['tool_wp:toggle-on'] = 'wp-toggle-on';
        $iconmap['tool_wp:sort-amount-asc'] = 'wp-sort-asc';
        $iconmap['tool_wp:sort-amount-desc'] = 'wp-sort-desc';
        $iconmap['core:i/menubars'] = 'wp-menu';
        $iconmap['core:a/view_icon_active'] = 'wp-launcher';
        $iconmap['core:i/filter'] = 'wp-filter';
        $iconmap['core:t/left'] = 'wp-arrow-left';
        $iconmap['core:i/return'] = 'wp-arrow-left';
        $iconmap['theme:fp/alias'] = 'wp-share-arrow';
        $iconmap['theme:fp/alias_sm'] = 'wp-share-arrow';
        $iconmap['core:i/publish'] = 'wp-share-arrow';
        $iconmap['core:i/import'] = 'wp-backup';
        $iconmap['core:i/restore'] = 'wp-backup';
        $iconmap['core:e/paste'] = 'wp-clipboard';
        $iconmap['tool_wp:bookmark'] = 'wp-bookmark';

        return $iconmap;
    }
}