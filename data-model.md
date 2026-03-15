# AZCB Membership Data Model

## Data Source

The **sole membership data store** is a static CSV file uploaded to the WordPress Media Library:

```
https://azcb.org/wp-content/uploads/2025/10/azcb_members.csv
```

This is **not** a database — it is a flat file that must be manually re-uploaded when membership data changes. It is fetched at runtime by the PHP code snippet on every form submission.

## CSV Column Schema (derived from `$field_map`)

The CSV header row contains at least these columns (names are exact, case-sensitive):

| Column | Purpose | Used in Match? |
|---|---|---|
| `First Name` | Member first name | **Yes** |
| `Last Name` | Member last name | **Yes** |
| `Email Address` | Member email | **Yes** |
| `Zip` | Postal code (normalized to 5 digits) | **Yes** |
| `Middle Name` | Member middle name | No |
| `Title` | Professional title | No |
| `Suffix` | Name suffix (Jr, Sr, etc.) | No |
| `Salutation` | Honorific (Mr, Mrs, Dr, etc.) | No |
| `Address 1` | Street address line 1 | No |
| `Address 2` | Street address line 2 | No |
| `City` | City | No |
| `State/Province` | State or province | No |
| `Country` | Country | No |
| `Home Phone` | Home phone number | No |
| `Mobile Phone` | Mobile phone number | No |
| `Gender` | Gender | No |
| `Ethnicity` | Ethnicity | No |
| `Vision Status` | Vision status | No |
| `BF Format` | Braille Forum format preference | No |
| `Preferred Mail Format` | Mail format preference | No |
| `Membership category` | Used for lifetime detection | No (flag only) |
| `ACB Life` | Alternate lifetime flag column | No (flag only) |

> **Note**: The CSV may contain additional columns not mapped in the code. Only the 20 columns in `$field_map` plus the match columns and lifetime columns are used.

## Membership Lookup Logic (Form 13)

### Hook
```php
add_filter( 'gform_entry_post_save_13', 'azcb_membership_lookup_and_fill', 10, 2 );
```

This runs **server-side** after the user submits Form 13 ("Find Membership"). It is **not** AJAX — it's a full form submission + page reload.

### Match Algorithm
1. User enters: First Name, Last Name, Email, Zip
2. All 4 values normalized: `strtolower(trim(...))`, Zip stripped to digits and truncated to 5
3. CSV fetched via `wp_remote_get()` on every submission (no caching)
4. Linear scan of all rows — **ALL 4 fields must match exactly** (case-insensitive)
5. First match wins (stops scanning)

### Output on Match
- **Field 6** (`match_found`): set to `'yes'`
- **Field 9** (`is_life`): set to `'yes'` if lifetime member, `'no'` otherwise
- **Fields 11–30**: populated from the matching CSV row (see mapping below)
- **Field 10** (`payload_json`): JSON object of all mapped column values

### Output on No Match
- **Field 6** (`match_found`): stays `'no'`
- All other fields cleared to empty string

### Lifetime Member Detection
Checks (in order):
1. If CSV has `Membership category` column → looks for "life" substring
2. Else if CSV has `ACB Life` column → looks for "life", "yes", "y", or "1"

## Form 13 → CSV Field Mapping

| Form Field ID | Hidden Field Purpose | CSV Column |
|---|---|---|
| 1 | First Name (user input) | — |
| 3 | Last Name (user input) | — |
| 31 | Email (user input, gpev-field) | — |
| 5 | Zip (user input) | — |
| 6 | match_found flag | — |
| 7 | (unmapped / unused) | — |
| 9 | is_life flag | Membership category / ACB Life |
| 10 | payload_json (hidden textarea) | All mapped columns as JSON |
| 11 | m_last_name | Last Name |
| 12 | m_first_name | First Name |
| 13 | m_middle_name | Middle Name |
| 14 | m_title | Title |
| 15 | m_suffix | Suffix |
| 16 | m_salutation | Salutation |
| 17 | m_address_1 | Address 1 |
| 18 | m_address_2 | Address 2 |
| 19 | m_city | City |
| 20 | m_state | State/Province |
| 21 | m_zip | Zip |
| 22 | m_country | Country |
| 23 | m_email | Email Address |
| 24 | m_home_phone | Home Phone |
| 25 | m_mobile_phone | Mobile Phone |
| 26 | m_gender | Gender |
| 27 | m_ethnicity | Ethnicity |
| 28 | m_vision_status | Vision Status |
| 29 | m_bf_format | BF Format |
| 30 | m_preferred_mail_format | Preferred Mail Format |
| 32 | Honeypot (spam) | — |

## Implications for Conference Registration

1. **No real database** — The membership "database" is a CSV that must be exported from somewhere (likely ACB national or a spreadsheet) and uploaded to WP.
2. **No authentication** — The lookup is just name+email+zip matching. No passwords, no tokens, no session.
3. **Pre-fill data available** — On a successful match, fields 11–30 provide the full member profile that can be used to pre-fill the conference registration form.
4. **Stale data risk** — CSV is dated October 2025 per the URL path. Members who joined/changed data since then won't match or will have outdated info.
5. **Unified flow** — All registrants enter the same path (name + email → magic link → register). The system silently checks CSV membership and stores `is_member` (1 = found, 0 = not found). The non-member confirmation gracefully handles both true non-members and members who couldn't be matched, inviting them to contact AZCB.
6. **The conference registration magic link** verifies every registrant's email while also carrying the CSV lookup result and pre-fill data.
7. **Two-state member status** — `is_member` (1/0) replaces the earlier three-state model. Simplifies the flow and data model.
8. **Lifetime members** get special treatment — the `is_life` flag could affect conference pricing or registration options.

## Source File Locations

- PHP Code Snippet: `plugins/code-snippets/azcb_membership_lookup_and_fill.php`
- Form 13 annotated JSON: `forms/form-13-annotated.json`
- Form 13 raw HTML: `pages/findmembership-842.html`
- Form 12 (Renewal) raw HTML: `pages/renew-825.html`
