# -*- coding: utf-8 -*-
"""Search students by WhatsApp name list and export Excel (read-only SELECT)."""

from __future__ import annotations

import re
from pathlib import Path

import pymysql
from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.table import Table, TableStyleInfo

OUT_PATH = Path(r"c:\xampp\htdocs\public_html\Student_Records_From_WhatsApp_List.xlsx")

QUERY_NAMES = [
    "SUMALAYA NAYAK",
    "SWAPNARANI MARNDI",
    "SAUDAMINI NAIK",
    "MANOJ KUMAR MAJHI",
    "SIBANI RAITA",
    "SUCHITRA BADRA",
    "MITHUN BADRA",
    "LOKANATH MAJHI",
    "BINA PUJARI",
    "KASISH PATRA",
    "AMISHA NAYAK",
    "TOFAN NAIK",
    "DEEPTIMAYEE NAIK",
    "SIPRA MALLIK",
    "RAJESH KUMAR NAIK",
    "MUSKAN PRADHAN",
    "MUKESH KUMAR ADHIKARI",
    "CHANDRAMA PATRA",
    "SUBHASHREE MAJHI",
    "SAMBHUNATH NAIK",
    "SUNITA NAYAK",
    "TRUPTI KARAKARIA",
    "SATYA RANJAN MAJHI",
    "BIJAYA LAXMI TARAI",
    "SEBATI BASKEY",
    "SURUJ SINGO TUDU",
    "BANITA PUJARI",
    "SABITA MUDULI",
    "JAGNYASENI DIGAL",
    "MADHUSMITA SUNDHI",
]

HIGHLIGHTED = {
    "SUMALAYA NAYAK",
    "MANOJ KUMAR MAJHI",
    "SIBANI RAITA",
    "LOKANATH MAJHI",
    "KASISH PATRA",
    "AMISHA NAYAK",
    "TOFAN NAIK",
    "DEEPTIMAYEE NAIK",
    "SIPRA MALLIK",
    "MUKESH KUMAR ADHIKARI",
    "CHANDRAMA PATRA",
    "SUBHASHREE MAJHI",
    "SAMBHUNATH NAIK",
    "SATYA RANJAN MAJHI",
    "BIJAYA LAXMI TARAI",
    "JAGNYASENI DIGAL",
    "MADHUSMITA SUNDHI",
}

TOKEN_VARIANTS = {
    "NAIK": {"NAIK", "NAYAK"},
    "NAYAK": {"NAIK", "NAYAK"},
    "KARAKARIA": {"KARAKARIA", "KARAKARIYA", "KARAKARI"},
    "MALLIK": {"MALLIK", "MALLICK", "MALIK"},
    "MARNDI": {"MARNDI", "MARANDI"},
    "BASKEY": {"BASKEY", "BASKI", "BASKY"},
    "SUNDHI": {"SUNDHI", "SUNDHY", "SANDHI"},
    "TARAI": {"TARAI", "TARAY", "TORAI"},
    "DIGAL": {"DIGAL", "DIGEL"},
    "ADHIKARI": {"ADHIKARI", "ADHIKARY"},
    "MAJHI": {"MAJHI", "MAJHEE", "MAJI"},
    "PATRA": {"PATRA", "PATRO"},
    "RAITA": {"RAITA", "RAYTA"},
    "PUJARI": {"PUJARI", "PUJARY"},
    "BADRA": {"BADRA"},
    "MUDULI": {"MUDULI", "MUDULY"},
    "LAXMI": {"LAXMI", "LAKSHMI"},
    "BIJAYA": {"BIJAYA", "BIJAY", "VIJAYA", "VIJAY"},
    "DEEPTIMAYEE": {"DEEPTIMAYEE", "DEEPTIMAYE", "DIPTIMAYEE", "DIPTIMAYE"},
    "JAGNYASENI": {"JAGNYASENI", "JAGNYASINI", "YAGNYASENI"},
    "KASISH": {"KASISH", "KASHISH", "KASIS"},
    "TOFAN": {"TOFAN", "TOPHAN", "TOOFAN"},
    "SIPRA": {"SIPRA", "SHIPRA"},
    "SIBANI": {"SIBANI", "SHIBANI", "SIBBANI"},
    "CHANDRAMA": {"CHANDRAMA", "CHANDRIMA"},
    "SUBHASHREE": {"SUBHASHREE", "SUBHASREE"},
    "SAMBHUNATH": {"SAMBHUNATH", "SHAMBHUNATH"},
    "SAUDAMINI": {"SAUDAMINI", "SAUDAMANI"},
    "SUMALAYA": {"SUMALAYA", "SUMALYA"},
}


