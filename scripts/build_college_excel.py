# -*- coding: utf-8 -*-
"""Build modern Excel workbook for Odisha college contacts (Puri, Jagatsinghpur, Sundargarh)."""

from __future__ import annotations

import csv
import re
from pathlib import Path

from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.table import Table, TableStyleInfo
from openpyxl.formatting.rule import FormulaRule
from openpyxl.chart import PieChart, Reference
from openpyxl.chart.label import DataLabelList

ROOT = Path(r"c:\xampp\htdocs\public_html")
CSV_PATH = ROOT / "college_list_full_contacts.csv"
OUT_PATH = ROOT / "Odisha_College_Contacts_Puri_Jagatsinghpur_Sundargarh.xlsx"

# Per-college source mapping (college name substring -> source text)
SOURCE_RULES = [
    (r"S\.C\.S\.|SCS", "College website / SAMS TE (scscollege.nic.in)"),
    (r"Govt\. Women's College Puri|Women's College Puri", "Public college listing / DHE Odisha"),
    (r"Surajmal Saha", "College website (smspuri.in)"),
    (r"Nimapara Autonomous", "College website (nimaparacollege.ac.in) / Aided college contact list"),
    (r"Pipili College", "College website (piplicollege.co.in) / Aided college contact list"),
    (r"Alarnath|Balanga College|Gop College|Konark Bhagabati|Mangala Mahavidyalaya|Nigamananda", "Odisha Aided College Contact List / DHE Odisha"),
    (r"Utkalamani Gopabandhu Smruti|Astaranga Degree|Daya Vihar|Gadibrahma Degree|Gopinath Dev|Hariswardev|Indira Gandhi Women's|Kalyanpur Degree|Kanas Degree|Konark Women's|Mahatma Gandhi Degree|Mahatma Gandhi Memorial|Netrananda|Panchayat Degree College of Education|Rukmani Devi|Rantanpur|Sastri Smruti", "DHE Odisha – List of Non-Govt Aided / Degree Colleges"),
    (r"Government Polytechnic Puri|BPIT", "DTE&T Odisha (dtet.odisha.gov.in) / College website (gppuri.in)"),
    (r"Govt\. ITI Puri|ITI Puri", "SAMS Odisha ITI / ITI Puri website (itipuri.nic.in)"),
    (r"ITI Krushnaprasad|ITI Rua", "SAMS Odisha ITI contact list"),
    (r"IMT Pharmacy|College of Pharmaceutical|Ghanashyam Hemalata|Xavier Institute|Vision Residential", "Public institute listing / OJEE / SAMS PDIS"),
    (r"SVM Autonomous", "College website (svmcautonomous.ac.in) / Aided college contact list"),
    (r"Adikabi Sarala Das|Alaka Mahavidyalaya|Balikuda College|Kujang College|Paradeep College|Sri Jagannath|Sri Sri Jagannath|Sarala Mahavidyalaya|Kamaladevi|Baya Abadhut|Brundaban Chandra", "Odisha Aided College Contact List / College website"),
    (r"Ashram Patna|Baisimouza|Balikuda Women's|Gadibrahma Women's|Sidha Barang|Swami Arupananda", "DHE Odisha – List of Non-Govt Aided / Degree Colleges"),
    (r"Government Polytechnic Jagatsinghpur", "DTE&T Odisha / College website (gpjagatsinghpur.ac.in)"),
    (r"ITI Paradeep", "SAMS Odisha ITI contact list"),
    (r"NVIRON", "SAMS Odisha PDIS college contacts"),
    (r"Government Autonomous College Sundargarh", "College website (gacs.ac.in) / SAMS TE / Sambalpur University affiliated-college email list"),
    (r"Govt\. Women's College Sundargarh", "Sambalpur University affiliated-college email list"),
    (r"Municipal College", "College website (municipalcollegerkl.com) / Sambalpur University email list"),
    (r"Ispat Autonomous", "College website (ispatcollegerkl.com) / Sambalpur University email list"),
    (r"Gandhi Mahavidyalaya", "College website (gmvrkl.ac.in) / Sambalpur University email list"),
    (r"Bonaigarh College", "College website (bonaigarhcollege.in) / Sambalpur University email list"),
    (r"Dalmia College", "College website (dalmiacollegergp.ac.in) / Sambalpur University email list"),
    (r"Neela Saila|Priyadarshini|Sarbati Devi|Srama Shakti|Vesaj Patel|Vedvyas|SG Women's|Rourkela College|CAST|Hrusikesh Ray|Illa Memorial|Jasoda Bishnu|Jadupati|Kalyani Ray|Kinjirikela|Koira Degree|Lahunipara|Lephripara|Maharishi Dayananda|Manikeswari|Panchayat Samiti Degree College Bargaon|Anchalika Sahojoog|Venus Degree|Gayatri \+3|Nirmaan|C S Degree", "Sambalpur University affiliated-college email list / DHE Odisha"),
    (r"UGIE|Utkalmani Gopabandhu Institute of Engineering", "DTE&T Odisha / SCTE&VT / College website (ugierkl.ac.in)"),
    (r"SKDAV", "DTE&T Odisha / College website (skdavpolytech.ac.in) / SAMS PDIS"),
    (r"Government Polytechnic Darlipali", "DTE&T Odisha / College website (gpdarlipali.org.in)"),
    (r"Sundargarh Engineering School", "College website (sessng.org) / SCTE&VT"),
    (r"ITI Rourkela|ITI Koira|ITI Kutra", "SAMS Odisha ITI contact list"),
    (r"College of Teacher Education|CTE", "SAMS Odisha Teacher Education (TE) contact report"),
    (r"AWDI|Asian Workers", "SAMS Odisha PDIS college contacts"),
]


