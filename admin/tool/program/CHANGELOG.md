# Changelog

## Unreleased
### Changed
- `api::get_user_allocation_statuses` return array now contains 'status' and 'stringid' only.

## 4.0 - 2022-10-14
### Changed
- `api::get_programs_with_criteria_conditions` now uses completeddate instead of timecreated to query
   for programs completed. The method `program_tree_progress::save_set_as_completed()` always inserts 
   completeddate=time(), which means that completeddate will always be the same as timecreated, but
   in some edge cases it could differ maybe about 1 second.

## 3.11 - 2021-06-08
### Changed
- `api::send_program_user_allocated_notification` is deprecated. Please use 
  api::send_program_user_allocation_created_notification function instead.

## Previous versions
Changelog was not maintained before version 3.11
