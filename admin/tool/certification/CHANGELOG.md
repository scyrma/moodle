# Changelog

## Unreleased
### Changed
- `api::get_user_allocation_status` return array now contains 'status' and 'stringid' only.

## 3.11 - 2021-06-08
### Changed
- `api::send_certification_user_allocated_notification` is deprecated. Please use 
  api::send_certification_user_allocation_created_notification function instead.
- `api::translate_relativedate_string` is deprecated. Please use 
  tool_wp\local\helpers\string_helper::translate_relativedate_string() function 
  instead.

## Previous versions
Changelog was not maintained before version 3.11
