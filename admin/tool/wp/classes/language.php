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
 * Class language
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

/**
 * Class language
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class language {

    /**
     * Get the list of languages from browser accepted languages
     *
     * Code is mostly taken from setup_lang_from_browser()
     *
     * @return array
     */
    protected static function get_browser_accepted_languages() {
        // Extract and clean langs from headers.
        $langs = [];
        $rawlangs = !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
        $rawlangs = explode(',', $rawlangs);
        if (empty($rawlangs)) {
            return $langs;
        }

        // Create a list of accepted languages sorted by their factor weighting (desc).
        $order = 1.0;
        foreach ($rawlangs as $lang) {
            if (strpos($lang, ';') === false) {
                $langs[(string)$order] = self::clean_lang($lang);
                $order = $order - 0.01;
            } else {
                $parts = explode(';', $lang);
                $pos = strpos($parts[1], '=');
                $langs[substr($parts[1], $pos + 1)] = self::clean_lang($parts[0]);
            }
        }
        $langs = array_filter($langs);
        krsort($langs, SORT_NUMERIC);

        // Return the same list of languages with postfix _wp first and then the list of languages.
        return array_merge(array_values(array_map(function($v) {
            return $v . '_wp';
        }, $langs)),
            array_values($langs));
    }

    /**
     * Returns first installed language from browser accepted languages
     *
     * @return string
     */
    public static function get_recommended_language() {
        $langs = self::get_browser_accepted_languages();
        foreach ($langs as $lang) {
            if (get_string_manager()->translation_exists($lang, false)) {
                return $lang;
            }
        }
        return '';
    }

    /**
     * Clean the language code
     *
     * @param string $lang
     * @return string
     */
    protected static function clean_lang($lang) {
        $lang = str_replace('-', '_', $lang);
        return strtolower(preg_replace('/[^A-Za-z0-9_-]/i', '', $lang));
    }

    /**
     * Returns first existing language from browser accepted languages (used to prefill installation language)
     *
     * @param string $langdir
     * @return string
     */
    public static function get_default_install_language($langdir) {
        $langs = self::get_browser_accepted_languages();
        if (!empty($_REQUEST['lang']) && ($reqlang = self::clean_lang($_REQUEST['lang']))) {
            array_unshift($langs, $reqlang);
        }
        foreach ($langs as $lang) {
            if (file_exists($langdir . '/' . $lang . '/langconfig.php')) {
                return $lang;
            }
        }
        return 'en_wp';
    }

    /**
     * Returns the list of languages filtered to only workplace languages
     *
     * @param string $selectedlang
     * @return array
     */
    public static function get_list_of_workplace_translations($selectedlang = null) {
        return array_filter(get_string_manager()->get_list_of_translations(), function($lang) use ($selectedlang) {
            return ($lang === $selectedlang) || preg_match('/_wp$/', $lang);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Pre-processes the list of translations - hides non-workplace languages if wp lang is present
     *
     * @param array $languages
     * @param bool $returnall
     * @param array $translist
     * @param array $transaliases
     * @return array
     */
    public static function get_list_of_translations($languages, $returnall, $translist, $transaliases) {
        global $CFG;
        if ($returnall || empty($CFG->wphideparentlang)) {
            return $languages;
        }
        $wpparents = self::get_wp_languages_with_parents();
        foreach ($languages as $langcode => $langname) {
            if (array_key_exists($langcode, $wpparents) &&
                    (empty($translist) || array_key_exists($langcode, $translist))) {
                unset($languages[$wpparents[$langcode]]);
            }
        }
        return $languages;
    }

    /**
     * List of installed workplace languages and their parents
     *
     * @param bool $usecache use cache when retrieving the list
     * @return array
     */
    protected static function get_wp_languages_with_parents(bool $usecache = true) {
        global $CFG;
        if ($usecache) {
            $cache = \cache::make('core', 'langmenu');
            $cachekey = 'langparents';
            if (($parents = $cache->get($cachekey)) !== false) {
                return $parents;
            }
        }

        $langs = get_directory_list($CFG->langotherroot, '', false, true, false);
        $stmanager = new \core_string_manager_standard($CFG->langotherroot, $CFG->langlocalroot, [], []);
        $parents = [];
        foreach ($langs as $langcode) {
            if ($langcode === 'en_wp') {
                $parents[$langcode] = 'en';
            } else if (preg_match('/_wp$/', $langcode)) {
                $lparents = array_diff($stmanager->get_language_dependencies($langcode), [$langcode]);
                if ($parent = reset($lparents)) {
                    $parents[$langcode] = $parent;
                }
            }
        }

        if ($usecache) {
            $cache->set($cachekey, $parents);
        }
        return $parents;
    }

    /**
     * Event listener when language pack was imported
     *
     * @param \tool_langimport\event\langpack_imported $event
     */
    public static function on_langpack_imported(\tool_langimport\event\langpack_imported $event) {
        global $DB, $CFG;
        $langcode = $event->other['langcode'];
        if (preg_match('/_wp$/', $langcode)) {
            $wpparents = self::get_wp_languages_with_parents(false);
            if (array_key_exists($langcode, $wpparents)) {
                // After this language installation the parent language $wpparents[$langcode] will be hidden.
                // Change all courses and users to use the new language instead of the parent.
                $parentlang = $wpparents[$langcode];
                $DB->execute('UPDATE {course} SET lang=? WHERE lang=?', [$langcode, $parentlang]);
                $DB->execute('UPDATE {user} SET lang=? WHERE lang=?', [$langcode, $parentlang]);
                if ($CFG->lang === $parentlang) {
                    set_config('lang', $langcode);
                }
            }
        }
    }

    /**
     * Event listener when language pack was removed
     *
     * @param \tool_langimport\event\langpack_removed $event
     */
    public static function on_langpack_removed(\tool_langimport\event\langpack_removed $event) {
        global $DB, $CFG;
        $langcode = $event->other['langcode'];
        if (preg_match('/^(.*)_wp$/', $langcode, $matches)) {
            $parentlang = $matches[1];
            if (array_key_exists($parentlang, get_string_manager()->get_list_of_translations())) {
                $DB->execute('UPDATE {course} SET lang=? WHERE lang=?', [$parentlang, $langcode]);
                $DB->execute('UPDATE {user} SET lang=? WHERE lang=?', [$parentlang, $langcode]);
                if ($CFG->lang === $langcode) {
                    set_config('lang', $parentlang);
                }
            }
        }
    }
}
