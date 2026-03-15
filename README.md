# AZCB Conference Registration

Conference registration system for the Arizona Council of the Blind 2026 Annual Conference and Business Meeting.

## Repository Contents

| Path | Description |
|------|-------------|
| `azcb-conference-registration/` | **The WordPress plugin** — everything needed for deployment |
| `PRD-conference-registration.md` | Product Requirements Document (v 1.2) |
| `data-model.md` | Database schema documentation |
| `plugins/code-snippets/` | Legacy membership-lookup Code Snippet (superseded by the plugin) |

---

## Deployment Instructions

### Prerequisites

* WordPress 5.8+ with PHP 7.4+
* A `/conference/` page must already exist (the plugin creates child pages under it)
* The membership CSV must be publicly accessible at a URL
* `wp_mail()` must be working (SMTP plugin recommended for production)

### Step 1 — Upload the Plugin

**Option A — SFTP / File Manager:**

1. Upload the entire `azcb-conference-registration/` folder to `wp-content/plugins/` on the server.

**Option B — ZIP upload via admin:**

1. Zip the `azcb-conference-registration/` folder.
2. Go to **Plugins → Add New → Upload Plugin** and upload the zip.

### Step 2 — Activate

1. Go to **Plugins → Installed Plugins**.
2. Find **AZCB Conference Registration** and click **Activate**.
3. On activation the plugin will:
   * Create two database tables (`wp_azcb_conf_registrations`, `wp_azcb_conf_tokens`).
   * Create four WordPress pages under `/conference/`:
     * `/conference/verify/` — Email verification form
     * `/conference/verify/sent/` — "Check your email" page
     * `/conference/register/` — Registration form (magic-link entry)
     * `/conference/register/confirmation/` — Confirmation page

### Step 3 — Configure Settings

Go to **AZCB Conference → Settings** in the admin sidebar.

#### General Tab
| Setting | Description | Default |
|---------|-------------|---------|
| Members CSV URL | Full URL to the membership CSV | `https://azcb.org/wp-content/uploads/2025/10/azcb_members.csv` |
| CSV Cache (minutes) | How long the downloaded CSV is cached | 15 |
| Magic Link Expiry (minutes) | How long verification links stay valid | 30 |
| Rate Limit (per email/hour) | Max verification emails per address per hour | 5 |
| Contact Page URL | Used in `{contact_url}` placeholders | `https://azcb.org/contact-us/` |
| Membership Page URL | Used in `{membership_url}` placeholders | `https://azcb.org/membership/` |
| /convention/ → /conference/ Redirect | 302 redirect from old URL | Enabled |

#### Page Content Tab

All headings, intro text, button labels, confirmation messages, and footer text are editable. HTML is allowed in rich-text fields. Use `{contact_url}`, `{membership_url}`, and `{expiry_minutes}` as placeholders.

#### Email Templates Tab

Edit subjects and bodies for:
* **Magic Link Email** — Placeholders: `{first_name}`, `{last_name}`, `{link_url}`, `{expiry_minutes}`, `{contact_url}`, `{site_name}`
* **Member Confirmation Email** — Placeholders: `{first_name}`, `{last_name}`, `{contact_url}`, `{site_name}`
* **Non-Member Confirmation Email** — Placeholders: `{first_name}`, `{last_name}`, `{contact_url}`, `{membership_url}`, `{site_name}`

### Step 4 — Verify the Flow

1. Visit `/conference/verify/` and submit a test name + email.
2. Check for the magic-link email (verify subject, body, link).
3. Click the link and complete the registration form.
4. Confirm you land on the correct confirmation page (member vs non-member).
5. Check the admin list at **AZCB Conference → Registrations** for the new entry.
6. Test CSV export and row actions (mark member, resend email, delete).

### Step 5 — Link Registration to Your Conference Page

The plugin creates its own `/conference/verify/` page, but you need to connect it to your existing conference page so visitors can find it. Pick one:

**Option A — Add a "Register Now" link (recommended):**

Open your existing `/conference/` page in the WordPress editor and add a button or link pointing to the verify page:

```html
<a href="/conference/verify/" class="wp-block-button__link">Register for the Conference</a>
```

This keeps your conference info page as-is and sends visitors into the registration flow when they click.

**Option B — Embed the form directly:**

If you want the verification form to appear *on* the conference page itself, add this shortcode to the page content:

```
[azcb_conference_verify]
```

The remaining steps (check-your-email, register, confirmation) still use the auto-created child pages.

### Step 6 — Go Live

1. Verify the membership CSV URL is correct and up to date.
2. Test the full flow end-to-end one more time from the conference page.
3. Disable or remove the legacy Code Snippets membership-lookup snippet if it's no longer needed.

---

## Managing Registrations

Navigate to **AZCB Conference → Registrations** to:

* **View** all registrations with filtering (All / Members / Non-Members) and search
* **Toggle member status** — "Mark Member" or "Mark Non-Member" row actions
* **Resend confirmation email** — row action to re-send
* **Delete** a registration
* **Export CSV** — downloads all registrations as a UTF-8 CSV file

---

## How It Works

1. **Verify** — Visitor enters First Name, Last Name, Email → plugin silently checks the membership CSV → generates a magic-link token → sends verification email.
2. **Register** — Visitor clicks the magic link → arrives at the registration form pre-filled with data from the CSV (if member) → submits → record saved to DB → confirmation email sent.
3. **Confirm** — Visitor is redirected to a member or non-member confirmation page based on CSV match results. Non-member copy gracefully explains how to join.

---

## Uninstall

Deleting the plugin via **Plugins → Delete** will:
* Drop both custom database tables
* Remove all `azcb_conf_*` options
* Trash the four created pages (recoverable from Trash)
* Clean up transients

---

## Security

* Nonces on every form and admin action
* All output escaped with `esc_html`, `esc_attr`, `esc_url`
* All SQL uses `$wpdb->prepare()`
* Rate limiting on verification emails (transient-based)
* Honeypot field on forms
* Capability checks (`manage_options`) on all admin pages
* Single-use magic-link tokens with configurable expiry
* No PII written to logs