def norm_spaces(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "").strip().upper())


def tokens(s: str) -> list[str]:
    return [t for t in re.split(r"[^A-Z0-9]+", norm_spaces(s)) if t]


def expand(tok: str) -> set[str]:
    return set(TOKEN_VARIANTS.get(tok, {tok})) | {tok}


def token_eq(a: str, b: str) -> bool:
    return bool(expand(a) & expand(b))


def edit_dist(a: str, b: str) -> int:
    if a == b:
        return 0
    if abs(len(a) - len(b)) > 2:
        return 99
    prev = list(range(len(b) + 1))
    for i, ca in enumerate(a, 1):
        cur = [i]
        for j, cb in enumerate(b, 1):
            cur.append(min(cur[j - 1] + 1, prev[j] + 1, prev[j - 1] + (ca != cb)))
        prev = cur
    return prev[-1]


def soft_first_eq(q: str, d: str) -> bool:
    """First-name equality with limited spelling tolerance (not loose prefix)."""
    if token_eq(q, d):
        return True
    if len(q) >= 5 and len(d) >= 5 and edit_dist(q, d) <= 1:
        return True
    return False


MIDDLE_SKIP = {"KUMAR", "KU", "RANJAN", "SINGO", "LAXMI", "LAKSHMI"}


def classify_match(query: str, db_name: str) -> tuple[int, str]:
    """Strict-ish match: require first+last (with variants). Returns (score, type) or (0,'')."""
    qt = tokens(query)
    dt = tokens(db_name)
    if not qt or not dt:
        return 0, ""

    qn = norm_spaces(query)
    dn = norm_spaces(db_name)
    if qn == dn:
        return 100, "exact"

    # All query tokens (non-middle) present with variants, same count±1
    essential = [t for t in qt if t not in MIDDLE_SKIP or t == qt[-1] or t == qt[0]]
    # Always keep first and last
    essential = [qt[0]] + [t for t in qt[1:-1] if t not in {"KUMAR", "KU"}] + [qt[-1]]
    # dedupe preserve order
    seen = set()
    essential2 = []
    for t in essential:
        if t not in seen:
            seen.add(t)
            essential2.append(t)
    essential = essential2

    used = [False] * len(dt)
    all_ok = True
    for qtok in essential:
        found = False
        for i, dtok in enumerate(dt):
            if used[i]:
                continue
            if token_eq(qtok, dtok) or (qtok == essential[0] and soft_first_eq(qtok, dtok)):
                used[i] = True
                found = True
                break
        if not found:
            all_ok = False
            break
    if all_ok and len(dt) <= len(essential) + 1:
        if len(dt) == len(essential):
            return 90, "token_exact_variants"
        return 85, "token_all_present"

    # first + last (order-free)
    q_first, q_last = qt[0], qt[-1]
    first_hit = any(soft_first_eq(q_first, d) for d in dt)
    last_hit = any(token_eq(q_last, d) for d in dt)
    if first_hit and last_hit:
        # middle tokens if present (except KUMAR)
        mids = [t for t in qt[1:-1] if t not in {"KUMAR", "KU"}]
        if not mids:
            return 75, "first_last"
        mid_ok = all(any(token_eq(m, d) or soft_first_eq(m, d) for d in dt) for m in mids)
        if mid_ok:
            return 80, "first_last_mids"
        # Allow RAJESH KUMAR NAIK -> Rajesh Nayak (middle KUMAR optional already stripped)
        # If mids remain unmatched, still accept first+last for 2-token identity names
        if len(qt) <= 3 and {"KUMAR", "KU"} >= set(qt[1:-1]):
            return 70, "first_last"
        # SATYA RANJAN MAJHI needs RANJAN or ignore? Require last+first only as weaker
        return 55, "first_last_mid_missing"

    return 0, ""


