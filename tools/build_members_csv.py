"""Convert the AZCB roster workbook into the members CSV used by the site.

The membership lookup Code Snippet (Gravity Forms form 13, "Find Membership")
and the conference registration plugin both read a CSV whose header names
must match exactly. This script maps the roster export columns to those
names and normalizes values so lookups and form prefills work.

Only active members are written: rows whose "Membership category" is
filled in (Yearly or AZCB life). Anyone else is treated as a new member
by the form.

Usage:
    python build_members_csv.py S:\\az.xlsx S:\\azcb_members.csv
"""

import csv
import re
import sys

import openpyxl

# Output column -> source column in the roster workbook.
COLUMNS = [
    ("Last Name", "LastName"),
    ("First Name", "FirstName"),
    ("Middle Name", "MiddleName"),
    ("Title", "Title"),
    ("Suffix", "Suffix"),
    ("Salutation", "Salutation"),
    ("Address 1", "Address1"),
    ("Address 2", "Address2"),
    ("City", "City"),
    ("State/Province", "State_Prov"),
    ("Zip", "Postal"),
    ("Country", "Country"),
    ("Email Address", "Email"),
    ("Home Phone", "HomePhone"),
    ("Mobile Phone", "MobilePhone"),
    ("Gender", "Gender"),
    ("Ethnicity", "Ethnicity"),
    ("Vision Status", "VisionStatus"),
    ("BF Format", "BFFormat"),
    ("Preferred Mail Format", "OtherCorr"),
    # The lookup reads "Membership category" for the AZCB life flag.
    ("Membership category", "Membership category"),
    ("ACB Life", "LifeMember"),
]

REQUIRED = ["First Name", "Last Name", "Email Address", "Zip"]

# Values that mean "no answer" in the roster.
BLANKS = {"no response", "?"}

# Choices that exist on form 12; anything else is left blank so the
# prefill does not select a wrong option.
PREFERRED_MAIL_CHOICES = {"None", "Large Print", "Braille"}


def clean(value):
    if value is None:
        return ""
    text = str(value).replace("\r", " ").replace("\n", " ").strip()
    return "" if text.lower() in BLANKS else text


def zip_code(value):
    if isinstance(value, int):
        return str(value).zfill(5)
    return clean(value)


def phone(value):
    text = clean(value)
    digits = re.sub(r"\D", "", text)
    if len(digits) == 11 and digits.startswith("1"):
        digits = digits[1:]
    if len(digits) == 10:
        return f"({digits[:3]}) {digits[3:6]}-{digits[6:]}"
    return text


def vision(value):
    text = clean(value)
    # Roster says "I identify as Blind"; form 12 choice is "Identify as Blind".
    return re.sub(r"^I identify as ", "Identify as ", text)


def preferred_mail(value):
    text = clean(value)
    return text if text in PREFERRED_MAIL_CHOICES else ""


CONVERTERS = {
    "Zip": zip_code,
    "Home Phone": phone,
    "Mobile Phone": phone,
    "Vision Status": vision,
    "Preferred Mail Format": preferred_mail,
}


def main(src, dest):
    sheet = openpyxl.load_workbook(src, data_only=True).worksheets[0]
    rows = list(sheet.iter_rows(values_only=True))
    header = [clean(h) for h in rows[0]]
    missing = [s for _, s in COLUMNS if s not in header]
    if missing:
        sys.exit(f"Error: workbook is missing columns: {', '.join(missing)}")
    index = {name: header.index(name) for name in header}

    out_rows = []
    skipped = 0
    for row in rows[1:]:
        if not any(c not in (None, "") for c in row):
            continue
        if not clean(row[index["Membership category"]]):
            skipped += 1
            continue
        out = {}
        for out_name, src_name in COLUMNS:
            value = row[index[src_name]]
            out[out_name] = CONVERTERS.get(out_name, clean)(value)
        out_rows.append(out)

    with open(dest, "w", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=[c for c, _ in COLUMNS], lineterminator="\n")
        writer.writeheader()
        writer.writerows(out_rows)

    matchable = sum(1 for r in out_rows if all(r[c] for c in REQUIRED))
    life = sum(1 for r in out_rows if "life" in r["Membership category"].lower())
    print(f"OK: wrote {len(out_rows)} active members to {dest}")
    print(f"Skipped (no membership category, treated as not current): {skipped}")
    print(f"Can be matched by the lookup (first, last, email, zip all present): {matchable}")
    print(f"Missing one or more match fields: {len(out_rows) - matchable}")
    print(f"AZCB life members: {life}")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
