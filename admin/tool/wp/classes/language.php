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
 * Class language
 *
 * @package     tool_wp
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

/**
 * Class language
 *
 * @package     tool_wp
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * @return array
     */
    protected static function get_wp_languages_with_parents() {
        global $CFG;
        $cache = \cache::make('core', 'langmenu');
        $cachekey = 'langparents';
        if (($parents = $cache->get($cachekey)) !== false) {
            return $parents;
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

        $cache->set($cachekey, $parents);
        return $parents;
    }
}
