# Changelog

## 4.1 - 2023-01-11
### Changed
- Deprecated helper::get_export_import_status_formatted() function
- The following deprecated Behat steps have now been removed:
  * `I press "X" in the modal form dialogue`
  * `I open the autocomplete suggestions list in the dialog`
  * `I set the visible field "X" to "Y"`
  * `I set the following visible fields to these values`
  * `I click on "X" "Y" in the "Z" table tree node`
  * `"X" "Y" should exist in the "Z" table tree node`
  * `"X" "Y" should not exist in the "Z" table tree node`
- Deprecated all functions in \tool_wp\db class, please use functions
  from \core_reportbuilder\local\helpers\database instead

## 4.0 - 2022-10-14
### Changed
- Deprecated "Tabs" functionality. Developers who needs same style tabs are advised
  to use \core\output\dynamic_tabs. In Workplace components however tabs are replaced with
  Secondary navigation menu, use tool_wp\output\secondary_tabs to implement tabs this way.

## 3.11.1 - 2021-07-20
### Changed
- Deprecated "Notifications" functionality since styled toast notifications are
  now implemented in core - JS module: core/toast

## 3.11 - 2021-06-08
### Changed
- Deprecated "Modal forms" functionality since it is now implemented in core -
  class \core_form\modal_form; web service tool_wp_modal_form,
  JS modules: tool_wp/modal_form and tool_wp/ajax_form.
  See \core_form\dynamic_form and https://docs.moodle.org/dev/Modal_and_AJAX_forms
- To display forms inside the tabs (\tool_wp\output\tab_form) you must submit
  an instance of a class extending \core_form\dynamic_form

## Previous versions
Changelog was not maintained before version 3.11