def near_miss_reason(query: str, db_name: str) -> str | None:
    """Informational near-miss: distinctive first OR last overlap but not a real match."""
    qt = tokens(query)
    dt = tokens(db_name)
    if not qt or not dt:
        return None
    q_first, q_last = qt[0], qt[-1]
    first_hit = any(soft_first_eq(q_first, d) for d in dt)
    last_hit = any(token_eq(q_last, d) for d in dt)
    # also soft: first starts same 5 chars and lengths close — already in soft_first_eq via edit
    if first_hit and not last_hit:
        return f"Same/similar first name '{q_first}', different surname (wanted {q_last})"
    if last_hit and not first_hit:
        # only for uncommon surnames or if query first is long
        common_last = {"NAIK", "NAYAK", "PATRA", "PRADHAN", "SAHOO", "SAHU", "DAS", "ROUT"}
        if q_last not in common_last or len(q_first) >= 6:
            return f"Same/similar surname '{q_last}', different first name (wanted {q_first})"
    # Bijayalaxmi concatenated
    dn = norm_spaces(db_name).replace(" ", "")
    if q_first.replace(" ", "") in dn and not last_hit and len(q_first) >= 5:
        return f"First name appears inside DB name, surname mismatch (wanted {q_last})"
    return None


EXPORT_COLS = [
    "Highlighted_In_List",
    "Query_Name",
    "Match_Type",
    "Match_Score",
    "Ambiguous_Duplicate",
    "DB_id",
    "student_id",
    "nielit_registration_no",
    "DB_name",
    "email",
    "mobile",
    "course",
    "course_id",
    "city",
    "state",
    "status",
    "gender",
    "category",
    "dob",
    "father_name",
    "mother_name",
    "college_name",
    "training_center",
    "batch_id",
    "registration_date",
    "created_at",
]


def style_header(ws, ncols: int):
    fill = PatternFill("solid", fgColor="1F4E79")
    font = Font(bold=True, color="FFFFFF")
    for c in range(1, ncols + 1):
        cell = ws.cell(1, c)
        cell.fill = fill
        cell.font = font
        cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
    ws.row_dimensions[1].height = 28
    ws.freeze_panes = "A2"


def autosize(ws, max_width=42):
    for col in ws.columns:
        letter = get_column_letter(col[0].column)
        length = 0
        for cell in col[:100]:
            v = "" if cell.value is None else str(cell.value)
            length = max(length, len(v))
        ws.column_dimensions[letter].width = min(max(10, length + 2), max_width)


def row_from_student(q, score, mtype, ambiguous, s):
    return {
        "Highlighted_In_List": "Yes" if q in HIGHLIGHTED else "No",
        "Query_Name": q,
        "Match_Type": mtype,
        "Match_Score": score,
        "Ambiguous_Duplicate": ambiguous,
        "DB_id": s["id"],
        "student_id": s["student_id"],
        "nielit_registration_no": s["nielit_registration_no"],
        "DB_name": s["name"],
        "email": s["email"],
        "mobile": s["mobile"],
        "course": s["course"],
        "course_id": s["course_id"],
        "city": s["city"],
        "state": s["state"],
        "status": s["status"],
        "gender": s["gender"],
        "category": s["category"],
        "dob": str(s["dob"]) if s["dob"] else "",
        "father_name": s["father_name"],
        "mother_name": s["mother_name"],
        "college_name": s["college_name"],
        "training_center": s["training_center"],
        "batch_id": s["batch_id"],
        "registration_date": str(s["registration_date"]) if s["registration_date"] else "",
        "created_at": str(s["created_at"]) if s["created_at"] else "",
    }


