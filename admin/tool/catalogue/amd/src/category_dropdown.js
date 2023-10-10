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

/**
 * Set the category selector events.
 *
 * @module     tool_catalogue/category_dropdown
 * @author     2023 Mohamed A. Shehata <mohamed.shehata@moodle.com>
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
import SELECTORS from 'tool_catalogue/selectors';

/**
 * The breakpoint between mobile and large screen.
 */
const largeScreenBreakpoint = 992;

/**
 * General selector container element.
 */
let categorySelector = null;

/**
 * Max level depth based on category depth limit.
 */
let maxLevelDepth = 0;

/**
 * Get most count of dropdown-items that exists in all levels.
 *
 * @param {Element} element The parent dropdown element to get its count.
 * @returns {Integer}
 */
const getMaxLevelSubmenusItemsCount = (element) => {
    let count = 0;
    element.querySelectorAll('.dropdown-submenu').forEach((element) => {
        // We get all dropdown items with no extended submenu.
        const dropdownItems = element.querySelectorAll(
            ':scope li > a.dropdown-item[data-item-type="category"]'
        ).length;
        // Check against previous most counting submenus.
        count = Math.max(count, dropdownItems);
    });
    return count;
};

/**
 * Update dropdown level height.
 */
const updateDropdownHeight = () => {
    // Reset everything before start.
    const dropdownSubMenu = categorySelector.querySelectorAll(".dropdown-submenu .dropdown-menu");
    const topLevelDropdown = categorySelector.querySelector('.dropdown-menu.multi-level');
    topLevelDropdown.style.height = 'auto';
    topLevelDropdown.style.width = 'auto';
    dropdownSubMenu.forEach(dropdown => {
        dropdown.style.height = 'auto';
        dropdown.style.width = 'auto';
    });
    if (topLevelDropdown.classList.contains('show')) {
        const maxSubcategories = getMaxLevelSubmenusItemsCount(topLevelDropdown);
        const topLevelDropdownSubMenus = topLevelDropdown.querySelectorAll(".dropdown-menu.multi-level > .dropdown-submenu");
        const firstSubMenu = topLevelDropdownSubMenus[0];
        const singleHeight = firstSubMenu.offsetHeight;
        const currentPadding = topLevelDropdown.offsetHeight - (topLevelDropdownSubMenus.length * singleHeight);

        // Calculate the max height and width.
        // We make height of max dropdown list height of all sub-menus.
        // We also split the 100% width of container over the max-depth.
        const maxHeight = Math.max(((maxSubcategories * singleHeight) + currentPadding), topLevelDropdown.clientHeight);
        const maxWidth = categorySelector.clientWidth / maxLevelDepth;
        // Apply the calculated height and width to all elements
        dropdownSubMenu.forEach(dropdown => {
            if (window.innerWidth > largeScreenBreakpoint) {
                dropdown.style.height = `${maxHeight}px`;
                dropdown.style.width = `${maxWidth}px`;
            }
        });
        if (window.innerWidth > largeScreenBreakpoint) {
            topLevelDropdown.style.height = `${maxHeight}px`;
            topLevelDropdown.style.width = `${maxWidth}px`;
        } else {
            topLevelDropdown.style.height = 'auto';
            topLevelDropdown.style.width = 'auto';
        }
    }
};

/**
 * Add events listeners and actions
 * @param {integer} maxDepth
 */
export const init = (maxDepth) => {
    categorySelector = document.querySelector(SELECTORS.regions.categorySelector);
    maxLevelDepth = maxDepth;
    const categorySelectorBtn = categorySelector.querySelector('.tool_catalogue-category-selector-btn');
    const topLevelDropdown = categorySelector.querySelector('.dropdown-menu.multi-level');

    categorySelectorBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        // Proceed to course content.
        topLevelDropdown.classList.toggle('show');
        updateDropdownHeight();
    });

    // Hide dropdown when autofocusses of selector button.
    document.addEventListener("click", (event) => {
        // Check if the clicked element is not the button or the dropdown menu
        if (event.target !== categorySelectorBtn && !categorySelectorBtn.contains(event.target) &&
            event.target !== topLevelDropdown && !topLevelDropdown.contains(event.target)) {
            // Close the dropdown
            topLevelDropdown.classList.remove('show');
        }
    });

    // Show subcategories when clicking on a category with subcategories
    categorySelector.querySelectorAll('.has-submenu').forEach(item => {
        item.addEventListener('click', event => {
            if (window.innerWidth < largeScreenBreakpoint) {
                event.preventDefault();
                event.stopPropagation();
            }
            const dropdownMenu = item.nextElementSibling?.nextElementSibling;
            if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                dropdownMenu.classList.toggle('show');
            }
        });
    });

    // Hide subcategories and go back to parent categories on back button click
    categorySelector.querySelectorAll('.tool_catalogue-back-button').forEach(item => {
        item.addEventListener('click', event => {
            event.stopPropagation();
            const dropdownMenu = item.closest('.dropdown-menu.show');
            if (dropdownMenu) {
                dropdownMenu.classList.remove('show');
            }
        });
    });

    // Close all subcategories clicking on X.
    categorySelector.querySelectorAll('.tool_catalogue-dropdown-close').forEach(item => {
        item.addEventListener('click', () => {
            const multiLevelDropdowns = categorySelector.querySelectorAll('.dropdown-menu.show');
            multiLevelDropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        });
    });

    // Update dropdown height on page resize.
    window.addEventListener('resize', () => {
        updateDropdownHeight();
    });
};