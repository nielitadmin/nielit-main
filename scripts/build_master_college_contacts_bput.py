# -*- coding: utf-8 -*-
"""Build master college contacts workbook for all locations with BPUT affiliation."""

from __future__ import annotations

import re
import unicodedata
from collections import Counter
from pathlib import Path

import pandas as pd
from openpyxl import Workbook
from openpyxl.chart import PieChart, Reference
from openpyxl.chart.label import DataLabelList
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter

ROOT = Path(r"c:\xampp\htdocs\public_html")
ODISHA_XLSX = ROOT / "Odisha_College_Contacts_Puri_Jagatsinghpur_Sundargarh.xlsx"
SORO_XLSX = ROOT / "College_Contacts_Soro_Paralakhemundi_Bilaspur.xlsx"
BPUT_XLSX = ROOT / "College_BPUT_Affiliation_Check.xlsx"
CSV_FULL = ROOT / "college_list_full_contacts.csv"
CSV_PURI = ROOT / "college_list_puri_sundargarh_jagatsinghpur.csv"
OUT_PATH = ROOT / "Master_College_Contacts_All_Locations_BPUT.xlsx"

NAVY = "0F2C59"
TEAL = "0D7377"
BLUE = "1B6CA8"
GREEN_DARK = "2E8B57"
PURPLE = "6A4C93"
ORANGE = "C44900"
LINK_BLUE = "0563C1"
ALT_ROW = "F4F7FB"
STATUS_COMPLETE = "D1E7DD"
STATUS_PARTIAL = "FFF3CD"
STATUS_LIMITED = "F8D7DA"
BPUT_YES = "D1E7DD"
BPUT_NO = "E9ECEF"
BPUT_UNKNOWN = "FFE082"
WHITE = "FFFFFF"
GRAY = "6C757D"

THIN = Border(
    left=Side(style="thin", color="D0D7DE"),
    right=Side(style="thin", color="D0D7DE"),
    top=Side(style="thin", color="D0D7DE"),
    bottom=Side(style="thin", color="D0D7DE"),
)

HEADER_FILL = PatternFill("solid", fgColor=NAVY)
TEAL_FILL = PatternFill("solid", fgColor=TEAL)
ALT_FILL = PatternFill("solid", fgColor=ALT_ROW)
GREEN_FILL = PatternFill("solid", fgColor=STATUS_COMPLETE)
AMBER_FILL = PatternFill("solid", fgColor=STATUS_PARTIAL)
RED_FILL = PatternFill("solid", fgColor=STATUS_LIMITED)
YES_FILL = PatternFill("solid", fgColor=BPUT_YES)
NO_FILL = PatternFill("solid", fgColor=BPUT_NO)
UNKNOWN_FILL = PatternFill("solid", fgColor=BPUT_UNKNOWN)

TITLE_FONT = Font(name="Calibri", bold=True, color=NAVY, size=18)
SUBTITLE_FONT = Font(name="Calibri", bold=True, color=TEAL, size=12)
HEADER_FONT = Font(name="Calibri", bold=True, color=WHITE, size=11)
BODY_FONT = Font(name="Calibri", size=10)
BOLD = Font(name="Calibri", bold=True, size=11, color=NAVY)
LINK_FONT = Font(name="Calibri", size=10, color=LINK_BLUE, underline="single")
EMAIL_FONT = Font(name="Calibri", size=10, color=TEAL)

HEADERS = [
    "Sl No",
    "District / Location",
    "College Name",
    "College Website",
    "College Address",
    "Email 1",
    "Email 2",
    "Email 3",
    "All Contact Emails",
    "Contact Phone",
    "Is BPUT Affiliated?",
    "Likely Affiliation Body",
    "Source of Record",
    "Data Status",
]

LOCATION_ORDER = [
    "Puri District",
    "Jagatsinghpur District",
    "Sundargarh District",
    "Soro (Balasore Odisha)",
    "Paralakhemundi (Gajapati Odisha)",
    "Bilaspur (Chhattisgarh)",
]