def split_emails(raw: str) -> list[str]:
    if not raw or not str(raw).strip():
        return []
    parts = re.split(r"\s*/\s*|\s*;\s*|\s*,\s*|\s*\|\s*", str(raw).strip())
    emails = []
    seen = set()
    for p in parts:
        e = p.strip()
        if not e or "@" not in e:
            continue
        key = e.lower()
        if key in seen:
            continue
        seen.add(key)
        emails.append(e)
    return emails


def split_phones(raw: str) -> str:
    if not raw or not str(raw).strip():
        return ""
    parts = re.split(r"\s*/\s*|\s*;\s*", str(raw).strip())
    cleaned = [p.strip() for p in parts if p.strip()]
    return "; ".join(cleaned)


def infer_source(college: str, website: str, email: str, phone: str) -> str:
    for pattern, source in SOURCE_RULES:
        if re.search(pattern, college, re.I):
            return source
    if website:
        return "College website / Public web listing"
    if email or phone:
        return "Public contact listing / DHE Odisha / SAMS Odisha"
    return "DHE Odisha college name list (contact fields not published publicly)"


def data_completeness(website: str, address: str, emails: list[str], phone: str) -> str:
    filled = sum(
        [
            bool(website),
            bool(address),
            bool(emails),
            bool(phone),
        ]
    )
    if filled == 4:
        return "Complete"
    if filled >= 2:
        return "Partial"
    return "Name only / Limited"


def load_rows() -> list[dict]:
    rows = []
    with CSV_PATH.open(encoding="utf-8-sig", newline="") as f:
        reader = csv.DictReader(f)
        for i, r in enumerate(reader, start=1):
            college = (r.get("College_Name") or "").strip()
            district = (r.get("District_Name") or "").strip()
            website = (r.get("College_Website") or "").strip()
            address = (r.get("College_Address") or "").strip()
            emails = split_emails(r.get("College_Contact_Email") or "")
            phone = split_phones(r.get("College_Contact_Phone") or "")
            source = infer_source(college, website, "; ".join(emails), phone)
            rows.append(
                {
                    "Sl_No": i,
                    "District": district,
                    "College_Name": college,
                    "Website": website,
                    "Address": address,
                    "Email_1": emails[0] if len(emails) > 0 else "",
                    "Email_2": emails[1] if len(emails) > 1 else "",
                    "Email_3": emails[2] if len(emails) > 2 else "",
                    "All_Emails": "; ".join(emails),
                    "Phone": phone,
                    "Source": source,
                    "Data_Status": data_completeness(website, address, emails, phone),
                    "Email_Count": len(emails),
                }
            )
    return rows


