## tables

### rors

ror_id
ror
locale
name
status

### ror_settings

ror_setting_id
ror_id
locale
setting_name
setting_value

### author_affiliations

author_affiliation_id
ror
locale
name

### author_affiliation_settings

author_affiliation_setting_id
author_affiliation_id
locale
setting_name
setting_value

## ror_display_lang

no_lang_code replace with en

## csv structure

| index | ror                                     | ojs                                             |
|-------|-----------------------------------------|-------------------------------------------------|
| 0     | id                                      | rors.ror                                        |
| 1     | admin.created.date                      |                                                 |
| 2     | admin.created.schema_version            |                                                 |
| 3     | admin.last_modified.date                |                                                 |
| 4     | admin.last_modified.schema_version      |                                                 |
| 5     | domains                                 |                                                 |
| 6     | established                             |                                                 |
| 7     | external_ids.type.fundref.all           |                                                 |
| 8     | external_ids.type.fundref.preferred     |                                                 |
| 9     | external_ids.type.grid.all              |                                                 |
| 10    | external_ids.type.grid.preferred        |                                                 |
| 11    | external_ids.type.isni.all              |                                                 |
| 12    | external_ids.type.isni.preferred        |                                                 |
| 13    | external_ids.type.wikidata.all          |                                                 |
| 14    | external_ids.type.wikidata.preferred    |                                                 |
| 15    | links.type.website                      |                                                 |
| 16    | links.type.wikipedia                    |                                                 |
| 17    | locations.geonames_id                   |                                                 |
| 18    | locations.geonames_details.country_code |                                                 |
| 19    | locations.geonames_details.country_name |                                                 |
| 20    | locations.geonames_details.lat          |                                                 |
| 21    | locations.geonames_details.lng          |                                                 |
| 22    | locations.geonames_details.name         |                                                 |
| 23    | names.types.acronym                     |                                                 |
| 24    | names.types.alias                       |                                                 |
| 25    | names.types.label                       | ror_settings[locale,setting_name,setting_value] |
| 26    | names.types.ror_display                 | rors.name                                       |
| 27    | ror_display_lang                        | rors.locale                                     |
| 28    | relationships                           |                                                 |
| 29    | status                                  | rors.status                                     | 