LOCATION_LABELS = {
    "Puri": "Puri District",
    "Puri District": "Puri District",
    "Jagatsinghpur": "Jagatsinghpur District",
    "Jagatsinghpur District": "Jagatsinghpur District",
    "Sundargarh": "Sundargarh District",
    "Sundargarh District": "Sundargarh District",
    "Soro": "Soro (Balasore Odisha)",
    "Soro (Balasore Odisha)": "Soro (Balasore Odisha)",
    "Soro (Balasore, Odisha)": "Soro (Balasore Odisha)",
    "Paralakhemundi": "Paralakhemundi (Gajapati Odisha)",
    "Paralakhemundi (Gajapati Odisha)": "Paralakhemundi (Gajapati Odisha)",
    "Paralakhemundi (Gajapati, Odisha)": "Paralakhemundi (Gajapati Odisha)",
    "Bilaspur": "Bilaspur (Chhattisgarh)",
    "Bilaspur (Chhattisgarh)": "Bilaspur (Chhattisgarh)",
}

SHORT_LOC = {
    "Puri District": "Puri",
    "Jagatsinghpur District": "Jagatsinghpur",
    "Sundargarh District": "Sundargarh",
    "Soro (Balasore Odisha)": "Soro",
    "Paralakhemundi (Gajapati Odisha)": "Paralakhemundi",
    "Bilaspur (Chhattisgarh)": "Bilaspur",
}

SHEET_NAMES = {
    "Puri District": "Puri District",
    "Jagatsinghpur District": "Jagatsinghpur District",
    "Sundargarh District": "Sundargarh District",
    "Soro (Balasore Odisha)": "Soro (Balasore)",
    "Paralakhemundi (Gajapati Odisha)": "Paralakhemundi (Gajapati)",
    "Bilaspur (Chhattisgarh)": "Bilaspur (Chhattisgarh)",
}

SUBTITLE = (
    "Puri · Jagatsinghpur · Sundargarh · Soro (Balasore) · Paralakhemundi (Gajapati) · "
    "Bilaspur (CG) | Contacts + BPUT affiliation check"
)

COL_WIDTHS = {
    1: 8,
    2: 28,
    3: 44,
    4: 32,
    5: 48,
    6: 34,
    7: 30,
    8: 24,
    9: 48,
    10: 26,
    11: 16,
    12: 42,
    13: 48,
    14: 14,
}


def clean_text(v) -> str:
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return ""
    s = str(v).strip()
    if s.lower() in {"nan", "none", "n/a", "na", "-", "—", ""}:
        return ""
    return s


def display(v: str) -> str:
    return v if v else "N/A"


def normalize_name(name: str) -> str:
    s = unicodedata.normalize("NFKD", clean_text(name)).encode("ascii", "ignore").decode("ascii")
    s = s.lower()
    s = s.replace("&", " and ")
    s = re.sub(r"[^\w\s]", " ", s)
    s = re.sub(r"\s+", " ", s).strip()
    # common abbreviations / noise
    for token in (
        "autonomous",
        "degree",
        "college",
        "the",
        "of",
        "and",
        "at",
        "dist",
        "district",
        "odisha",
        "orissa",
        "chhattisgarh",
        "cg",
    ):
        s = re.sub(rf"\b{token}\b", " ", s)
    s = re.sub(r"\s+", " ", s).strip()
    return s


def normalize_status(status: str) -> str:
    s = clean_text(status)
    if not s:
        return "Limited"
    low = s.lower()
    if "complete" in low:
        return "Complete"
    if "partial" in low:
        return "Partial"
    return "Limited"


def normalize_bput(val: str) -> str:
    s = clean_text(val).title() if clean_text(val) else "Unknown"
    if s.lower() == "yes":
        return "Yes"
    if s.lower() == "no":
        return "No"
    return "Unknown"


def read_college_sheet(path: Path, sheet: str) -> pd.DataFrame:
    df = pd.read_excel(path, sheet_name=sheet, header=None)
    header_idx = None
    for i in range(min(6, len(df))):
        row = [str(c) for c in df.iloc[i].tolist()]
        if any("Sl No" in c for c in row):
            header_idx = i
            break
    if header_idx is None:
        raise ValueError(f"Header not found in {path.name} / {sheet}")
    headers = [clean_text(c) for c in df.iloc[header_idx].tolist()]
    data = df.iloc[header_idx + 1 :].copy()
    data.columns = headers
    data = data.dropna(how="all")
    name_col = next(c for c in data.columns if "College Name" in str(c))
    data = data[data[name_col].map(lambda x: bool(clean_text(x)))]
    return data