# Styles
NAVY = "0F2C59"
TEAL = "0D7377"
LIGHT = "F4F7FB"
WHITE = "FFFFFF"
AMBER = "FFF3CD"
GREEN = "D1E7DD"
RED = "F8D7DA"
GRAY = "6C757D"
HEADER_FILL = PatternFill("solid", fgColor=NAVY)
TEAL_FILL = PatternFill("solid", fgColor=TEAL)
ALT_FILL = PatternFill("solid", fgColor=LIGHT)
AMBER_FILL = PatternFill("solid", fgColor=AMBER)
GREEN_FILL = PatternFill("solid", fgColor=GREEN)
RED_FILL = PatternFill("solid", fgColor=RED)
WHITE_FILL = PatternFill("solid", fgColor=WHITE)
THIN = Border(
    left=Side(style="thin", color="D0D7DE"),
    right=Side(style="thin", color="D0D7DE"),
    top=Side(style="thin", color="D0D7DE"),
    bottom=Side(style="thin", color="D0D7DE"),
)
HEADER_FONT = Font(name="Calibri", bold=True, color=WHITE, size=11)
TITLE_FONT = Font(name="Calibri", bold=True, color=NAVY, size=18)
SUBTITLE_FONT = Font(name="Calibri", bold=True, color=TEAL, size=12)
BODY_FONT = Font(name="Calibri", size=10)
BOLD = Font(name="Calibri", bold=True, size=11, color=NAVY)


HEADERS = [
    "Sl No",
    "District",
    "College Name",
    "College Website",
    "College Address",
    "Email 1",
    "Email 2",
    "Email 3",
    "All Contact Emails",
    "Contact Phone",
    "Source of Record",
    "Data Status",
]


def style_header_row(ws, row_num: int, ncol: int):
    for col in range(1, ncol + 1):
        cell = ws.cell(row=row_num, column=col)
        cell.fill = HEADER_FILL
        cell.font = HEADER_FONT
        cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
        cell.border = THIN


def autosize(ws, widths: dict[int, int]):
    for col, width in widths.items():
        ws.column_dimensions[get_column_letter(col)].width = width


def write_data_sheet(ws, rows: list[dict], title: str):
    ws.sheet_view.showGridLines = False
    ws["A1"] = title
    ws["A1"].font = TITLE_FONT
    ws.merge_cells(start_row=1, start_column=1, end_row=1, end_column=12)
    ws["A2"] = "Puri · Jagatsinghpur · Sundargarh | Contacts compiled from SAMS Odisha, DHE Odisha, DTE&T, university lists & college websites"
    ws["A2"].font = Font(name="Calibri", italic=True, color=GRAY, size=9)
    ws.merge_cells(start_row=2, start_column=1, end_row=2, end_column=12)
    ws.row_dimensions[1].height = 28
    ws.row_dimensions[2].height = 18
    ws.row_dimensions[3].height = 32

    header_row = 3
    for c, h in enumerate(HEADERS, start=1):
        ws.cell(row=header_row, column=c, value=h)
    style_header_row(ws, header_row, len(HEADERS))

    for i, r in enumerate(rows, start=1):
        excel_row = header_row + i
        values = [
            i,
            r["District"],
            r["College_Name"],
            r["Website"],
            r["Address"],
            r["Email_1"],
            r["Email_2"],
            r["Email_3"],
            r["All_Emails"],
            r["Phone"],
            r["Source"],
            r["Data_Status"],
        ]
        for c, v in enumerate(values, start=1):
            cell = ws.cell(row=excel_row, column=c, value=v if v != "" else "N/A")
            cell.font = BODY_FONT
            cell.border = THIN
            cell.alignment = Alignment(vertical="center", wrap_text=True)
            if i % 2 == 0:
                cell.fill = ALT_FILL
            if c == 12:
                if r["Data_Status"] == "Complete":
                    cell.fill = GREEN_FILL
                elif r["Data_Status"] == "Partial":
                    cell.fill = AMBER_FILL
                else:
                    cell.fill = RED_FILL
                cell.alignment = Alignment(horizontal="center", vertical="center")
            if c == 4 and r["Website"]:
                cell.hyperlink = r["Website"]
                cell.font = Font(name="Calibri", size=10, color="0563C1", underline="single")
            if c in (6, 7, 8, 9) and isinstance(v, str) and "@" in v:
                cell.font = Font(name="Calibri", size=10, color=TEAL)

        ws.row_dimensions[excel_row].height = 34

    last_row = header_row + len(rows)
    if rows:
        table = Table(displayName=re.sub(r"[^A-Za-z0-9]", "", title)[:20] + "Tbl", ref=f"A{header_row}:L{last_row}")
        table.tableStyleInfo = TableStyleInfo(
            name="TableStyleMedium2",
            showFirstColumn=False,
            showLastColumn=False,
            showRowStripes=True,
            showColumnStripes=False,
        )
        # Avoid duplicate table names
        try:
            ws.add_table(table)
        except Exception:
            pass

    ws.auto_filter.ref = f"A{header_row}:L{last_row}"
    ws.freeze_panes = "A4"
    autosize(
        ws,
        {
            1: 8,
            2: 14,
            3: 42,
            4: 32,
            5: 48,
            6: 34,
            7: 34,
            8: 28,
            9: 48,
            10: 28,
            11: 48,
            12: 16,
        },
    )
    ws.print_title_rows = "1:3"


