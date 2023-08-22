# Changelog

## 3.11.12 - 2023-01-17
### Changed
- `api::deallocate_users_after_grace_period_end` and `api::reallocate_user_into_initial_program` have a new argument
  $rollbackinitialcertdates that indicates if users need to be reallocated to initial program after
  recertification period end even with the original allocation dates.

## 3.11 - 2021-06-08
### Changed
- `api::send_certification_user_allocated_notification` is deprecated. Please use 
  api::send_certification_user_allocation_created_notification function instead.
- `api::translate_relativedate_string` is deprecated. Please use 
  tool_wp\local\helpers\string_helper::translate_relativedate_string() function 
  instead.

## Previous versions
Changelog was not maintained before version 3.11