def location_from_raw(raw: str) -> str:
    key = clean_text(raw)
    if key in LOCATION_LABELS:
        return LOCATION_LABELS[key]
    # fuzzy contains
    low = key.lower()
    if "puri" in low:
        return "Puri District"
    if "jagatsinghpur" in low:
        return "Jagatsinghpur District"
    if "sundargarh" in low:
        return "Sundargarh District"
    if "soro" in low or "balasore" in low:
        return "Soro (Balasore Odisha)"
    if "paralakhemundi" in low or "gajapati" in low:
        return "Paralakhemundi (Gajapati Odisha)"
    if "bilaspur" in low:
        return "Bilaspur (Chhattisgarh)"
    raise ValueError(f"Unknown location: {raw!r}")


def row_from_source(r: pd.Series, source_file: str) -> dict:
    loc_col = "District / Location"
    if "District / City" in r.index:
        loc_col = "District / City"
    elif "District" in r.index:
        loc_col = "District"
    location = location_from_raw(r.get(loc_col, ""))
    website = clean_text(r.get("College Website"))
    address = clean_text(r.get("College Address"))
    e1 = clean_text(r.get("Email 1"))
    e2 = clean_text(r.get("Email 2"))
    e3 = clean_text(r.get("Email 3"))
    all_emails = clean_text(r.get("All Contact Emails"))
    if not all_emails:
        parts = [e for e in (e1, e2, e3) if e]
        all_emails = "; ".join(parts)
    phone = clean_text(r.get("Contact Phone"))
    source = clean_text(r.get("Source of Record")) or source_file
    status = normalize_status(r.get("Data Status"))
    name = clean_text(r.get("College Name"))
    return {
        "location": location,
        "name": name,
        "website": website,
        "address": address,
        "email1": e1,
        "email2": e2,
        "email3": e3,
        "all_emails": all_emails,
        "phone": phone,
        "source": source,
        "status": status,
        "source_file": source_file,
        "norm_name": normalize_name(name),
        "short_loc": SHORT_LOC[location],
    }


def load_contacts() -> list[dict]:
    rows: list[dict] = []
    d1 = read_college_sheet(ODISHA_XLSX, "All Colleges")
    for _, r in d1.iterrows():
        rows.append(row_from_source(r, ODISHA_XLSX.name))
    d2 = read_college_sheet(SORO_XLSX, "All Colleges")
    for _, r in d2.iterrows():
        rows.append(row_from_source(r, SORO_XLSX.name))

    # Deduplicate by location + normalized name (prefer richer record)
    best: dict[tuple[str, str], dict] = {}

    def richness(rec: dict) -> int:
        return sum(
            [
                bool(rec["website"]),
                bool(rec["address"]),
                bool(rec["all_emails"]),
                bool(rec["phone"]),
                rec["status"] == "Complete",
            ]
        )

    for rec in rows:
        key = (rec["location"], rec["norm_name"] or rec["name"].lower())
        if key not in best or richness(rec) > richness(best[key]):
            best[key] = rec
    out = list(best.values())
    out.sort(key=lambda r: (LOCATION_ORDER.index(r["location"]), r["name"].lower()))
    return out


def load_affiliation() -> dict[tuple[str, str], dict]:
    aff = pd.read_excel(BPUT_XLSX, sheet_name="Affiliation Check")
    mapping: dict[tuple[str, str], dict] = {}
    for _, r in aff.iterrows():
        short = clean_text(r.get("Location"))
        name = clean_text(r.get("College Name"))
        if not short or not name:
            continue
        location = location_from_raw(short)
        key_exact = (location, name.lower())
        key_norm = (location, normalize_name(name))
        payload = {
            "bput": normalize_bput(r.get("BPUT Affiliated?")),
            "body": clean_text(r.get("Likely Affiliation Body")) or "Unknown",
            "category": clean_text(r.get("Category")),
            "notes": clean_text(r.get("Notes")),
        }
        mapping[key_exact] = payload
        mapping[key_norm] = payload
    return mapping


