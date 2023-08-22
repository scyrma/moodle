# Changelog

## 4.2.2 Rolling - 2023-08-22
### Changed
- New method `allow_non_authenticated_users_access` added to base audience class, to be implemented
  for any audience types to allow guest and/or non-authenticated user access to pages

## 4.1 - 2023-01-11
### Changed
- The following local helper methods have been deprecated, their implementation moved to exporters:
  `audience::get_all_audiences_menu_types` -> `page_audience_cards_exporter`