def write_dashboard(ws, rows: list[dict]):
    ws.sheet_view.showGridLines = False
    ws["A1"] = "Odisha College Contact Directory"
    ws["A1"].font = TITLE_FONT
    ws.merge_cells("A1:F1")
    ws["A2"] = "Modern contact workbook · Puri | Jagatsinghpur | Sundargarh"
    ws["A2"].font = SUBTITLE_FONT
    ws.merge_cells("A2:F2")
    ws.row_dimensions[1].height = 30

    # KPI cards
    total = len(rows)
    with_email = sum(1 for r in rows if r["Email_Count"] > 0)
    with_website = sum(1 for r in rows if r["Website"])
    with_phone = sum(1 for r in rows if r["Phone"])
    complete = sum(1 for r in rows if r["Data_Status"] == "Complete")
    multi_email = sum(1 for r in rows if r["Email_Count"] > 1)

    cards = [
        ("Total Colleges", total, NAVY),
        ("With Email", with_email, TEAL),
        ("With Website", with_website, "1B6CA8"),
        ("With Phone", with_phone, "2E8B57"),
        ("Complete Records", complete, "6A4C93"),
        ("Multiple Emails", multi_email, "C44900"),
    ]
    for idx, (label, value, color) in enumerate(cards):
        col = 1 + idx
        cell = ws.cell(row=4, column=col, value=label)
        cell.fill = PatternFill("solid", fgColor=color)
        cell.font = Font(name="Calibri", bold=True, color=WHITE, size=10)
        cell.alignment = Alignment(horizontal="center")
        vcell = ws.cell(row=5, column=col, value=value)
        vcell.fill = PatternFill("solid", fgColor=color)
        vcell.font = Font(name="Calibri", bold=True, color=WHITE, size=20)
        vcell.alignment = Alignment(horizontal="center", vertical="center")
        ws.column_dimensions[get_column_letter(col)].width = 16
    ws.row_dimensions[4].height = 22
    ws.row_dimensions[5].height = 36

    ws["A7"] = "District-wise Summary"
    ws["A7"].font = BOLD
    headers = ["District", "Colleges", "With Email", "With Website", "With Phone", "Complete", "Partial", "Limited"]
    for c, h in enumerate(headers, 1):
        cell = ws.cell(row=8, column=c, value=h)
        cell.fill = TEAL_FILL
        cell.font = HEADER_FONT
        cell.alignment = Alignment(horizontal="center")
        cell.border = THIN

    districts = ["Puri", "Jagatsinghpur", "Sundargarh"]
    for i, d in enumerate(districts):
        drows = [r for r in rows if r["District"] == d]
        vals = [
            d,
            len(drows),
            sum(1 for r in drows if r["Email_Count"] > 0),
            sum(1 for r in drows if r["Website"]),
            sum(1 for r in drows if r["Phone"]),
            sum(1 for r in drows if r["Data_Status"] == "Complete"),
            sum(1 for r in drows if r["Data_Status"] == "Partial"),
            sum(1 for r in drows if r["Data_Status"] == "Name only / Limited"),
        ]
        for c, v in enumerate(vals, 1):
            cell = ws.cell(row=9 + i, column=c, value=v)
            cell.border = THIN
            cell.font = BODY_FONT
            if i % 2:
                cell.fill = ALT_FILL
            if c == 1:
                cell.font = Font(name="Calibri", bold=True, size=10)

    # Chart data
    ws["A13"] = "Data Completeness (All Districts)"
    ws["A13"].font = BOLD
    ws["A14"] = "Status"
    ws["B14"] = "Count"
    ws["A14"].fill = HEADER_FILL
    ws["B14"].fill = HEADER_FILL
    ws["A14"].font = HEADER_FONT
    ws["B14"].font = HEADER_FONT
    status_counts = {
        "Complete": sum(1 for r in rows if r["Data_Status"] == "Complete"),
        "Partial": sum(1 for r in rows if r["Data_Status"] == "Partial"),
        "Name only / Limited": sum(1 for r in rows if r["Data_Status"] == "Name only / Limited"),
    }
    for i, (k, v) in enumerate(status_counts.items(), start=15):
        ws.cell(row=i, column=1, value=k).border = THIN
        ws.cell(row=i, column=2, value=v).border = THIN

    pie = PieChart()
    labels = Reference(ws, min_col=1, min_row=15, max_row=17)
    data = Reference(ws, min_col=2, min_row=14, max_row=17)
    pie.add_data(data, titles_from_data=True)
    pie.set_categories(labels)
    pie.title = "Record Completeness"
    pie.dataLabels = DataLabelList()
    pie.dataLabels.showPercent = True
    pie.dataLabels.showVal = True
    pie.width = 12
    pie.height = 8
    ws.add_chart(pie, "D13")

    ws["A20"] = "Quick Navigation"
    ws["A20"].font = BOLD
    ws["A21"] = "→ Use sheets: All Colleges | Puri | Jagatsinghpur | Sundargarh | Email Ready | Sources & Notes"
    ws["A21"].font = Font(name="Calibri", size=10, color=TEAL)
    ws.merge_cells("A21:F21")
    ws["A23"] = "Why some fields are blank?"
    ws["A23"].font = BOLD
    ws["A24"] = (
        "Many smaller degree colleges appear only by name in DHE Odisha lists. "
        "Official websites, emails and phones are often not published on a single public portal. "
        "SAMS Odisha contact pages usually show phone + email for ITI/Diploma/PDIS, not every degree college. "
        "Blank cells are marked 'N/A' and Data Status shows Complete / Partial / Limited."
    )
    ws["A24"].alignment = Alignment(wrap_text=True)
    ws.merge_cells("A24:F26")
    ws.row_dimensions[24].height = 60