def apply_affiliation(rows: list[dict], aff_map: dict[tuple[str, str], dict]) -> list[dict]:
    unmatched = []
    for rec in rows:
        # Hard rule: Bilaspur CG always No
        if rec["location"] == "Bilaspur (Chhattisgarh)":
            payload = aff_map.get((rec["location"], rec["name"].lower())) or aff_map.get(
                (rec["location"], rec["norm_name"])
            )
            rec["bput"] = "No"
            rec["body"] = (payload or {}).get("body") or "CSVTU / ABVV Bilaspur (Chhattisgarh – not BPUT)"
            continue

        payload = aff_map.get((rec["location"], rec["name"].lower()))
        if not payload:
            payload = aff_map.get((rec["location"], rec["norm_name"]))
        if not payload:
            # try short-loc keys from affiliation file style
            for alt_loc in (rec["short_loc"],):
                payload = aff_map.get((location_from_raw(alt_loc), rec["name"].lower())) or aff_map.get(
                    (location_from_raw(alt_loc), rec["norm_name"])
                )
                if payload:
                    break
        if payload:
            rec["bput"] = payload["bput"]
            rec["body"] = payload["body"]
        else:
            # Heuristic fallbacks matching verified rules
            name_l = rec["name"].lower()
            if any(x in name_l for x in ("polytechnic", "engineering school", "diploma")):
                rec["bput"] = "No"
                rec["body"] = "SCTE&VT (diploma/polytechnic)"
            elif re.search(r"\biti\b|\bitc\b|industrial training", name_l):
                rec["bput"] = "No"
                rec["body"] = "NCVT / SCTE&VT (ITI)"
            elif any(
                x in name_l
                for x in (
                    "mahavidyalaya",
                    "degree college",
                    "+3",
                    "women's college",
                    "womens college",
                    "autonomous college",
                )
            ):
                rec["bput"] = "No"
                rec["body"] = "State university (arts/science/commerce – not BPUT)"
            else:
                rec["bput"] = "Unknown"
                rec["body"] = "Unknown – verify against current BPUT affiliated list"
                unmatched.append(rec["name"])
    if unmatched:
        print(f"Affiliation unmatched ({len(unmatched)}): {unmatched[:10]}")
    return rows


def has_email(rec: dict) -> bool:
    return bool(rec["all_emails"]) and "@" in rec["all_emails"]


def style_header_row(ws, row_num: int, ncol: int):
    for col in range(1, ncol + 1):
        cell = ws.cell(row=row_num, column=col)
        cell.fill = HEADER_FILL
        cell.font = HEADER_FONT
        cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
        cell.border = THIN


def write_banner(ws, title: str, ncol: int = 14):
    ws.sheet_view.showGridLines = False
    ws["A1"] = title
    ws["A1"].font = TITLE_FONT
    ws.merge_cells(start_row=1, start_column=1, end_row=1, end_column=ncol)
    ws["A2"] = SUBTITLE
    ws["A2"].font = Font(name="Calibri", italic=True, color=GRAY, size=9)
    ws.merge_cells(start_row=2, start_column=1, end_row=2, end_column=ncol)
    # banner fill strip
    for c in range(1, ncol + 1):
        ws.cell(row=1, column=c).fill = PatternFill("solid", fgColor="E8EEF7")
    ws.row_dimensions[1].height = 30
    ws.row_dimensions[2].height = 18
    ws.row_dimensions[3].height = 34


