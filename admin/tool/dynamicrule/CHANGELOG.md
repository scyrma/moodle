# Changelog

## 3.10 - 2020-12-01
### Changed
- Outcomes processing is now executed using `apply_to_user` method and
  `apply_to_users` is deprecated. Developers are advised to review the code
  and replace `apply_to_users` accordingly. If applying outcome is performance
  intensive, use `setup_for_applying` method to make necessary preparation.
  [WP-2353](https://tracker.moodle.org/browse/WP-2353)

## Previous versions
Changelog was not maintained before version 3.10
