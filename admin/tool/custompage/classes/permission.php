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

namespace tool_custompage;

use context_system;
use tool_custompage\local\helpers\audience;
use tool_custompage\local\models\page;
use tool_tenant\tenancy;

/**
 * Custom pages permission class
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission {

    /**
     * Require given user can view pages list
     *
     * @param int|null $userid User ID to check, or the current user if omitted
     * @throws permission_exception
     */
    public static function require_can_view_pages_list(?int $userid = null): void {
        if (!static::can_view_pages_list($userid)) {
            throw new permission_exception('errorpagelist');
        }
    }

    /**
     * Whether given user can view pages list
     *
     * @param int|null $userid User ID to check, or the current user if omitted
     * @return bool
     */
    public static function can_view_pages_list(?int $userid = null): bool {
        return self::can_create_page($userid);
    }

    /**
     * Require given user can create a new page
     *
     * @param int|null $userid User ID to check, or the current user if omitted
     * @throws permission_exception
     */
    public static function require_can_create_page(?int $userid = null): void {
        if (!static::can_create_page($userid)) {
            throw new permission_exception('errorpagecreate');
        }
    }

    /**
     * Whether given user can create a new page
     *
     * @param int|null $userid User ID to check, or the current user if omitted
     * @return bool
     */
    public static function can_create_page(?int $userid = null): bool {
        return has_capability('tool/custompage:edit', context_system::instance(), $userid) || static::can_create_global_page();
    }

    /**
     * Require given user can create a new global page
     *
     * @param int|null $userid User ID to check, or the current user if omitted
     * @throws permission_exception
     */
    public static function require_can_create_global_page(?int $userid = null): void {
        if (!static::can_create_global_page($userid)) {
            throw new permission_exception('errorpagecreate');
        }
    }

    /**
     * Whether given user can create a new global page
     *
     * @param int|null $userid User ID to check, or the current user if omitted
     * @return bool
     */
    public static function can_create_global_page(?int $userid = null): bool {
        return has_capability('tool/custompage:editall', context_system::instance(), $userid);
    }

    /**
     * Require given user can edit page
     *
     * @param page $page
     * @param int|null $userid User ID to check, or the current user if omitted
     * @throws permission_exception
     */
    public static function require_can_edit_page(page $page, ?int $userid = null): void {
        if (!static::can_edit_page($page, $userid)) {
            throw new permission_exception('errorpageedit');
        }
    }

    /**
     * Whether given user can edit page
     *
     * @param page $page
     * @param int|null $userid User ID to check, or the current user if omitted
     * @return bool
     */
    public static function can_edit_page(page $page, ?int $userid = null): bool {
        if ($page->get('global')) {
            return static::can_create_global_page($userid);
        } else {
            return static::can_create_page($userid) && $page->get('tenantid') === tenancy::get_tenant_id();
        }
    }

    /**
     * Require given user can preview page
     *
     * @param page $page
     * @param int|null $userid User ID to check, or the current user if omitted
     * @throws permission_exception
     */
    public static function require_can_preview_page(page $page, ?int $userid = null): void {
        if (!static::can_preview_page($page, $userid)) {
            throw new permission_exception('errorpagepreview');
        }
    }

    /**
     * Whether given user can preview page
     *
     * @param page $page
     * @param int|null $userid User ID to check, or the current user if omitted
     * @return bool
     */
    public static function can_preview_page(page $page, ?int $userid = null): bool {
        if ($page->get('global')) {
            return static::can_create_global_page($userid) ||
                (self::can_create_page() && self::can_view_page_via_audience($page, $userid));
        } else {
            return static::can_create_page($userid) && $page->get('tenantid') === tenancy::get_tenant_id();
        }
    }

    /**
     * Require given user can view page
     *
     * @param page $page
     * @param int|null $userid User ID to check, or the current user if omitted
     * @throws permission_exception
     */
    public static function require_can_view_page(page $page, ?int $userid = null): void {
        if (!static::can_view_page($page, $userid)) {
            throw new permission_exception('errorpageview');
        }
    }

    /**
     * Whether given user can view page
     *
     * @param page $page
     * @param int|null $userid User ID to check, or the current user if omitted
     * @return bool
     */
    public static function can_view_page(page $page, ?int $userid = null): bool {
        return static::can_edit_page($page, $userid) || static::can_view_page_via_audience($page, $userid);
    }

    /**
     * Whether given user is within the configured audience of a page
     *
     * @param page $page
     * @param int|null $userid User ID to check, or the current user if omitted
     * @return bool
     */
    public static function can_view_page_via_audience(page $page, ?int $userid = null): bool {
        $pages = array_map(static function(page $page): int {
            return $page->get('id');
        }, audience::user_pages_list($userid));

        return in_array($page->get('id'), $pages);
    }

    /**
     * Whether given user can skip validation when duplicating page blocks
     *
     * @param int|null $userid User ID to check.
     * @return bool
     */
    public static function can_duplicate_block_without_validation(?int $userid = null): bool {
        return has_capability('tool/custompage:skipblockvalidation', context_system::instance(), $userid);
    }
}