def write_data_sheet(ws, rows: list[dict], title: str):
    write_banner(ws, title)
    header_row = 3
    for c, h in enumerate(HEADERS, start=1):
        ws.cell(row=header_row, column=c, value=h)
    style_header_row(ws, header_row, len(HEADERS))

    for i, r in enumerate(rows, start=1):
        excel_row = header_row + i
        values = [
            i,
            r["location"],
            r["name"],
            display(r["website"]),
            display(r["address"]),
            display(r["email1"]),
            display(r["email2"]),
            display(r["email3"]),
            display(r["all_emails"]),
            display(r["phone"]),
            r["bput"],
            display(r["body"]),
            display(r["source"]),
            r["status"],
        ]
        for c, v in enumerate(values, start=1):
            cell = ws.cell(row=excel_row, column=c, value=v)
            cell.font = BODY_FONT
            cell.border = THIN
            cell.alignment = Alignment(vertical="center", wrap_text=True)
            if i % 2 == 0:
                cell.fill = ALT_FILL

            # Website hyperlink
            if c == 4 and r["website"] and r["website"].lower().startswith(("http://", "https://")):
                cell.value = r["website"]
                cell.hyperlink = r["website"]
                cell.font = LINK_FONT

            # Email styling
            if c in (6, 7, 8, 9) and isinstance(v, str) and "@" in v:
                cell.font = EMAIL_FONT

            # BPUT column
            if c == 11:
                cell.alignment = Alignment(horizontal="center", vertical="center")
                cell.font = Font(name="Calibri", bold=True, size=10)
                if r["bput"] == "Yes":
                    cell.fill = YES_FILL
                    cell.font = Font(name="Calibri", bold=True, size=10, color="0F5132")
                elif r["bput"] == "No":
                    cell.fill = NO_FILL
                    cell.font = Font(name="Calibri", bold=True, size=10, color=GRAY)
                else:
                    cell.fill = UNKNOWN_FILL
                    cell.font = Font(name="Calibri", bold=True, size=10, color="7A5B00")

            # Data Status
            if c == 14:
                cell.alignment = Alignment(horizontal="center", vertical="center")
                if r["status"] == "Complete":
                    cell.fill = GREEN_FILL
                elif r["status"] == "Partial":
                    cell.fill = AMBER_FILL
                else:
                    cell.fill = RED_FILL

        ws.row_dimensions[excel_row].height = 34

    last_row = header_row + max(len(rows), 1)
    ws.auto_filter.ref = f"A{header_row}:{get_column_letter(len(HEADERS))}{last_row}"
    ws.freeze_panes = "A4"
    for col, width in COL_WIDTHS.items():
        ws.column_dimensions[get_column_letter(col)].width = width
    ws.print_title_rows = "1:3"


