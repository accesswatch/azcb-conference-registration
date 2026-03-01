# Product Requirements Document: AZCB 2026 Conference Registration System

**Project:** Arizona Council of the Blind — 2026 Annual Conference & Business Meeting Registration  
**Version:** 1.0  
**Date:** February 26, 2026  
**Stakeholder:** Wesley (AZCB)  
**Developer:** Jeff  

---

## 1. Overview

Build a multi-step conference registration flow for the AZCB 2026 Annual Conference and Business Meeting. The system must:

- Verify AZCB membership via a magic-link email flow before registration.
- Allow non-members to register directly without the magic-link verification.
- Pre-fill registration fields for verified members.
- Deliver distinct confirmation messages based on membership status.
- Store conference registration data **separately** from AZCB membership records.

---

## 2. Goals & Success Criteria

| Goal | Metric |
|------|--------|
| Enable online registration for virtual conference | Registrants can complete the flow end-to-end |
| Distinguish members from non-members | Members receive business meeting links; non-members do not |
| Maintain data separation | Conference registrations stored in a dedicated dataset, not in membership records |
| Accessibility | All pages fully accessible (WCAG 2.1 AA minimum) — critical for this user base |

---

## 3. User Flows

### 3.1 Flow Diagram

```
azcb.org/convention/ ──302 redirect──► azcb.org/conference/
                                            │
                                            ▼
                                    [Conference Landing Page]
                                            │
                              ┌─────────────┴─────────────┐
                              ▼                           ▼
                   [Member Verification]         [Non-Member Registration]
                   (magic link flow)              (direct to Registration Page)
                              │
                              ▼
                   [Magic Link Sent Page]
                              │
                       (email link clicked)
                              │
                              ▼
                   [Registration Page]
                   (fields pre-filled)
                              │
                              ▼
              ┌───────────────┴───────────────┐
              ▼                               ▼
   [Member Confirmation]           [Non-Member Confirmation]
              │                               │
              ▼                               ▼
   [Email: Member Confirmation]    [Email: Non-Member Confirmation]
```

### 3.2 User Personas

| Persona | Description |
|---------|-------------|
| **AZCB Member** | Has registered and paid 2026 dues. Enters via member verification flow. Receives business meeting links. |
| **Non-Member** | Not a current AZCB member. Registers directly. Receives conference links only (not business meeting). |

---

## 4. Page & Component Specifications

### 4.1 Redirect: `/convention/` → `/conference/`

| Attribute | Value |
|-----------|-------|
| **Source URL** | `https://azcb.org/convention/` |
| **Target URL** | `https://azcb.org/conference/` |
| **Redirect Type** | **302 (Temporary)** |
| **Rationale** | AZCB may run a "convention" again in the future; a 301 (permanent) would cause browsers/search engines to cache the redirect indefinitely, making it difficult to reclaim the `/convention/` URL later. |

### 4.2 Conference Landing Page — `/conference/`

**Purpose:** Entry point. Provides conference information and routes users to the appropriate registration path.

**Content:**
- Conference details / welcome copy (existing page content)
- Link/button at the bottom navigating to the **Member Verification Page**

**Navigation:**
- "Register as a Member" → `/conference/verify/` (or equivalent)
- "Non-Member Registration" button → direct to `/conference/register/` (or external form)

---

### 4.3 Member Verification Page — `/conference/verify/`

**Purpose:** Collect identifying information from users who claim AZCB membership, then send a magic link to verify their email.

**Page Copy:**

> **Conference Registration for AZCB Members**
>
> If you are an AZCB member, please fill in the following information, so we can verify your eligibility, and simplify your online conference registration.
>
> Non-members, please tap this button to be taken to the online registration form.

**Form Fields:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| First Name | Text input | Yes | Used for magic link token & pre-fill |
| Last Name | Text input | Yes | Used for magic link token & pre-fill |
| Your Email Address | Email input | Yes | Magic link sent here |