def write_sources_notes(ws, rows: list[dict]):
    ws.sheet_view.showGridLines = False
    ws["A1"] = "Sources & Notes"
    ws["A1"].font = TITLE_FONT
    ws.merge_cells("A1:B1")

    ws["A3"] = "Why many fields were blank"
    ws["A3"].font = BOLD
    notes = [
        "1. DHE Odisha publishes college NAME + district/block, but not always website/email/phone in one place.",
        "2. SAMS Odisha has good contact emails for ITI, Diploma/Polytechnic, PDIS and some TE colleges — not every +3 degree college.",
        "3. Many rural/aided degree colleges do not maintain an active public website.",
        "4. Some older contact PDFs list phone only; email may be outdated or unpublished.",
        "5. Blank fields mean 'not found in public sources used for this workbook' — not that the college has no contact.",
        "6. Where multiple emails were found, ALL emails are kept in Email 1 / Email 2 / Email 3 and All Contact Emails.",
        "7. Phone numbers with '/' in source data were split and joined with '; ' for Excel readability.",
        "8. Always verify latest contact on the college website or SAMS portal before official mailing.",
    ]
    for i, line in enumerate(notes, start=4):
        ws.cell(row=i, column=1, value=line).font = BODY_FONT
        ws.merge_cells(start_row=i, start_column=1, end_row=i, end_column=2)

    ws["A13"] = "Official / Public Sources Used"
    ws["A13"].font = BOLD
    source_headers = ["#", "Source"]
    for c, h in enumerate(source_headers, 1):
        cell = ws.cell(row=14, column=c, value=h)
        cell.fill = HEADER_FILL
        cell.font = HEADER_FONT
        cell.border = THIN

    sources = [
        "SAMS Odisha – ITI Institute Contact Information (skill.samsodisha.gov.in)",
        "SAMS Odisha – PDIS College Contacts (skill.samsodisha.gov.in)",
        "SAMS Odisha – Teacher Education Institution Details (te.samsodisha.gov.in)",
        "DHE Odisha – List of Non-Government Aided Colleges (dhe.odisha.gov.in)",
        "DTE&T Odisha – Diploma / Polytechnic institute directory (dtet.odisha.gov.in)",
        "SCTE&VT Odisha – District exam/distribution notifications (principal names & phones)",
        "Sambalpur University – List of Affiliated Colleges Email PDF (suniv.ac.in)",
        "Odisha Aided College Contact List (public compilation used for many +3 college emails)",
        "Individual college official websites (e.g. gacs.ac.in, gppuri.in, gpjagatsinghpur.ac.in, municipalcollegerkl.com, etc.)",
        "ITI Puri official website (itipuri.nic.in)",
    ]
    for i, s in enumerate(sources, start=1):
        ws.cell(row=14 + i, column=1, value=i).border = THIN
        ws.cell(row=14 + i, column=2, value=s).border = THIN
        if i % 2 == 0:
            ws.cell(row=14 + i, column=1).fill = ALT_FILL
            ws.cell(row=14 + i, column=2).fill = ALT_FILL

    ws["A27"] = "Source usage count in this workbook"
    ws["A27"].font = BOLD
    ws["A28"] = "Source text (as written in Source of Record column)"
    ws["B28"] = "Colleges"
    ws["A28"].fill = TEAL_FILL
    ws["B28"].fill = TEAL_FILL
    ws["A28"].font = HEADER_FONT
    ws["B28"].font = HEADER_FONT

    from collections import Counter

    counts = Counter(r["Source"] for r in rows)
    row = 29
    for src, cnt in counts.most_common():
        ws.cell(row=row, column=1, value=src).border = THIN
        ws.cell(row=row, column=2, value=cnt).border = THIN
        row += 1

    ws["A" + str(row + 1)] = "Legend – Data Status"
    ws[f"A{row+1}"].font = BOLD
    ws[f"A{row+2}"] = "Complete"
    ws[f"A{row+2}"].fill = GREEN_FILL
    ws[f"B{row+2}"] = "Website + Address + Email + Phone all present"
    ws[f"A{row+3}"] = "Partial"
    ws[f"A{row+3}"].fill = AMBER_FILL
    ws[f"B{row+3}"] = "At least 2 of Website / Address / Email / Phone present"
    ws[f"A{row+4}"] = "Name only / Limited"
    ws[f"A{row+4}"].fill = RED_FILL
    ws[f"B{row+4}"] = "Mostly name (+ maybe address) from DHE list; email/website not found publicly"

    autosize(ws, {1: 90, 2: 14})