def write_dashboard(ws, rows: list[dict]):
    ws.sheet_view.showGridLines = False
    ws["A1"] = "Master College Contact Directory"
    ws["A1"].font = TITLE_FONT
    ws.merge_cells("A1:H1")
    ws["A2"] = "All locations + BPUT affiliation (Biju Patnaik University of Technology)"
    ws["A2"].font = SUBTITLE_FONT
    ws.merge_cells("A2:H2")
    for c in range(1, 9):
        ws.cell(row=1, column=c).fill = PatternFill("solid", fgColor="E8EEF7")
    ws.row_dimensions[1].height = 32

    total = len(rows)
    with_email = sum(1 for r in rows if has_email(r))
    with_website = sum(1 for r in rows if r["website"])
    with_phone = sum(1 for r in rows if r["phone"])
    complete = sum(1 for r in rows if r["status"] == "Complete")
    bput_yes = sum(1 for r in rows if r["bput"] == "Yes")
    bput_no = sum(1 for r in rows if r["bput"] == "No")
    bput_unknown = sum(1 for r in rows if r["bput"] == "Unknown")

    cards = [
        ("Total Colleges", total, NAVY),
        ("With Email", with_email, TEAL),
        ("With Website", with_website, BLUE),
        ("With Phone", with_phone, GREEN_DARK),
        ("Complete Records", complete, PURPLE),
        ("BPUT Yes", bput_yes, "198754"),
        ("BPUT No", bput_no, GRAY),
        ("BPUT Unknown", bput_unknown, "D4A017"),
    ]
    for idx, (label, value, color) in enumerate(cards):
        col = 1 + idx
        cell = ws.cell(row=4, column=col, value=label)
        cell.fill = PatternFill("solid", fgColor=color)
        cell.font = Font(name="Calibri", bold=True, color=WHITE, size=9)
        cell.alignment = Alignment(horizontal="center", wrap_text=True)
        vcell = ws.cell(row=5, column=col, value=value)
        vcell.fill = PatternFill("solid", fgColor=color)
        vcell.font = Font(name="Calibri", bold=True, color=WHITE, size=18)
        vcell.alignment = Alignment(horizontal="center", vertical="center")
        ws.column_dimensions[get_column_letter(col)].width = 14
    ws.row_dimensions[4].height = 28
    ws.row_dimensions[5].height = 36

    ws["A7"] = "Counts by Location"
    ws["A7"].font = BOLD
    loc_headers = [
        "District / Location",
        "Colleges",
        "With Email",
        "With Website",
        "Complete",
        "Partial",
        "Limited",
        "BPUT Yes",
        "BPUT No",
        "BPUT Unknown",
    ]
    for c, h in enumerate(loc_headers, 1):
        cell = ws.cell(row=8, column=c, value=h)
        cell.fill = TEAL_FILL
        cell.font = HEADER_FONT
        cell.alignment = Alignment(horizontal="center", wrap_text=True)
        cell.border = THIN
    ws.row_dimensions[8].height = 30

    for i, loc in enumerate(LOCATION_ORDER):
        drows = [r for r in rows if r["location"] == loc]
        vals = [
            loc,
            len(drows),
            sum(1 for r in drows if has_email(r)),
            sum(1 for r in drows if r["website"]),
            sum(1 for r in drows if r["status"] == "Complete"),
            sum(1 for r in drows if r["status"] == "Partial"),
            sum(1 for r in drows if r["status"] == "Limited"),
            sum(1 for r in drows if r["bput"] == "Yes"),
            sum(1 for r in drows if r["bput"] == "No"),
            sum(1 for r in drows if r["bput"] == "Unknown"),
        ]
        for c, v in enumerate(vals, 1):
            cell = ws.cell(row=9 + i, column=c, value=v)
            cell.border = THIN
            cell.font = BODY_FONT
            if i % 2:
                cell.fill = ALT_FILL
            if c == 1:
                cell.font = Font(name="Calibri", bold=True, size=10)
            if c == 8 and isinstance(v, int) and v > 0:
                cell.fill = YES_FILL
                cell.font = Font(name="Calibri", bold=True, size=10, color="0F5132")

    # Totals row
    total_row = 9 + len(LOCATION_ORDER)
    totals = [
        "TOTAL",
        total,
        with_email,
        with_website,
        complete,
        sum(1 for r in rows if r["status"] == "Partial"),
        sum(1 for r in rows if r["status"] == "Limited"),
        bput_yes,
        bput_no,
        bput_unknown,
    ]
    for c, v in enumerate(totals, 1):
        cell = ws.cell(row=total_row, column=c, value=v)
        cell.fill = HEADER_FILL
        cell.font = HEADER_FONT
        cell.border = THIN
        cell.alignment = Alignment(horizontal="center" if c > 1 else "left")

    ws["A17"] = "BPUT Affiliation Summary"
    ws["A17"].font = BOLD
    ws["A18"] = "Status"
    ws["B18"] = "Count"
    ws["A18"].fill = HEADER_FILL
    ws["B18"].fill = HEADER_FILL
    ws["A18"].font = HEADER_FONT
    ws["B18"].font = HEADER_FONT
    for i, (label, count, fill) in enumerate(
        [
            ("Yes", bput_yes, YES_FILL),
            ("No", bput_no, NO_FILL),
            ("Unknown", bput_unknown, UNKNOWN_FILL),
        ],
        start=19,
    ):
        a = ws.cell(row=i, column=1, value=label)
        b = ws.cell(row=i, column=2, value=count)
        a.border = THIN
        b.border = THIN
        a.fill = fill
        b.fill = fill
        a.font = Font(name="Calibri", bold=True, size=10)

    pie = PieChart()
    labels = Reference(ws, min_col=1, min_row=19, max_row=21)
    data = Reference(ws, min_col=2, min_row=18, max_row=21)
    pie.add_data(data, titles_from_data=True)
    pie.set_categories(labels)
    pie.title = "Is BPUT Affiliated?"
    pie.dataLabels = DataLabelList()
    pie.dataLabels.showPercent = True
    pie.dataLabels.showVal = True
    pie.width = 12
    pie.height = 8
    ws.add_chart(pie, "D17")

    ws["A24"] = "Confirmed BPUT Affiliated Colleges (Yes)"
    ws["A24"].font = BOLD
    yes_headers = ["#", "District / Location", "College Name", "Likely Affiliation Body"]
    for c, h in enumerate(yes_headers, 1):
        cell = ws.cell(row=25, column=c, value=h)
        cell.fill = PatternFill("solid", fgColor="198754")
        cell.font = HEADER_FONT
        cell.border = THIN
    yes_rows = [r for r in rows if r["bput"] == "Yes"]
    if not yes_rows:
        ws.cell(row=26, column=1, value="None found").border = THIN
    else:
        for i, r in enumerate(yes_rows, start=1):
            vals = [i, r["location"], r["name"], r["body"]]
            for c, v in enumerate(vals, 1):
                cell = ws.cell(row=25 + i, column=c, value=v)
                cell.border = THIN
                cell.font = BODY_FONT
                cell.fill = YES_FILL

    ws["A30"] = (
        "Note: BPUT = Biju Patnaik University of Technology (Odisha). "
        "Only engineering / pharmacy / MBA-MCA type colleges listed by BPUT are marked Yes. "
        "Bilaspur (Chhattisgarh) colleges are always No. Degree arts/science colleges, polytechnics "
        "(SCTE&VT) and ITIs (NCVT) are No."
    )
    ws["A30"].font = Font(name="Calibri", italic=True, color=GRAY, size=9)
    ws.merge_cells("A30:J30")
    ws.row_dimensions[30].height = 40

    for col, width in {1: 34, 2: 12, 3: 14, 4: 14, 5: 12, 6: 12, 7: 12, 8: 12, 9: 12, 10: 14}.items():
        ws.column_dimensions[get_column_letter(col)].width = width


