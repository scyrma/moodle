# Changelog

## 4.1.2 Rolling - 2023-03-14
### Changed
* The following SCSS variables `$icon-size-[base|large|xlarge]` had been removed.
* The following helper classes `.icon-[large|lg|xlarge|xl]` had been removed , use `.icon-size-[1-5]` instead.
* The following helper classes `.icon-{theme-colour}` had been removed use `.text-{theme-colour}` instead.

## 4.1 - 2023-01-11
### Changed
* \theme_workplace\manager::get_site_name() method has been removed.
* Styles for '[data-region="wp-toggle"]' have been removed.

## 4.0 - 2022-10-14
### Changed
* The following templates had been removed:
  - templates/core/block.mustache
  - templates/core/single_button.mustache
  - templates/core_course/activity_navigation.mustache
  - templates/theme_boost/columns2.mustache
  - templates/theme_boost/flat_navigation.mustache
  - templates/theme_boost/maintenance.mustache
  - templates/action_link.mustache
  - templates/course_header_image.mustache
  - templates/header.mustache
  - templates/headerbtn.mustache
  - templates/larrow.mustache
  - templates/rarrow.mustache
  - templates/usermenu.mustache
* 'dashboard' layout is not used anymore and has been removed. Also the following templates:
  - templates/wpdashboard.mustache
  - templates/wpdashboard_content.mustache
* is_dashboard() method has been removed.
* Setting 'wpmenumodal' has been removed. Because of that the following templates had been removed too:
  - templates/wpmenu_link.mustache
  - templates/wpmenu_modal.mustache
* The following templates had been renamed:
  - templates/wpmenu_dropdown.mustache => templates/local/wpmenu/dropdown.mustache
  - templates/wpmenu_item.mustache => templates/local/wpmenu/item.mustache
* The method override full_header() has been been removed.
* The method override user_menu() has been been removed.
* Methods override rarrow() and larrow() had been removed.
* New 'drawers' layout has been added. 'dashboard' and 'columns2' layouts had been replaced with it in theme config.
* 'My courses' page type has been added.
* Class theme_workplace\workplace has been renamed to theme_workplace\manager and the following methods removed:
  - removenav()
  - loginbackgroundimage()
  - dashboard()
* The method get_site_name() has been deprecated. Because of that the following template overrides are no longer needed:
  - columns1.php
  - login.php
  - embedded.php
  - maintenance.php
  - secure.php
* A new callback has been added to the theme allowing plugins to add elements to the Workplace launcher.
  Please refer to 'theme_workplace_theme_workplace_menu_items' method for an example of making use of this new feature.
* A new page layout 'stardardnonav' has been added. When used in conjunction with render_custom_navbar() method it allows
  you to display standard pages with a custom navbar.
* Fontello font added to the theme to display custom SVG icons in the navbar.

## Previous versions
Changelog was not maintained before version 4.0