def main():
    rows = load_rows()
    wb = Workbook()

    # Dashboard
    ws_dash = wb.active
    ws_dash.title = "Dashboard"
    write_dashboard(ws_dash, rows)

    # All colleges
    ws_all = wb.create_sheet("All Colleges")
    write_data_sheet(ws_all, rows, "All Colleges – Contact Directory")

    # District sheets
    for district in ["Puri", "Jagatsinghpur", "Sundargarh"]:
        drows = [r for r in rows if r["District"] == district]
        ws = wb.create_sheet(district)
        write_data_sheet(ws, drows, f"{district} District – College Contacts")

    # Email ready (only rows with at least one email)
    email_rows = [r for r in rows if r["Email_Count"] > 0]
    ws_email = wb.create_sheet("Email Ready")
    write_data_sheet(ws_email, email_rows, "Email Ready – Colleges with Contact Email")

    # Sources & Notes
    ws_notes = wb.create_sheet("Sources & Notes")
    write_sources_notes(ws_notes, rows)

    wb.save(OUT_PATH)
    print(f"Saved: {OUT_PATH}")
    print(f"Total colleges: {len(rows)}")
    print(f"With email: {sum(1 for r in rows if r['Email_Count']>0)}")
    print(f"Multi-email: {sum(1 for r in rows if r['Email_Count']>1)}")


if __name__ == "__main__":
    main()
