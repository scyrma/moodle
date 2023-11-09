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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
use Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat step definitions for tool_catalogue
 *
 * @package    tool_catalogue
 * @category   test
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_catalogue extends behat_base {

    /**
     * Return the list of partial named selectors.
     *
     * Those selectors can be used to capture dashboard elements. Examples:
     *    And I should see "12 courses" in the "ProgramName" "tool_catalogue > Catalogue item"
     *
     * @return array
     */
    public static function get_partial_named_selectors(): array {
        return [
            new behat_component_named_selector('Catalogue item', [
                ".//*[@data-region='catalogue-item'][.//@data-region='item-name'][contains(.,%locator%)]",
            ]),
            new behat_component_named_selector('Catalogue pagesection', [
                ".//*[@data-region='catalogue-pagesection'][.//@data-region='pagesection-name'][contains(.,%locator%)]",
            ]),
            new behat_component_named_selector('Recently accessed course card', [
                ".//div[contains(@class, 'card dashboard-card')][.//@class='coursename'][contains(., %locator%)]",
            ]),
        ];
    }

    /**
     * Action to hover over specific link.
     *
     * @Then I hover over the :phrase link
     * @param string $phrase link phrase to hover over.
     */
    public function i_hover_over_link($phrase) {
        $session = $this->getSession();
        // Get all link elements on the page.
        $links = $session->getPage()->findAll('css', 'a');
        foreach ($links as $link) {
            if ($link->getText() == $phrase) {
                $link->mouseOver();
                return;
            }
        }
        throw new \InvalidArgumentException(sprintf('Could not find anchor element matching "%s"', $phrase));
    }
}