**Actions:**
- **"Verify Membership Status"** button (submit) → Triggers magic link email, redirects to Magic Link Sent page
- **Non-member CTA button** → Navigates to the registration form (non-member path)

**Footer:**
> Have Questions?
>
> If you have questions about the conference, or if you wish to inquire about sponsoring the conference or making a donation, please [Send us a Message](https://azcb.org/contact-us/).

**Backend Logic:**
1. Receive form submission (first name, last name, email).
2. Generate a time-limited magic link token (see §5.1).
3. Look up the submitted info against the membership dataset to determine member status. Store the result with the token.
4. Send magic link email to the provided address.
5. Redirect browser to the Magic Link Sent page.

---

### 4.4 Magic Link Sent Page — `/conference/verify/sent/`

**Purpose:** Inform the user to check their email.

**Page Copy:**

> **Verifying Your Membership Status**
>
> Thanks for submitting your Membership Verification. Please go to your email inbox and click the link to continue with the Conference Registration. Note: This link expires in **30 minutes**.

**Design Notes:**
- The expiration time (e.g., 15 minutes) should be configurable; placeholder shown as "xx minutes" in the original spec — **confirm exact value with Wesley**.
- No form or navigation beyond standard site chrome.

---

### 4.5 Registration Page — `/conference/register/`

**Purpose:** Collect registration details for the conference.

**Page Copy:**

> **2026 Arizona Council of the Blind Annual Conference and Business Meeting – Registration Page**
>
> We're excited to welcome you to the Arizona Council of the Blind's 2026 Annual Conference and Business Meeting. There is no cost to attend this virtual event, but you do need to register by providing the following information. All fields are required.

**Form Fields:**

| Field | Type | Required | Pre-filled for Verified Members |
|-------|------|----------|---------------------------------|
| First Name | Text input | Yes | Yes |
| Last Name | Text input | Yes | Yes |
| Email | Email input | Yes | Yes |
| Mobile Phone | Phone input | **No** | Yes (if on file) |
| Your Zip Code | Text input | Yes | Yes (if on file) |

**Actions:**
- **"Complete your Registration"** button (submit)

**Behavior:**
- If the user arrives via a valid magic link token → fields are pre-filled from the verification submission (and/or membership data). Membership status is known.
- If the user arrives as a non-member (no token, or direct link) → fields are blank. Membership status = non-member.
- On submit:
  1. Validate all required fields.
  2. Save registration to the **conference registration dataset** (separate from membership).
  3. Send confirmation email (content matches the appropriate confirmation page — see §4.6 / §4.7).
  4. Redirect to the appropriate confirmation page based on membership status.

---

### 4.6 Confirmation Page — Verified AZCB Members

**Route:** `/conference/register/confirmation/` (with member context)

**Page Copy:**

> **Confirmation**
>
> Thank you for registering for the 2026 AZCB Conference and Annual Business Meeting! As a member of AZCB, you will receive links for all conference related meetings, including the AZCB Annual Business Meeting. If you do not receive these links by Thursday, April 10, and/or if you have questions about the conference, please [Contact us Here](https://azcb.org/contact-us/).

---

### 4.7 Confirmation Page — Non-Members

**Route:** `/conference/register/confirmation/` (with non-member context)

**Page Copy:**

> **Confirmation**
>
> Thank you for registering for the 2026 AZCB Conference. Our records indicate that you are not currently a member of the Arizona Council of the Blind, so you will receive links for conference-related meetings, but not for the AZCB Annual Business Meeting.
>
> If you would like to become a member of the AZCB, please visit the [Membership Page](https://azcb.org/membership/), fill in the required information, and provide the required dues, and we will happily add you to our growing organization. If you do so before the start of the convention, you will then be able to join us for our 2026 Annual Business Meeting.
>
> If you believe you are a member in good standing (meaning that you have registered and paid dues for 2026), and/or if you have other questions about the conference, please [Contact us Here](https://azcb.org/contact-us/).

---

## 5. Technical Requirements

### 5.1 Magic Link System

| Parameter | Specification |
|-----------|---------------|
| Token format | Cryptographically random, URL-safe string (min 32 bytes) |
| Expiration | Configurable; default **30 minutes** |
| Single-use | Token is invalidated after first successful use |
| Storage | Server-side token store with expiry (DB or cache) |
| Payload | Links to: first name, last name, email, membership verification result |

**Magic Link Email:**
- **Subject:** "AZCB Conference Registration — Verify Your Email"
- **Body:** Contains a link to `/conference/register/?token=<token>`. Use the same confirmation copy as the appropriate confirmation page (member or non-member) — per Wesley's instruction that email content matches the on-site messages.

### 5.2 Confirmation Emails

Two email templates, sent upon successful registration:

| Template | Recipient | Content |
|----------|-----------|---------|
| Member Confirmation | Verified AZCB members | Same copy as §4.6 |
| Non-Member Confirmation | Non-members | Same copy as §4.7 |

### 5.3 Data Storage

**Critical Requirement:** Conference registration data must be stored in a **separate dataset** from the AZCB membership records.

Implemented as two custom WordPress database tables created by the plugin on activation.

**Table: `{prefix}azcb_conf_registrations`**

| Column | Type | Notes |
|--------|------|-------|
| id | bigint, auto-increment | Primary key |
| first_name | varchar(100) | Required |
| last_name | varchar(100) | Required |
| email | varchar(200), UNIQUE | One registration per email |
| mobile_phone | varchar(30) | Optional |
| zip_code | varchar(20) | Required |
| is_member | tinyint(1) | 1 = verified AZCB member, 0 = non-member |
| is_lifetime | tinyint(1) | 1 = lifetime member |
| registered_at | datetime | UTC timestamp |
| confirmation_sent | tinyint(1) | Whether confirmation email was sent |

**Table: `{prefix}azcb_conf_tokens`**

| Column | Type | Notes |
|--------|------|-------|
| id | bigint, auto-increment | Primary key |
| token | varchar(64), UNIQUE | Cryptographically random hex string |
| email | varchar(200), INDEX | Recipient email |
| first_name | varchar(100) | From verification form |
| last_name | varchar(100) | From verification form |
| is_member | tinyint(1) | CSV lookup result |
| is_lifetime | tinyint(1) | CSV lifetime flag |
| member_data | longtext | JSON of all CSV columns for pre-fill |
| created_at | datetime | UTC |
| expires_at | datetime | created_at + 30 minutes |
| used | tinyint(1) | Consumed on successful registration |

**Membership Lookup (read-only):**
- Reads the existing CSV at `wp-content/uploads/2025/10/azcb_members.csv`
- Matches on First Name + Last Name + Email (case-insensitive)
- The system **never writes** to the membership dataset.

### 5.4 URL Structure Summary

| URL | Purpose | Method |
|-----|---------|--------|
| `/convention/` | 302 redirect to `/conference/` | GET |
| `/conference/` | Conference landing page | GET |
| `/conference/verify/` | Member verification form | GET, POST |
| `/conference/verify/sent/` | Magic link sent confirmation | GET |
| `/conference/register/` | Registration form | GET, POST |
| `/conference/register/confirmation/` | Post-registration confirmation | GET |

### 5.5 Implementation Architecture

**Approach:** Standalone WordPress plugin (`azcb-conference-registration`)

**Rationale:**
- Self-contained — all logic in one deployable unit
- No dependency on Gravity Forms (premium) for a simple 5-field form
- Full control over magic link flow, token management, rate limiting
- Custom DB tables for clean data separation
- Easy admin interface for registration management
- Uses the same membership CSV already in the Media Library

**Plugin Structure:**
```
azcb-conference-registration/
├── azcb-conference-registration.php   (entry point, hooks, constants)
├── includes/
│   ├── class-activator.php            (DB tables + page creation on activate)
│   ├── class-csv-lookup.php           (membership CSV fetch + match)
│   ├── class-magic-link.php           (token CRUD + rate limiting)
│   ├── class-registration.php         (shortcodes + form POST handling)
│   ├── class-email.php                (magic link + confirmation emails)
│   └── class-admin.php                (admin list table + CSV export)
├── templates/
│   ├── verify-form.php
│   ├── verify-sent.php
│   ├── register-form.php
│   ├── confirmation-member.php
│   └── confirmation-nonmember.php
└── assets/
    └── style.css
```

**Shortcodes (placed on WP pages by activator):**
| Shortcode | Page |
|-----------|------|
| `[azcb_conference_verify]` | `/conference/verify/` |
| `[azcb_conference_sent]` | `/conference/verify/sent/` |
| `[azcb_conference_register]` | `/conference/register/` |
| `[azcb_conference_confirmation]` | `/conference/register/confirmation/` |

**Security:**
- CSRF protection via WordPress nonces on all forms
- Output escaping (`esc_html`, `esc_attr`, `esc_url`) on all rendered content
- Prepared statements (`$wpdb->prepare()`) for all SQL
- Rate limiting: 5 magic link requests per email per hour (transient-based)
- Tokens: cryptographically random (32 bytes), single-use, 30-minute expiry
- Admin pages: `manage_options` capability check
- Honeypot field for spam protection

---

## 6. Accessibility Requirements

Given that AZCB serves the blind and visually impaired community, accessibility is **paramount**:

- WCAG 2.1 AA compliance at minimum (target AAA where feasible)
- All form fields must have associated `<label>` elements
- Error messages must be announced to screen readers (ARIA live regions)
- Focus management on page transitions (focus moves to main content/heading)
- All buttons and links must have clear, descriptive accessible names
- Color must not be the sole means of conveying information
- Keyboard navigability throughout the entire flow
- Tested with screen readers (NVDA, JAWS, VoiceOver)

---

## 7. Open Questions / Items to Confirm with Wesley

| # | Question | Status |
|---|----------|--------|
| 1 | Magic link expiration time — "xx minutes" in spec. **Proposed: 15 minutes.** | **RESOLVED** — **30 minutes**. |
| 2 | What is the non-member registration path? Separate external form, or the same `/conference/register/` page without pre-fill? | **RESOLVED** — Same `/conference/register/` page, no pre-fill. System must track member vs. non-member status (members = voting, get business meeting access; non-members = non-voting, conference only). |
| 3 | Does the "non-member" button on the verification page go to the same registration page or a different external URL? | **RESOLVED** — Same page: `/conference/register/` (no token, no pre-fill). |
| 4 | Should membership lookup match on all three fields (first, last, email) or just email? | **RESOLVED** — Existing Form 13 matches on **4 fields**: First Name + Last Name + Email + Zip (all case-insensitive, exact). Conference registration should follow the same pattern (or simplify to email-only + magic link). |
| 5 | What membership data fields are available for pre-filling phone and zip? | **RESOLVED** — CSV contains: First/Last/Middle Name, Title, Suffix, Salutation, Address 1/2, City, State, Zip, Country, Email, Home Phone, Mobile Phone, Gender, Ethnicity, Vision Status, BF Format, Preferred Mail Format. See `data-model.md`. |
| 6 | Is there an existing membership database/API, or do we need a CSV/import approach? | **RESOLVED** — Membership data is a **static CSV file** at `wp-content/uploads/2025/10/azcb_members.csv`. No database, no API. A PHP Code Snippet fetches and parses it on each form submission. See `plugins/code-snippets/azcb_membership_lookup_and_fill.php`. |
| 7 | Should there be rate limiting on the verification form to prevent abuse? **Recommended: Yes.** | **RESOLVED** — Yes. **5 requests per email per hour**. |
| 8 | Should registrants be able to re-register or edit their registration? | **RESOLVED** — No. **One registration per email**, no edits allowed. |
| 9 | Is there an admin view needed for viewing/exporting conference registrations? | **RESOLVED** — Yes. Admin interface with: registrant list, CSV export, member vs. non-member filtering. |
| 10 | What is the conference date? (Needed to determine registration close date and the April 10 link-distribution deadline.) | **RESOLVED** — Pre-Conference Game Night: Fri Apr 10, 5–7 PM MST. Conference: Sat Apr 11, 8 AM–12:30 PM MST. Business Meeting: Sat Apr 11, 1–2:30 PM MST. |
| 11 | What email service/provider is currently in use for AZCB? | **RESOLVED** — Standard WordPress `wp_mail` (default PHP mail via hosting). No third-party email service. |
| 12 | What is the current tech stack for azcb.org? (WordPress, static site, etc.) | **RESOLVED** — See Appendix A below. |

---

## 8. Out of Scope

- Modifications to the AZCB membership database
- Payment processing (this is a free virtual event)
- Conference content delivery / meeting link distribution (handled separately)
- Membership registration/renewal (existing flow at `/membership/`)

---

## 9. Timeline & Milestones

| Milestone | Description |
|-----------|-------------|
| **M1** | PRD finalized, open questions resolved |
| **M2** | Redirect implemented (`/convention/` → `/conference/`) |
| **M3** | Member verification flow (magic link) implemented |
| **M4** | Registration page with pre-fill implemented |
| **M5** | Confirmation pages & emails implemented |
| **M6** | Accessibility audit & testing |
| **M7** | Stakeholder review & UAT |
| **M8** | Go live |

**Hard deadline:** Registration must be live well before **April 10** (link distribution date referenced in member confirmation copy).

---

## Appendix A: Current Tech Stack (azcb.org)

Discovered via live site analysis (WP REST API, page scraping, code snippet review).

### Platform
- **WordPress** (wordpress.com hosted, Site ID 203501633)
- **Theme:** GeneratePress + GeneratePress Pro (premium)

### Plugins
| Plugin | Type | Purpose |
|--------|------|----------|
| **Gravity Forms** | Premium | All forms (Contact, Survey, Find Membership, Membership Renewal) |
| **Gravity Wiz / Perks** | Premium | Entry View field (`gpev-field`) on Form 13 |
| **Code Snippets** | Free | Custom PHP (membership lookup, form hooks) |
| **Jetpack + Jetpack Boost** | Freemium | Site management, performance |
| **My Calendar** | Free | Calendar display (`[my_calendar]` shortcode) |
| **WP Dark Mode** | Free | Dark mode toggle |
| **WP Accessibility Helper** | Free | Toolbar (text size, contrast, grayscale, underline links, readable font) |

### Payments
- **Stripe** integration in Form 12 (Membership Renewal) via Gravity Forms Stripe Add-On
- Conference registration is **free** — no payment processing needed

### Membership Data
- **No database** — membership data lives in a static CSV (`wp-content/uploads/2025/10/azcb_members.csv`)
- Lookup via PHP Code Snippet (`azcb_membership_lookup_and_fill`) on Form 13 submission
- Matches on: First Name + Last Name + Email + Zip (case-insensitive, exact)
- 20 member profile columns available for pre-fill (see `data-model.md`)
- Lifetime membership detected via `Membership category` or `ACB Life` CSV column

### Forms Inventory
| Form ID | Name | Location | Fields | Notes |
|---------|------|----------|--------|-------|
| 7 | Contact Us | `/contact-us/` | 5 | Name, email, phone, message |
| 8 | Survey | `/survey/` | 27 | Multi-page member profile survey |
| 12 | Membership Renewal | `/renew/` | 30 | Multi-step with Stripe payment, membership tier pricing |
| 13 | Find Membership | `/membership/`, `/findmembership/` | 29 | 4 visible inputs + 23 hidden fields populated by CSV lookup |

### Key Architectural Notes
1. The CSV-based lookup has **no caching** — every form submission fetches the full CSV
2. The CSV URL is hardcoded in the Code Snippet — changing the file requires updating both the upload and the code
3. No AJAX — Form 13 does a full page submit + server-side lookup + redirect
4. The `payload_json` field (Form 13, field 10) stores a JSON snapshot of all matched data for debugging
5. Gravity Perks Entry View (`gpev-field`) is used on the Email field (Form 13, field 31)