def main():
    conn = pymysql.connect(
        host="localhost",
        user="root",
        password="",
        database="nielit_bhubaneswar",
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )
    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT id, student_id, nielit_registration_no, name, email, mobile,
                       course, course_id, city, state, status, gender, category, dob,
                       father_name, mother_name, college_name, training_center,
                       batch_id, registration_date, created_at
                FROM students
                """
            )
            students = list(cur.fetchall())
    finally:
        conn.close()

    found_rows = []
    not_found = []
    near_rows = []
    ambiguous_notes = []

    MIN_ACCEPT = 70  # first_last and above; exclude mid_missing (55)

    for q in QUERY_NAMES:
        scored = []
        for s in students:
            score, mtype = classify_match(q, s.get("name") or "")
            if score >= MIN_ACCEPT:
                scored.append((score, mtype, s))
        scored.sort(key=lambda x: (-x[0], x[2]["id"]))

        if scored:
            top = scored[0][0]
            keep = [x for x in scored if x[0] >= top - 10]
            ambiguous = "Yes" if len(keep) > 1 else "No"
            if ambiguous == "Yes":
                names = ", ".join(
                    f"{x[2]['name'].strip()} (id={x[2]['id']}, {x[1]}, score={x[0]})" for x in keep
                )
                ambiguous_notes.append(f"{q}: {names}")
            for score, mtype, s in keep:
                found_rows.append(row_from_student(q, score, mtype, ambiguous, s))
        else:
            not_found.append(q)
            # collect near misses (cap 5 per query)
            nm = []
            for s in students:
                reason = near_miss_reason(q, s.get("name") or "")
                if reason:
                    nm.append((s, reason))
            # prefer longer first-name overlaps
            nm = nm[:8]
            for s, reason in nm[:5]:
                near_rows.append(
                    {
                        "Highlighted_In_List": "Yes" if q in HIGHLIGHTED else "No",
                        "Query_Name": q,
                        "Near_Miss_Reason": reason,
                        "DB_id": s["id"],
                        "student_id": s["student_id"],
                        "DB_name": s["name"],
                        "email": s["email"],
                        "mobile": s["mobile"],
                        "course": s["course"],
                        "city": s["city"],
                        "status": s["status"],
                    }
                )

    found_queries = sorted({r["Query_Name"] for r in found_rows})
    highlighted_found = [q for q in found_queries if q in HIGHLIGHTED]
    highlighted_missing = [q for q in QUERY_NAMES if q in HIGHLIGHTED and q not in found_queries]

    yellow = PatternFill("solid", fgColor="FFF2CC")
    wb = Workbook()

    # Found Matches
    ws = wb.active
    ws.title = "Found Matches"
    for i, col in enumerate(EXPORT_COLS, 1):
        ws.cell(1, i, col)
    for r_i, row in enumerate(found_rows, 2):
        for c_i, col in enumerate(EXPORT_COLS, 1):
            cell = ws.cell(r_i, c_i, row.get(col))
            if row.get("Highlighted_In_List") == "Yes":
                cell.fill = yellow
    style_header(ws, len(EXPORT_COLS))
    if found_rows:
        end = f"A1:{get_column_letter(len(EXPORT_COLS))}{len(found_rows)+1}"
        tab = Table(displayName="FoundMatches", ref=end)
        tab.tableStyleInfo = TableStyleInfo(name="TableStyleMedium2", showRowStripes=True)
        ws.add_table(tab)
        ws.auto_filter.ref = end
    autosize(ws)

    # Not Found
    ws2 = wb.create_sheet("Not Found in DB")
    nf_cols = ["Highlighted_In_List", "Query_Name", "Notes"]
    for i, col in enumerate(nf_cols, 1):
        ws2.cell(1, i, col)
    for r_i, q in enumerate(not_found, 2):
        has_near = any(n["Query_Name"] == q for n in near_rows)
        note = (
            "No first+last match in students.name (see Possible Near Matches sheet)"
            if has_near
            else "No match in students.name with flexible first+last matching"
        )
        ws2.cell(r_i, 1, "Yes" if q in HIGHLIGHTED else "No")
        ws2.cell(r_i, 2, q)
        ws2.cell(r_i, 3, note)
        if q in HIGHLIGHTED:
            for c in range(1, 4):
                ws2.cell(r_i, c).fill = yellow
    style_header(ws2, 3)
    if not_found:
        tab2 = Table(displayName="NotFound", ref=f"A1:C{len(not_found)+1}")
        tab2.tableStyleInfo = TableStyleInfo(name="TableStyleMedium9", showRowStripes=True)
        ws2.add_table(tab2)
    autosize(ws2)

    # Possible Near Matches
    wsN = wb.create_sheet("Possible Near Matches")
    near_cols = [
        "Highlighted_In_List",
        "Query_Name",
        "Near_Miss_Reason",
        "DB_id",
        "student_id",
        "DB_name",
        "email",
        "mobile",
        "course",
        "city",
        "status",
    ]
    for i, col in enumerate(near_cols, 1):
        wsN.cell(1, i, col)
    for r_i, row in enumerate(near_rows, 2):
        for c_i, col in enumerate(near_cols, 1):
            cell = wsN.cell(r_i, c_i, row.get(col))
            if row.get("Highlighted_In_List") == "Yes":
                cell.fill = yellow
    style_header(wsN, len(near_cols))
    if near_rows:
        endn = f"A1:{get_column_letter(len(near_cols))}{len(near_rows)+1}"
        tabn = Table(displayName="NearMisses", ref=endn)
        tabn.tableStyleInfo = TableStyleInfo(name="TableStyleMedium4", showRowStripes=True)
        wsN.add_table(tabn)
    autosize(wsN)

    # Summary
    ws3 = wb.create_sheet("Summary")
    summary_lines = [
        ("Total names searched", len(QUERY_NAMES)),
        ("Names found (at least one confident match)", len(found_queries)),
        ("Names not found", len(not_found)),
        ("Total DB row matches (incl. duplicate people)", len(found_rows)),
        ("Yellow-highlighted in WhatsApp list", len(HIGHLIGHTED)),
        ("Highlighted found", len(highlighted_found)),
        ("Highlighted not found", len(highlighted_missing)),
        ("Near-miss rows (informational only)", len(near_rows)),
        ("Students table rows scanned", len(students)),
        ("Database", "nielit_bhubaneswar (local XAMPP)"),
        (
            "Match rule",
            "Require first+last name match; NAIK↔NAYAK and other spelling variants; "
            "middle KUMAR optional. Password column excluded.",
        ),
        ("District column", "Not in schema; city/state exported instead"),
    ]
    ws3["A1"] = "Metric"
    ws3["B1"] = "Value"
    style_header(ws3, 2)
    for i, (k, v) in enumerate(summary_lines, 2):
        ws3.cell(i, 1, k)
        ws3.cell(i, 2, v)

    start = len(summary_lines) + 4
    ws3.cell(start, 1, "Found query names")
    ws3.cell(start, 1).font = Font(bold=True)
    ws3.cell(start, 2, "Highlighted_In_List")
    for i, q in enumerate(found_queries, start + 1):
        ws3.cell(i, 1, q)
        ws3.cell(i, 2, "Yes" if q in HIGHLIGHTED else "No")

    start2 = start + 1 + max(len(found_queries), 1) + 2
    ws3.cell(start2, 1, "Not found query names")
    ws3.cell(start2, 1).font = Font(bold=True)
    for i, q in enumerate(not_found, start2 + 1):
        ws3.cell(i, 1, q)
        ws3.cell(i, 2, "Yes" if q in HIGHLIGHTED else "No")

    start3 = start2 + 1 + max(len(not_found), 1) + 2
    ws3.cell(start3, 1, "Ambiguous / duplicate matches")
    ws3.cell(start3, 1).font = Font(bold=True)
    if ambiguous_notes:
        for i, note in enumerate(ambiguous_notes, start3 + 1):
            ws3.cell(i, 1, note)
    else:
        ws3.cell(start3 + 1, 1, "None")

    autosize(ws3, max_width=90)
    wb.save(OUT_PATH)

    print("OUT=", OUT_PATH)
    print("FOUND_QUERIES=", len(found_queries))
    print("NOT_FOUND=", len(not_found))
    print("ROW_MATCHES=", len(found_rows))
    print("NEAR_ROWS=", len(near_rows))
    print("HIGHLIGHTED_FOUND=", len(highlighted_found))
    print("HIGHLIGHTED_MISSING=", len(highlighted_missing))
    print("FOUND_LIST=")
    for q in found_queries:
        hl = "HL" if q in HIGHLIGHTED else "  "
        matches = [r for r in found_rows if r["Query_Name"] == q]
        amb = matches[0]["Ambiguous_Duplicate"] if matches else "No"
        detail = "; ".join(
            f"{m['DB_name'].strip()}|{m['student_id']}|{m['Match_Type']}|{m['email']}|{m['mobile']}"
            for m in matches
        )
        print(f"  [{hl}] {q} -> {detail} amb={amb}")
    print("NOT_FOUND_LIST=")
    for q in not_found:
        hl = "HL" if q in HIGHLIGHTED else "  "
        print(f"  [{hl}] {q}")
    print("AMBIGUOUS=")
    for n in ambiguous_notes:
        print(" ", n)


if __name__ == "__main__":
    main()
