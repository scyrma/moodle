# Changelog
All notable changes to the API will be documented in this file.

## 4.1 - 2023-01-11
### Changed
- The `userinfo_section::set_overdue` method is deprecated, calling code should now set individual
  section items as overdue by calling `userinfo_section_item::set_overdue` instead
