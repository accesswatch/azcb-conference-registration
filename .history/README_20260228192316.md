AZCB Conference Registration — trimmed repository

This repository contains only the PRD, data model, and a membership lookup plugin snippet.

Files of interest:
- PRD: [PRD-conference-registration.md](PRD-conference-registration.md)
- Data model: [data-model.md](data-model.md)
- Plugin: [plugins/code-snippets/azcb_membership_lookup_and_fill.php](plugins/code-snippets/azcb_membership_lookup_and_fill.php)

Plugin configuration

The plugin fetches a members CSV. You can configure its location in two ways (priority order):

1) Define a constant in `wp-config.php` or plugin bootstrap:

   define('AZCB_MEMBERS_CSV_URL', 'https://example.org/wp-content/uploads/2025/10/azcb_members.csv');

2) Use the admin UI: Settings → AZCB Members CSV. The option is stored as `azcb_members_csv_url` in the WordPress options table.

Notes

- The constant takes precedence over the admin option.
- The admin option is registered and sanitized with `esc_url_raw`.
- After changing the CSV URL, verify the file is publicly accessible and matches expected CSV headers (e.g., "First Name", "Last Name", "Email Address", "Zip").
