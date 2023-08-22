# Changelog

## 3.11 - 2021-06-08
### Changed
- `render_placeholders` in renderer is deprecated. This was used
  exclusively to pre-render notification placeholders in conditions and
  outcomes. The rendering was moved to relevant output methods, conditions and
  outcomes don't need to render placeholders.

## 3.10.1 - 2021-03-09
### Changed
- `permission::can_edit_cohort_condition` is deprecated. Necessary check is
  performed in cohort_member abstract class.

## 3.10 - 2020-12-01
### Changed
- Outcomes processing is now executed using `apply_to_user` method and
  `apply_to_users` is deprecated. Developers are advised to review the code
  and replace `apply_to_users` accordingly. If applying outcome is performance
  intensive, use `setup_for_applying` method to make necessary preparation.
  [WP-2353](https://tracker.moodle.org/browse/WP-2353)

## Previous versions
Changelog was not maintained before version 3.10
