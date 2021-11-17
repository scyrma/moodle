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
 * File containing tests for tool_wp\language class.
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_wp\language class methods.
 *
 * @package    tool_wp
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_language_testcase extends advanced_testcase {

    /**
     * Test for funciton get_browser_accepted_languages()
     */
    public function test_get_browser_accepted_languages() {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '*';
        $this->assertEquals([], $this->get_browser_accepted_languages());
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de';
        $this->assertEquals(['de_wp', 'de'], $this->get_browser_accepted_languages());
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en';
        $this->assertEquals(['en_us_wp', 'en_wp', 'en_us', 'en'], $this->get_browser_accepted_languages());
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en,de';
        $this->assertEquals(['en_wp', 'de_wp', 'en', 'de'], $this->get_browser_accepted_languages());
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-CH, fr;q=0.9, en;q=0.8, de;q=0.7, *;q=0.5';
        $this->assertEquals(['fr_ch_wp', 'fr_wp', 'en_wp', 'de_wp', 'fr_ch', 'fr', 'en', 'de'],
            $this->get_browser_accepted_languages());
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    /**
     * Test for function get_recommended_language()
     */
    public function test_get_recommended_language() {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en';
        $this->assertEquals('en', \tool_wp\language::get_recommended_language());
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-CH';
        $this->assertEquals('', \tool_wp\language::get_recommended_language());
    }

    /**
     * Test for function get_default_install_language()
     */
    public function test_get_default_install_language() {
        global $CFG;
        $langdir = $CFG->dirroot . '/install/lang';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-CH';
        $this->assertEquals('de_ch', \tool_wp\language::get_default_install_language($langdir));
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-MX,es';
        $this->assertEquals('es_wp', \tool_wp\language::get_default_install_language($langdir));
    }

    /**
     * Calls method tool_wp\language::get_browser_accepted_languages()
     *
     * @return mixed
     */
    protected function get_browser_accepted_languages() {
        $class = new ReflectionClass(tool_wp\language::class);
        $method = $class->getMethod('get_browser_accepted_languages');
        $method->setAccessible(true);
        return $method->invokeArgs(null, []);
    }

    /**
     * Calls method tool_wp\language::get_wp_languages_with_parents()
     *
     * @return mixed
     */
    protected function get_wp_languages_with_parents() {
        $class = new ReflectionClass(tool_wp\language::class);
        $method = $class->getMethod('get_wp_languages_with_parents');
        $method->setAccessible(true);
        return $method->invokeArgs(null, []);
    }

    /**
     * Tests for function get_wp_languages_with_parents
     */
    public function test_get_wp_languages_with_parents() {
        global $CFG;
        $this->resetAfterTest();
        $this->assertEquals([], $this->get_wp_languages_with_parents());
        cache::make('core', 'langmenu')->purge();
        $langotherroot = $CFG->langotherroot;
        $langlocalroot = $CFG->langlocalroot;
        $CFG->langotherroot = $CFG->langlocalroot = __DIR__ . '/fixtures/langtest';

        $this->assertEquals(['de_wp' => 'de', 'en_wp' => 'en'], $this->get_wp_languages_with_parents());

        $CFG->langotherroot = $langotherroot;
        $CFG->langlocalroot = $langlocalroot;
    }

    /**
     * Tests for function get_list_of_translations
     */
    public function test_get_list_of_translations() {
        global $CFG;
        $this->resetAfterTest();
        $langotherroot = $CFG->langotherroot;
        $langlocalroot = $CFG->langlocalroot;
        $CFG->langotherroot = $CFG->langlocalroot = __DIR__ . '/fixtures/langtest';
        $CFG->wphideparentlang = true;
        cache::make('core', 'langmenu')->purge();

        $languages = ['en' => 'English', 'en_wp' => 'English for Workplace'];
        $this->assertEquals(['en', 'en_wp'],
            array_keys(\tool_wp\language::get_list_of_translations($languages, true, [], [])));
        $this->assertEquals(['en_wp'],
            array_keys(\tool_wp\language::get_list_of_translations($languages, false, [], [])));

        $languages = ['de' => 'Deutsch', 'de_wp' => 'Deutsch für Arbeitsplatz'];
        $this->assertEquals(['de', 'de_wp'],
            array_keys(\tool_wp\language::get_list_of_translations($languages, true, [], [])));
        $this->assertEquals(['de_wp'],
            array_keys(\tool_wp\language::get_list_of_translations($languages, false, [], [])));

        $languages = ['es' => 'Español', 'es_wp' => 'Español para la Empresa'];
        $this->assertEquals(['es', 'es_wp'],
            array_keys(\tool_wp\language::get_list_of_translations($languages, true, [], [])));
        $this->assertEquals(['es', 'es_wp'],
            array_keys(\tool_wp\language::get_list_of_translations($languages, false, [], [])));

        $languages = ['es' => 'Español', 'es_wp' => 'Español para la Empresa',
            'en' => 'English', 'en_wp' => 'English for Workplace',
            'de' => 'Deutsch', 'de_wp' => 'Deutsch für Arbeitsplatz',
            'aa' => 'AA native name',
            'bb' => 'Something unknown',
            'cc_wp' => 'Something wp unknown'
            ];
        $this->assertEqualsCanonicalizing(['en_wp', 'es', 'es_wp', 'de_wp', 'aa', 'bb', 'cc_wp'],
            array_keys(\tool_wp\language::get_list_of_translations($languages, false, [], [])));

        $CFG->langotherroot = $langotherroot;
        $CFG->langlocalroot = $langlocalroot;
    }

}
