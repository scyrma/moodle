# Changelog

## 4.0 - 2022-10-14
### Changed
- This plugin was not intended as API or base plugin for others. We may remove or rename functions
  and classes or change their arguments but we will do it only in major versions. In this version
  the following were changed:
  - The `\mod_appointment\output\session_signup` class constructor now requires a second
    argument specifying the module context of the appointment activity
  - Removed unused functions `format_duration()`, `appointment_minutes_to_hours()`, 
    `appointment_hours_to_minutes()`, `mod_appointment\permission::require_can_manage_customfields()`,
    `appointment_get_appointment_menu()`, `appointment_get_userfields()`, `appointment_get_user_customfields()`,
    `appointment_update_user_calendar_events()`, `appointment_get_session_customfields()`, `appointment_manager_needed()`,
    `permission::can_edit_custom_fields()`
  - Constant `MDL_MANAGERSEMAIL_FIELD` renamed to `MOD_APPOINTMENT_MANAGERSEMAIL_FIELD`
  - Function `cleanup_session_data()` renamed to `appointment_cleanup_session_data()`
  - Removed unused class `mod_appointment_cancelsignup_form`
  - Removed unused/unnecessary events: `cancel_booking_failed`, `take_attendance_failed`,
    `update_manageremail_failed`, `signup_failed`
  - Complete removed functionality related to the trainers and session roles, including database table
    `appointment_session_roles`, admin setting `$CFG->appointment_session_roles`, functions
    `appointment_update_trainers`, `appointment_get_trainer_roles`, `appointment_get_trainers`

## 3.11 - 2021-06-08
### Changed
- Removed appointment_session_field and appointment_session_data tables and relevant use.
  They were not in use since we switched to core custom fields at the point when activity
  was included in Workplace.

## Previous versions
Changelog was not maintained before version 3.11
