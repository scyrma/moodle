# Changelog

## 4.1 - 2023-01-11
### Changed
- `api::deallocate_users_after_grace_period_end` and `api::reallocate_user_into_initial_program` have a new argument
   $rollbackinitialcertdates that indicates if users need to be reallocated to initial program after 
   recertification period end even with the original allocation dates.
- `api::get_user_allocation_status` return array now contains 'status' and 'stringid' only.
- The following deprecated Behat steps have now been removed:
  * `the following tool certification data "X" exist`
  * `the following users allocations to certifications exist`

## 3.11 - 2021-06-08
### Changed
- `api::send_certification_user_allocated_notification` is deprecated. Please use 
  api::send_certification_user_allocation_created_notification function instead.
- `api::translate_relativedate_string` is deprecated. Please use 
  tool_wp\local\helpers\string_helper::translate_relativedate_string() function 
  instead.

## Previous versions
Changelog was not maintained before version 3.11