def write_sources(ws):
    ws.sheet_view.showGridLines = False
    ws["A1"] = "Sources & Notes"
    ws["A1"].font = TITLE_FONT
    ws.merge_cells("A1:B1")
    ws["A2"] = "How to read this master workbook (simple English)"
    ws["A2"].font = SUBTITLE_FONT
    ws.merge_cells("A2:B2")
    for c in range(1, 3):
        ws.cell(row=1, column=c).fill = PatternFill("solid", fgColor="E8EEF7")

    notes = [
        ("What is BPUT?", ""),
        (
            "Meaning",
            "BPUT = Biju Patnaik University of Technology, Odisha. It affiliates technical colleges "
            "(B.Tech, M.Tech, B.Pharm, MBA, MCA, Architecture, etc.).",
        ),
        (
            "Column name",
            'The column is written as "Is BPUT Affiliated?" (correct spelling: BPUT, not BUPT).',
        ),
        ("", ""),
        ("When is BPUT = Yes?", ""),
        (
            "Only if confirmed",
            "Mark Yes only when the college is confirmed on the BPUT affiliated colleges list "
            "(cross-checked in College_BPUT_Affiliation_Check.xlsx).",
        ),
        (
            "Typical Yes colleges in this file",
            "Usually only a few Puri technical/pharmacy colleges, for example: "
            "GHITM Puri; IMT Pharmacy College Puri; College of Pharmaceutical Sciences Puri.",
        ),
        ("", ""),
        ("When is BPUT = No?", ""),
        (
            "Bilaspur (Chhattisgarh)",
            "Always No. These colleges are under Chhattisgarh bodies (ABVV / CSVTU etc.), not Odisha BPUT.",
        ),
        (
            "Degree arts / science / commerce",
            "No. These +3 / degree colleges affiliate to state universities "
            "(Utkal University, Sambalpur University, Fakir Mohan University, Berhampur University, etc.).",
        ),
        (
            "Polytechnic / diploma",
            "No. These come under SCTE&VT / DTE&T Odisha (or similar state technical boards).",
        ),
        (
            "ITI / ITC",
            "No. Industrial Training Institutes come under NCVT / SCTE&VT pathways, not BPUT.",
        ),
        ("", ""),
        ("When is BPUT = Unknown?", ""),
        (
            "Need verify",
            "Name looks technical or unclear, and it was not clearly matched on the BPUT list. "
            "Check the latest BPUT affiliated college PDF/Excel before using as Yes.",
        ),
        ("", ""),
        ("Source files used", ""),
        ("Contacts (Odisha 3 districts)", ODISHA_XLSX.name),
        ("Contacts (Soro / Paralakhemundi / Bilaspur)", SORO_XLSX.name),
        ("BPUT affiliation check", BPUT_XLSX.name),
        ("CSV fallback (if needed)", f"{CSV_FULL.name}; {CSV_PURI.name}"),
        ("", ""),
        ("Data Status meaning", ""),
        ("Complete", "Website + Address + Email + Phone all available."),
        ("Partial", "At least two contact fields available."),
        ("Limited", "Mostly name / place only; public email/phone not found."),
        ("", ""),
        ("Sheet guide", ""),
        ("Dashboard", "Totals by location and BPUT Yes/No/Unknown summary."),
        ("All Colleges", "Full master list for all 6 locations."),
        ("Location sheets", "One sheet for each location (easy filtering)."),
        ("BPUT Affiliated Only", "Only rows where Is BPUT Affiliated? = Yes."),
        ("Email Ready", "Colleges that have at least one contact email."),
        ("Sources & Notes", "This explanation page."),
        ("", ""),
        ("Important reminder (Odia-friendly English)", ""),
        (
            "Simple rule",
            "BPUT = Odisha technical university. Most local +3 colleges, polytechnics, ITIs, "
            "and all Bilaspur (CG) colleges are NOT BPUT. Only confirmed BPUT-listed colleges = Yes.",
        ),
    ]

    ws["A4"] = "Topic"
    ws["B4"] = "Explanation"
    style_header_row(ws, 4, 2)
    for i, (topic, text) in enumerate(notes, start=5):
        a = ws.cell(row=i, column=1, value=topic)
        b = ws.cell(row=i, column=2, value=text)
        a.font = Font(name="Calibri", bold=True, size=10, color=NAVY)
        b.font = BODY_FONT
        a.alignment = Alignment(vertical="top", wrap_text=True)
        b.alignment = Alignment(vertical="top", wrap_text=True)
        a.border = THIN
        b.border = THIN
        if topic and not text:
            a.fill = TEAL_FILL
            a.font = HEADER_FONT
            b.fill = TEAL_FILL
        elif i % 2 == 0:
            a.fill = ALT_FILL
            b.fill = ALT_FILL
        ws.row_dimensions[i].height = 28 if text else 20

    ws.column_dimensions["A"].width = 34
    ws.column_dimensions["B"].width = 100
    ws.freeze_panes = "A5"


