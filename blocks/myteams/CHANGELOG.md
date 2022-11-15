# Changelog
All notable changes to the API will be documented in this file.

## Unreleased
### Changed
- The `userinfo_section::set_overdue` method is deprecated, calling code should now set individual
  section items as overdue by calling `userinfo_section_item::set_overdue` instead