def build():
    if not ODISHA_XLSX.exists() or not SORO_XLSX.exists() or not BPUT_XLSX.exists():
        missing = [p.name for p in (ODISHA_XLSX, SORO_XLSX, BPUT_XLSX) if not p.exists()]
        raise FileNotFoundError(f"Missing source files: {missing}")

    rows = load_contacts()
    aff_map = load_affiliation()
    rows = apply_affiliation(rows, aff_map)

    # Safety: Bilaspur always No; confirmed Yes only for known BPUT names if somehow mismatched
    for r in rows:
        if r["location"] == "Bilaspur (Chhattisgarh)":
            r["bput"] = "No"

    wb = Workbook()

    # 1 Dashboard
    ws_dash = wb.active
    ws_dash.title = "Dashboard"
    write_dashboard(ws_dash, rows)

    # 2 All Colleges
    ws_all = wb.create_sheet("All Colleges")
    write_data_sheet(ws_all, rows, "All Colleges – Master Contact Directory")

    # 3–8 One sheet per location
    for loc in LOCATION_ORDER:
        loc_rows = [r for r in rows if r["location"] == loc]
        ws = wb.create_sheet(SHEET_NAMES[loc])
        write_data_sheet(ws, loc_rows, f"{loc} – College Contacts")

    # 9 BPUT Affiliated Only
    yes_rows = [r for r in rows if r["bput"] == "Yes"]
    ws_yes = wb.create_sheet("BPUT Affiliated Only")
    write_data_sheet(ws_yes, yes_rows, "BPUT Affiliated Only (Is BPUT Affiliated? = Yes)")

    # 10 Email Ready
    email_rows = [r for r in rows if has_email(r)]
    ws_email = wb.create_sheet("Email Ready")
    write_data_sheet(ws_email, email_rows, "Email Ready – Colleges with Contact Email")

    # 11 Sources & Notes
    ws_notes = wb.create_sheet("Sources & Notes")
    write_sources(ws_notes)

    wb.save(OUT_PATH)

    counts = Counter(r["location"] for r in rows)
    bput_counts = Counter(r["bput"] for r in rows)
    print("OUT", OUT_PATH)
    print("TOTAL", len(rows))
    for loc in LOCATION_ORDER:
        print(f"  {loc}: {counts.get(loc, 0)}")
    print("BPUT", dict(bput_counts))
    print("COLUMN", "Is BPUT Affiliated?")
    print("YES_NAMES", [r["name"] for r in yes_rows])
    return {
        "path": str(OUT_PATH),
        "total": len(rows),
        "counts": dict(counts),
        "bput_yes": bput_counts.get("Yes", 0),
        "bput_no": bput_counts.get("No", 0),
        "bput_unknown": bput_counts.get("Unknown", 0),
    }


if __name__ == "__main__":
    build()
