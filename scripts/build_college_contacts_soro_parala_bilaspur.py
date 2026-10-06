#!/usr/bin/env python3
"""Build modern college contact workbook for Soro, Paralakhemundi, Bilaspur."""

from __future__ import annotations

import re
from collections import Counter, defaultdict
from pathlib import Path

from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

OUT = Path(r"c:\xampp\htdocs\public_html\College_Contacts_Soro_Paralakhemundi_Bilaspur.xlsx")

# Colors matching existing workbook style
NAVY = "0F2C59"
TEAL = "0D7377"
BLUE = "1B6CA8"
GREEN = "2E8B57"
PURPLE = "6A4C93"
ORANGE = "C44900"
LINK_BLUE = "0563C1"
ALT_ROW = "F4F7FB"
STATUS_COMPLETE = "D1E7DD"
STATUS_PARTIAL = "FFF3CD"
STATUS_LIMITED = "F8D7DA"
WHITE = "FFFFFF"
GRAY = "6C757D"
THIN = Border(
    left=Side(style="thin", color="D0D7DE"),
    right=Side(style="thin", color="D0D7DE"),
    top=Side(style="thin", color="D0D7DE"),
    bottom=Side(style="thin", color="D0D7DE"),
)

HEADERS = [
    "Sl No",
    "District / City",
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

LOCATIONS = ["Soro", "Paralakhemundi", "Bilaspur"]
SUBTITLE = (
    "Soro (Balasore, Odisha) | Paralakhemundi (Gajapati, Odisha) | Bilaspur (Chhattisgarh) "
    "| Contacts from SAMS Odisha, DHE Odisha, DTE&T, FMU/BU affiliation lists, "
    "ABVV/CSVTU directories & college websites"
)


def na(v: str | None) -> str:
    if v is None:
        return "N/A"
    v = str(v).strip()
    return v if v else "N/A"


def split_emails(emails) -> list[str]:
    if not emails:
        return []
    if isinstance(emails, str):
        parts = re.split(r"[;,/|]+", emails)
    else:
        parts = list(emails)
    out = []
    seen = set()
    for p in parts:
        e = (p or "").strip().lower().replace(" ", "")
        # restore common patterns mangled by lower()
        e = e.replace("[at]", "@").replace("[dot]", ".")
        if not e or e in ("n/a", "na", "-", "—"):
            continue
        if "@" not in e:
            continue
        if e not in seen:
            seen.add(e)
            out.append(e)
    return out


def status_for(website, address, emails, phone) -> str:
    w = na(website) != "N/A"
    a = na(address) != "N/A"
    e = bool(emails)
    p = na(phone) != "N/A"
    score = sum([w, a, e, p])
    if score == 4:
        return "Complete"
    if score >= 2:
        return "Partial"
    return "Limited"


def normalize_row(loc, name, website, address, emails, phone, source):
    ems = split_emails(emails)
    e1 = ems[0] if len(ems) > 0 else "N/A"
    e2 = ems[1] if len(ems) > 1 else "N/A"
    e3 = ems[2] if len(ems) > 2 else "N/A"
    all_e = "; ".join(ems) if ems else "N/A"
    website = na(website)
    address = na(address)
    phone = na(phone)
    source = na(source)
    st = status_for(website, address, ems, phone)
    return {
        "location": loc,
        "name": name.strip(),
        "website": website,
        "address": address,
        "email1": e1,
        "email2": e2,
        "email3": e3,
        "all_emails": all_e,
        "phone": phone,
        "source": source,
        "status": st,
        "has_email": bool(ems),
    }


# ---------------------------------------------------------------------------
# DATA
# ---------------------------------------------------------------------------

RAW = []

# ===== SORO (Balasore district – town & surrounding) =====
RAW += [
    ("Soro", "Upendranath (U.N.) College, Soro", "https://www.uncollegesoro.com",
     "Soro, Dist-Balasore, Odisha - 756045",
     ["principaluncollege@yahoo.com"], "9348440455; 06788-221222",
     "College website (uncollegesoro.com) / Fakir Mohan University affiliated-college email list"),
    ("Soro", "Soro Women's College, Soro", "N/A",
     "Soro, Dist-Balasore, Odisha",
     ["principalswcd@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list / DHE Odisha"),
    ("Soro", "Sriram +3 Science Residential College, Soro", "N/A",
     "Soro, Dist-Balasore, Odisha",
     ["sriramplus3science.soro.Balasore@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list"),
    ("Soro", "Kalam Institute of Management & Technology, Soro", "N/A",
     "Matiapalha Chhack, Soro, Dist-Balasore, Odisha",
     ["kimt.Balasore2018@gmail.com"], "N/A",
     "Fakir Mohan University professional college email list"),
    ("Soro", "Soro School of Engineering, Soro", "N/A",
     "Soro, Dist-Balasore, Odisha",
     ["hemantarout2@gmail.com"], "9938569781; 7377565056; 9777003188",
     "SAMS Odisha Diploma College Contact list / DTE&T Odisha"),
    ("Soro", "Ayodhya Industrial Training Centre (ITC), Soro", "N/A",
     "At/Po-Soro, Dist-Balasore, Odisha",
     ["ayodhyaitc@gmail.com"], "06788-222666",
     "Public ITI directory / SAMS Odisha ITI listings"),
    ("Soro", "Khaira College, Khaira", "https://khairacollegekhaira.com",
     "Khaira, Dist-Balasore, Odisha - 756048",
     ["khairacollege@gmail.com"], "9937614548; 7978406908",
     "College website (khairacollegekhaira.com) / FMU affiliated-college email list"),
    ("Soro", "Simulia College, Markona", "N/A",
     "Markona, Simulia, Dist-Balasore, Odisha",
     ["principalsimulia126@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list / DHE Odisha"),
    ("Soro", "Oupada College, Oupada", "N/A",
     "Oupada, Dist-Balasore, Odisha",
     ["oupadadegreecollege@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list / DHE Odisha"),
    ("Soro", "Nilagiri College, Raj-Nilgiri", "N/A",
     "Raj-Nilgiri, Dist-Balasore, Odisha",
     ["nilgiricollege@yahoo.in"], "N/A",
     "Fakir Mohan University affiliated-college email list / DHE Odisha"),
    ("Soro", "Nilgiri Women's College, Nilgiri", "N/A",
     "Nilgiri, Dist-Balasore, Odisha",
     ["nilagiriwomensdegreecollege@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list"),
    ("Soro", "H.K. Mahatab College, Kupari", "N/A",
     "Kupari, Dist-Balasore, Odisha",
     ["hkmcollegekupari@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list / DHE Odisha"),
    ("Soro", "Saraswata Mahavidyalaya, Anantapur", "N/A",
     "Anantapur, Dist-Balasore, Odisha",
     ["saraswat_bls@yahoo.co.in"], "N/A",
     "Fakir Mohan University affiliated-college email list"),
    ("Soro", "Belavoomi Mahavidyalaya, Avana", "N/A",
     "Avana, Dist-Balasore, Odisha",
     ["bvmprincipal@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list"),
    ("Soro", "Swarnachud College, Mitrapur", "N/A",
     "Mitrapur, Dist-Balasore, Odisha",
     ["swarnachud@rediffmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list"),
    ("Soro", "Berhampur Degree College, Raj-Berhampur", "N/A",
     "Raj-Berhampur, Dist-Balasore, Odisha",
     ["degreecollege.berhampur@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list"),
    ("Soro", "Balikhand +3 College, Balikhand", "N/A",
     "Balikhand, Dist-Balasore, Odisha",
     ["balikhanddegreecollege@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list"),
    ("Soro", "Baba Panchalingeswar Degree College, Santragadia", "N/A",
     "Santragadia, Dist-Balasore, Odisha",
     ["bpdegree.santaragadia@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list"),
    ("Soro", "Remuna Degree College, Remuna", "N/A",
     "Remuna, Dist-Balasore, Odisha",
     ["remunadegreecollege@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list / DHE Odisha"),
    ("Soro", "Government Polytechnic, Balasore", "https://gpbalasore.org.in",
     "At-Bidyadharpur, Po-Remuna, Dist-Balasore, Odisha - 756019",
     ["govtpolytechnic.bls@rediffmail.com"], "06782-275577; 9437218739; 9776553650",
     "College website (gpbalasore.org.in) / SAMS Odisha Diploma / DTE&T Odisha"),
    ("Soro", "Balasore School of Engineering, Balasore", "N/A",
     "Balasore Sadar, Dist-Balasore, Odisha",
     ["principalbse@yahoo.co.in"], "8093623247; 8280042543; 8763368435",
     "SAMS Odisha Diploma College Contact list / DTE&T Odisha"),
    ("Soro", "B.I.T. Polytechnic, Balasore", "N/A",
     "Remuna, Dist-Balasore, Odisha",
     ["shymsndr12@rediffmail.com"], "9438047857; 9861782030",
     "SAMS Odisha Diploma College Contact list"),
    ("Soro", "CIPET, Balasore", "https://www.cipet.gov.in",
     "Remuna, Dist-Balasore, Odisha",
     ["apptc.balasore@cipet.gov.in"], "8342977308; 9929444230; 6370703006",
     "SAMS Odisha Diploma College Contact list / CIPET"),
    ("Soro", "Jhadeswar Institute of Engineering & Technology, Chhanpur", "N/A",
     "Chhanpur, Remuna, Dist-Balasore, Odisha",
     ["principaljiet@gmail.com"], "9438458393; 9853410164; 9853177263",
     "SAMS Odisha Diploma College Contact list"),
    ("Soro", "Odisha Polytechnic, Kuruda, Balasore", "N/A",
     "Kuruda, Remuna, Dist-Balasore, Odisha",
     ["odishapolytechnic@gmail.com"], "9437103710; 9337647510; 7008234307",
     "SAMS Odisha Diploma College Contact list"),
    ("Soro", "Ramarani Institute of Technology, Balasore", "N/A",
     "Remuna, Dist-Balasore, Odisha",
     ["principalramarani@yahoo.in", "principal@ritbls.edu.in"], "7381086305; 7735747524; 7735747525",
     "SAMS Odisha Diploma College Contact list"),
    ("Soro", "Mahalaxmi Institute of Technology & Engineering, Raj Nilagiri", "N/A",
     "Raj Nilagiri, Oupada, Dist-Balasore, Odisha",
     ["mitenilgiri@gmail.com"], "9556624983; 7504112995",
     "SAMS Odisha Diploma College Contact list"),
    ("Soro", "Raja Kishore Chandra Academy of Technology, Nilagiri", "N/A",
     "Nilagiri, Dist-Balasore, Odisha",
     ["rkcat2008@gmail.com"], "9853870507; 9853167697; 8270199914",
     "SAMS Odisha Diploma College Contact list"),
    ("Soro", "Nilasaila Institute of Science & Technology, Sergarh", "N/A",
     "Sergarh, Bahanaga, Dist-Balasore, Odisha",
     ["nist.bls@gmail.com"], "7381082061; 9040024788; 9439677227",
     "SAMS Odisha Diploma College Contact list"),
    ("Soro", "Venus Group of Educational & Research Institute, Bahanaga", "N/A",
     "Bahanaga, Dist-Balasore, Odisha",
     ["venusdiploma@gmail.com"], "9437977877; 7008164431; 9090621093",
     "SAMS Odisha Diploma College Contact list"),
    ("Soro", "Vijayanjali School of Engineering, Khantapada", "N/A",
     "Khantapada, Bahanaga, Dist-Balasore, Odisha",
     ["vse.edu.bls@gmail.com"], "9338272030; 9337048383; 8117868894",
     "SAMS Odisha Diploma College Contact list"),
    ("Soro", "Satya Sai School of Engineering (Diploma), Balasore", "N/A",
     "Balasore Sadar, Dist-Balasore, Odisha",
     ["jatindrakumar1965@gmail.com"], "9937531923",
     "SAMS Odisha Diploma College Contact list"),
    ("Soro", "Government ITI, Balasore", "http://govtitibalasore.org",
     "Mallikashpur, Dist-Balasore, Odisha - 756001",
     ["govt.itibls@gmail.com", "iti.bls@rediffmail.com"], "9437601519; 7008859328; 9937201820",
     "SAMS Odisha ITI contact list / College website (govtitibalasore.org)"),
    ("Soro", "Fakir Mohan Autonomous College, Balasore", "N/A",
     "Balasore, Dist-Balasore, Odisha",
     ["fmcollegebaleswar@gmail.com"], "N/A",
     "Fakir Mohan University autonomous college email list / DHE Odisha"),
    ("Soro", "K.K.S. Women's College, Balasore", "N/A",
     "Balasore, Dist-Balasore, Odisha",
     ["kks_wc@yahoo.co.in"], "N/A",
     "Fakir Mohan University affiliated-college email list / DHE Odisha"),
    ("Soro", "College of Teacher Education, Balasore", "N/A",
     "Balasore, Dist-Balasore, Odisha",
     ["principalcteBalasore@gmail.com"], "N/A",
     "Fakir Mohan University professional college email list / SAMS TE"),
    ("Soro", "BITS, Balasore", "N/A",
     "Balasore, Dist-Balasore, Odisha",
     ["bitsmgt@gmail.com"], "N/A",
     "Fakir Mohan University professional college email list"),
    ("Soro", "Jhadeswar +3 Science Degree College, Balasore", "N/A",
     "Balasore, Dist-Balasore, Odisha",
     ["jhadeswardegree@gmail.com"], "N/A",
     "Fakir Mohan University affiliated-college email list"),
]

# ===== PARALAKHEMUNDI (Gajapati) =====
RAW += [
    ("Paralakhemundi", "S.K.C.G. (Autonomous) College, Paralakhemundi", "https://www.skcgparala.ac.in",
     "Paralakhemundi, Dist-Gajapati, Odisha - 761200",
     ["principal@skcgparala.ac.in", "rti@skcgparala.ac.in"], "06815-223823; 9437641818",
     "College website (skcgparala.ac.in) / Berhampur University affiliation / DHE Odisha"),
    ("Paralakhemundi", "Government Polytechnic, Gajapati (Paralakhemundi)", "https://govpolytechgajapati.org",
     "At-Betaguda, Po-Jhami, Via-Paralakhemundi, Dist-Gajapati, Odisha - 761201",
     ["gpgajapati2013@gmail.com", "gadamaharana@gmail.com", "anilkumarpatra7@gmail.com"],
     "06815-225055; 9438517182; 9437207105",
     "College website (govpolytechgajapati.org / gpgajapati.in) / DTE&T Odisha / SAMS Diploma"),
    ("Paralakhemundi", "Centurion University of Technology and Management (Paralakhemundi Campus)",
     "https://cutm.ac.in",
     "Village Alluri Nagar, P.O. R Sitapur, Via-Uppalada, Paralakhemundi, Dist-Gajapati, Odisha - 761211",
     ["admission@cutm.ac.in"], "8260077222",
     "College website (cutm.ac.in) / Gajapati district college directory"),
    ("Paralakhemundi", "Women's Degree College, Paralakhemundi", "https://www.wdcgjp.ac.in",
     "Friends Colony, Paralakhemundi, Dist-Gajapati, Odisha - 761200",
     ["info@wcpkd.ac.in"], "9437259971; 9778264094",
     "College website (wdcgjp.ac.in) / AISHE / public Odisha college contact listing"),
    ("Paralakhemundi", "Hill Top Degree College, Mohana", "https://hilltopdegreecollegemohana.edu.in",
     "Mohana, Dist-Gajapati, Odisha - 761015",
     ["principal@hilltopdegreecollegemohana.edu.in", "hilltopdegreecollege@gmail.com"], "9437750758",
     "College website (hilltopdegreecollegemohana.edu.in) / Berhampur University list"),
    ("Paralakhemundi", "Indira Memorial Degree College, Chandiput", "https://www.imcollegechandiput.org.in",
     "Chandiput / Chandragiri, Dist-Gajapati, Odisha",
     ["N/A"], "06816-256524; 9438603069; 9556730530",
     "College website (imcollegechandiput.org.in) / Berhampur University affiliated college list"),
    ("Paralakhemundi", "Sri Ram Degree College, Kashinagar", "https://www.srdckngr.edu.in",
     "Kashinagar, Dist-Gajapati, Odisha - 761206",
     ["srdckngr@gmail.com"], "9437526971",
     "College website (srdckngr.edu.in) / Berhampur University affiliated college list"),
    ("Paralakhemundi", "Meena Ketan Degree College, Gurandi", "N/A",
     "Gurandi, Gosani Block, Dist-Gajapati, Odisha",
     ["mkc.gjp79@gmail.com"], "9437857975; 9437262524; 9778235128",
     "Public college listing / Berhampur University affiliated college list"),
    ("Paralakhemundi", "Sri Venketeswar Degree College, Kasinagar", "N/A",
     "Kasinagar, Dist-Gajapati, Odisha",
     ["N/A"], "N/A",
     "Berhampur University affiliated college list / DHE Odisha"),
    ("Paralakhemundi", "Baba Saheb Ambedkar +3 Degree College, Khajuripada", "N/A",
     "Khajuripada, Dist-Gajapati, Odisha",
     ["N/A"], "N/A",
     "Berhampur University affiliated college list / DHE Odisha"),
    ("Paralakhemundi", "Dr. B.R. Ambedkar National College, Ramagiri", "N/A",
     "Ramagiri, Dist-Gajapati, Odisha",
     ["N/A"], "N/A",
     "Berhampur University affiliated college list / DHE Odisha"),
    ("Paralakhemundi", "Parsuram Degree College, Sevakpur", "N/A",
     "Sevakpur, Dist-Gajapati, Odisha",
     ["N/A"], "N/A",
     "Berhampur University affiliated college list / DHE Odisha"),
    ("Paralakhemundi", "Binodini Science College, Paralakhemundi area", "N/A",
     "Parlakhemundi-Gumma Rd area, Dist-Gajapati, Odisha",
     ["N/A"], "N/A",
     "Gajapati district public utilities college directory"),
    ("Paralakhemundi", "Gajapati College of Nursing, Ranipentha, Paralakhemundi", "N/A",
     "Ranipentha, Paralakhemundi, Dist-Gajapati, Odisha",
     ["N/A"], "N/A",
     "Berhampur University professional affiliated college list"),
    ("Paralakhemundi", "Government ITI, Gumma, Gajapati", "N/A",
     "Gumma, Dist-Gajapati, Odisha",
     ["itigumma1@rediffmail.com"], "9040703003; 8917321023; 9556469752",
     "SAMS Odisha ITI contact list"),
    ("Paralakhemundi", "Government ITI, Chandragiri, Gajapati", "N/A",
     "Chandragiri / Mohana, Dist-Gajapati, Odisha",
     ["itichandragiri@rediffmail.com"], "7847913022; 9348617697; 8260751573",
     "SAMS Odisha ITI contact list"),
    ("Paralakhemundi", "Government ITI, Raigada, Gajapati", "N/A",
     "Rayagada Block, Dist-Gajapati, Odisha",
     ["iti.rayagadagpt2012@gmail.com"], "9861385341; 7008054302; 9090563216",
     "SAMS Odisha ITI contact list"),
]

# ===== BILASPUR (Chhattisgarh – primary) =====
RAW += [
    ("Bilaspur", "Atal Bihari Vajpayee Vishwavidyalaya (ABVV), Bilaspur", "https://abvv.ac.in",
     "Ratanpur Road, Koni, Bilaspur, Chhattisgarh - 495009",
     ["registrar@abvv.ac.in", "registrar@bilaspuruniversity.ac.in"], "8889928648; 07752-220031",
     "University website (abvv.ac.in) / CG higher education"),
    ("Bilaspur", "Guru Ghasidas Vishwavidyalaya (Central University), Bilaspur", "https://www.ggu.ac.in",
     "Koni, Bilaspur, Chhattisgarh - 495009",
     ["ggv.registrar@gmail.com"], "07752-260342",
     "University website / UGC central university directory"),
    ("Bilaspur", "Pandit Sundarlal Sharma (Open) University, Bilaspur", "https://pssou.ac.in",
     "Bilaspur, Chhattisgarh",
     ["N/A"], "N/A",
     "CG higher education / Open university public listing"),
    ("Bilaspur", "Government Engineering College, Bilaspur", "http://gecbsp.ac.in",
     "Koni, Bilaspur, Chhattisgarh - 495009",
     ["principalgecbilaspur@gmail.com", "tpo@gecbsp.ac.in", "principalaicte@gecbsp.ac.in"],
     "07752-260289; 9424144099",
     "College website (gecbsp.ac.in) / CSVTU affiliated engineering list / AICTE"),
    ("Bilaspur", "Chouksey Engineering College, Bilaspur", "https://cecbilaspur.ac.in",
     "Lalkhadan, Masturi Road, NH-49, Bilaspur, Chhattisgarh - 495004",
     ["info@cecbilaspur.ac.in", "admission@cecbilaspur.ac.in", "cecbilaspur@lnct.ac.in"],
     "9752410899; 7746099992; 9752410911",
     "College website (cecbilaspur.ac.in) / CSVTU affiliated engineering list"),
    ("Bilaspur", "J.K. Institute of Engineering, Bilaspur", "https://www.jkie.jkedugroup.in",
     "Near Gatora Railway Station, Village Gotora / Farhada, Bilaspur, Chhattisgarh",
     ["N/A"], "N/A",
     "CSVTU affiliated engineering institute list"),
    ("Bilaspur", "Lakhmi Chand Institute of Technology (LCIT), Bilaspur", "https://www.lcit.edu.in",
     "Vidyasthali, Village Bordi / Bodri, P.O. Chakarbhata, Bilaspur, Chhattisgarh - 495220",
     ["info@lcit.edu.in"], "9179080002; 9522220113; 9630052722",
     "College website (lcit.edu.in) / CSVTU affiliated engineering list"),
    ("Bilaspur", "Dr. C.V. Raman Institute of Science and Technology, Bilaspur", "N/A",
     "Kargi Road, Kota, Bilaspur, Chhattisgarh",
     ["N/A"], "07753-253801",
     "Public engineering college listing / AICTE institute directories"),
    ("Bilaspur", "Government Polytechnic (Co-Ed), Bilaspur", "http://gecbsp.ac.in",
     "Govt. Engineering College Campus, Koni, Bilaspur, Chhattisgarh",
     ["principalgecbilaspur@gmail.com"], "07752-260289",
     "CSVTU diploma institute list / DTE Chhattisgarh"),
    ("Bilaspur", "Government Girls Polytechnic, Bilaspur", "N/A",
     "Government Engineering College Campus, Koni, Bilaspur, Chhattisgarh",
     ["N/A"], "N/A",
     "CSVTU diploma institute list"),
    ("Bilaspur", "Bilaspur College of Polytechnic", "N/A",
     "Near Gatora Rly Station, Gram Farhada, Bilaspur, Chhattisgarh",
     ["N/A"], "N/A",
     "CSVTU diploma institute list"),
    ("Bilaspur", "Ayush College of Polytechnic, Pendra Road area", "N/A",
     "Gram Maduka, Post Darri, Teh. Pendra Road, Dist-Bilaspur, Chhattisgarh",
     ["N/A"], "N/A",
     "CSVTU diploma institute list"),
    ("Bilaspur", "Institute of Advanced Studies in Education (IASE), Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["iasebilaspur@gmail.com"], "9425222737",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. E. Ragvendra Rao P.G. Science College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["pr.sc.college@gmail.com"], "9981125599",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. Bilasa Girls P.G. College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["bilasagirlscollege_bilaspur@rediffmail.com"], "9425538230",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. Jamuna Prasad Verma P.G. Arts and Commerce College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["gpgacc.bsp@gmail.com"], "9098525975",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. Mata Shabari Naveen Girls College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["gmsngc1989@gmail.com"], "8253021704",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. Agrasen College, Bilha", "N/A",
     "Bilha, Dist-Bilaspur, Chhattisgarh",
     ["govtagrasencollegebilha89@gmail.com"], "9754164015",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. Niranjan Kesharwani College, Kota", "N/A",
     "Kota, Dist-Bilaspur, Chhattisgarh",
     ["gnkckota@gmail.com"], "9424019919",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. Pataleshwar College, Masturi", "N/A",
     "Masturi, Dist-Bilaspur, Chhattisgarh",
     ["govtcollegemasturi@gmail.com"], "9993091054",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. Mahamaya College, Ratanpur", "N/A",
     "Ratanpur, Dist-Bilaspur, Chhattisgarh",
     ["gmc_ratanpur@rediffmail.com"], "7974423430",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Madan Lal Shukla Govt. College, Seepat", "N/A",
     "Seepat, Dist-Bilaspur, Chhattisgarh",
     ["gmlscseepat@gmail.com"], "9425543779",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. J.M.P. College, Takhatpur", "N/A",
     "Takhatpur, Dist-Bilaspur, Chhattisgarh",
     ["principalgjmptakhatpur@gmail.com", "govtcollegetakhatpur@gmail.com"], "9993407184",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. Naveen College, Sakri", "N/A",
     "Sakri, Dist-Bilaspur, Chhattisgarh",
     ["govtcollegesakri@gmail.com"], "8839748096",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Govt. Naveen Girls College, Takhatpur", "N/A",
     "Takhatpur, Dist-Bilaspur, Chhattisgarh",
     ["govtcollegeTAKHATPUR@gmail.com"], "8120535866",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "C.M.D. / C.M. Dubey Post Graduate College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["info@cmdpgcollege.in"], "9039260630",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "College of IT & Applied Social Science, Pendari, Bilaspur", "N/A",
     "Pendari, Bilaspur, Chhattisgarh",
     ["dronacollege202@gmail.com"], "9425535622",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "D.L.S. P.G. College, Sarkanda, Bilaspur", "N/A",
     "Sarkanda, Bilaspur, Chhattisgarh",
     ["dlspgcollege@gmail.com"], "6260007180",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "D.P. Vipra College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["dpvipracollege@gmail.com"], "8602041180",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "D.P. Vipra College of Education, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["dpvipracollegeofeducation@gmail.com"], "9302754329",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "D.P. Vipra Law College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["dpvlawprincipal@yahoo.com"], "9926138734",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "G.T.B. College, Nehru Nagar, Bilaspur", "N/A",
     "Nehru Nagar, Bilaspur, Chhattisgarh",
     ["gtbpte@gmail.com"], "9300021347",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Kaushlendra Rao Law College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["krlawcollege@gmail.com"], "9424163089",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Mahamaya Excellency College, Narmada Nagar, Bilaspur", "N/A",
     "Narmada Nagar, Bilaspur, Chhattisgarh",
     ["mahamayaexcellencybsp@gmail.com"], "7974015527",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Nalini Prabha Dev Prasad Roy College, Sarkanda", "N/A",
     "Sarkanda, Bilaspur, Chhattisgarh",
     ["ndrcollege.bsp@gmail.com"], "9303296088",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "P.N.S. College, Rajendra Nagar, Bilaspur", "N/A",
     "Rajendra Nagar, Bilaspur, Chhattisgarh",
     ["principal.pns.college@gmail.com"], "9827887445",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "S.B.T. College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["sbtbsp@gmail.com"], "9039616323",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Shanti Niketan College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["sewaindia2010@gmail.com"], "7000873833",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Shri Siddhapith Mahamaya College of Education, Nehru Nagar", "N/A",
     "Nehru Nagar, Bilaspur, Chhattisgarh",
     ["bharatpandey83@gmail.com"], "9993755507",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Shubham Shikshan College, Shubham Vihar, Bilaspur", "N/A",
     "Shubham Vihar, Bilaspur, Chhattisgarh",
     ["ShubhamShikshan@gmail.com"], "9300671823",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Sant Guru Ghasidas Arts and Science College, Pachpedi", "N/A",
     "Pachpedi, Dist-Bilaspur, Chhattisgarh",
     ["SantGuruGhasidas@Gmail.com"], "9993532486",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "B.L.T. College, Ratanpur", "N/A",
     "Ratanpur, Dist-Bilaspur, Chhattisgarh",
     ["bltbsp@yahoo.in"], "7000629548",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Mahamaya Technical and Professional College, Ratanpur", "N/A",
     "Ratanpur, Dist-Bilaspur, Chhattisgarh",
     ["harishrtp@gmail.com"], "9425546607",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "C.S.R. College, Pipartarai, Kota", "N/A",
     "Pipartarai, Kota, Dist-Bilaspur, Chhattisgarh",
     ["csrcollege.piper@gmail.com"], "9425582180",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "J.E.S. College, Farhada", "N/A",
     "Farhada, Dist-Bilaspur, Chhattisgarh",
     ["jesbilaspur@gmail.com"], "9827900689",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "G.T.B. College of Professional and Technical Education, Farhada", "N/A",
     "Farhada, Dist-Bilaspur, Chhattisgarh",
     ["gtbpte@gmail.com"], "9752112233",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Pt. Madan Mohan Malviya College of Education, Lawar", "N/A",
     "Lawar, Dist-Bilaspur, Chhattisgarh",
     ["pmmmce@gmail.com"], "9406299803",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "L.C.I.T. College of Commerce and Science, Bodri", "N/A",
     "Bodri, Bilaspur, Chhattisgarh",
     ["lcit.cs@gmail.com"], "9685091020",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Sandipani Academy, Pendri, Masturi", "N/A",
     "Pendri, Masturi, Dist-Bilaspur, Chhattisgarh",
     ["sandipanieducation.masturi@gmail.com"], "9755152052",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "I.P.S. Gurukul College, Beltukari, Ganiyari", "N/A",
     "Beltukari, Ganiyari, Dist-Bilaspur, Chhattisgarh",
     ["ipsgurukul.college@gmail.com"], "7771921000",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Siddharth College, Hardikala, Bilha", "N/A",
     "Hardikala, Bilha, Dist-Bilaspur, Chhattisgarh",
     ["info@siddharthcollege.co.in"], "9827159652",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Chouksey College of Science and Commerce, Lalkhadan", "N/A",
     "Lalkhadan, Bilaspur, Chhattisgarh",
     ["ccscbsp@gmail.com"], "9300733403",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Jagrani Devi College, Bilaspur", "N/A",
     "Bilaspur, Chhattisgarh",
     ["jrdcollege249@gmail.com"], "8817285511",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Sardar Bhagat Singh College, Belgahna / Kargi Road, Kota", "N/A",
     "Belgahna, Kargi Road, Kota, Dist-Bilaspur, Chhattisgarh",
     ["sbscollegebelgahna965@gmail.com"], "8269887965",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "B.R. Sao Science, Arts and Commerce College, Nevsa Beltara", "N/A",
     "Nevsa Beltara, Dist-Bilaspur, Chhattisgarh",
     ["giriraj2242@gmail.com"], "8718888826",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Vaidic Mahavidyalaya, Sipat", "N/A",
     "Sipat / Seepat, Dist-Bilaspur, Chhattisgarh",
     ["dwarikeshpandey1963@gmail.com"], "9827900451",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Usha Devi Memorial College, Sakri", "N/A",
     "Sakri, Bilaspur, Chhattisgarh",
     ["ushadevicollege@gmail.com"], "6232060055",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Nayantara Sharma College, Pandhi", "N/A",
     "Pandhi, Dist-Bilaspur, Chhattisgarh",
     ["pranjaldiwan90@yahoo.com"], "7415770533",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "R.D.S. College, Umariya, Bilha", "N/A",
     "Umariya, Bilha, Dist-Bilaspur, Chhattisgarh",
     ["jlmahto1972@gmail.com"], "9826140699",
     "ABVV College Development Council affiliated college contact list"),
    ("Bilaspur", "Maulana Azad Shiksha Mahavidyalaya, Gondpara, Bilaspur", "N/A",
     "Subhash Nagar, Gondpara, Bilaspur, Chhattisgarh",
     ["mace.sai@rdiffmail.com"], "9644188203",
     "ABVV College Development Council affiliated college contact list"),
]


def build_rows():
    rows = []
    seen = set()
    for item in RAW:
        loc, name, website, address, emails, phone, source = item
        # filter N/A-only email lists
        if emails == ["N/A"]:
            emails = []
        key = (loc.lower(), name.lower())
        if key in seen:
            continue
        seen.add(key)
        rows.append(normalize_row(loc, name, website, address, emails, phone, source))
    # stable order by location then name
    order = {l: i for i, l in enumerate(LOCATIONS)}
    rows.sort(key=lambda r: (order.get(r["location"], 99), r["name"].lower()))
    for i, r in enumerate(rows, 1):
        r["sl"] = i
    return rows


def fill_header_banner(ws, title, merge_cols=12):
    ws.merge_cells(start_row=1, start_column=1, end_row=1, end_column=merge_cols)
    ws.merge_cells(start_row=2, start_column=1, end_row=2, end_column=merge_cols)
    c1 = ws.cell(1, 1, title)
    c1.font = Font(name="Calibri", size=18, bold=True, color=NAVY)
    c1.alignment = Alignment(vertical="center")
    c2 = ws.cell(2, 1, SUBTITLE)
    c2.font = Font(name="Calibri", size=10, color=GRAY)
    c2.alignment = Alignment(wrap_text=True, vertical="center")
    ws.row_dimensions[1].height = 28
    ws.row_dimensions[2].height = 32


def write_table_headers(ws, row=3):
    for col, h in enumerate(HEADERS, 1):
        cell = ws.cell(row, col, h)
        cell.fill = PatternFill("solid", fgColor=NAVY)
        cell.font = Font(name="Calibri", size=11, bold=True, color=WHITE)
        cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
        cell.border = THIN
    ws.row_dimensions[row].height = 30
    ws.auto_filter.ref = f"A{row}:{get_column_letter(len(HEADERS))}{row}"
    ws.freeze_panes = f"A{row+1}"


def style_data_row(ws, r_idx, record, alt=False):
    values = [
        record["sl"] if "sheet_sl" not in record else record["sheet_sl"],
        record["location"],
        record["name"],
        record["website"],
        record["address"],
        record["email1"],
        record["email2"],
        record["email3"],
        record["all_emails"],
        record["phone"],
        record["source"],
        record["status"],
    ]
    fill = PatternFill("solid", fgColor=ALT_ROW) if alt else None
    status_fills = {
        "Complete": PatternFill("solid", fgColor=STATUS_COMPLETE),
        "Partial": PatternFill("solid", fgColor=STATUS_PARTIAL),
        "Limited": PatternFill("solid", fgColor=STATUS_LIMITED),
    }
    for col, val in enumerate(values, 1):
        cell = ws.cell(r_idx, col, val)
        cell.font = Font(name="Calibri", size=10)
        cell.alignment = Alignment(vertical="center", wrap_text=True)
        cell.border = THIN
        if fill:
            cell.fill = fill
        # hyperlink website
        if col == 4 and val not in ("N/A",) and str(val).startswith("http"):
            cell.hyperlink = val
            cell.font = Font(name="Calibri", size=10, color=LINK_BLUE, underline="single")
        # email styling
        if col in (6, 7, 8, 9) and val not in ("N/A",):
            cell.font = Font(name="Calibri", size=10, color=TEAL)
            if col in (6, 7, 8) and "@" in str(val):
                cell.hyperlink = f"mailto:{val}"
        if col == 12:
            cell.fill = status_fills.get(val, cell.fill)
            cell.alignment = Alignment(horizontal="center", vertical="center")
            cell.font = Font(name="Calibri", size=10, bold=True)


def set_col_widths(ws):
    widths = [8, 16, 42, 28, 40, 32, 28, 28, 40, 28, 42, 12]
    for i, w in enumerate(widths, 1):
        ws.column_dimensions[get_column_letter(i)].width = w


def write_college_sheet(wb, title, sheet_title, records):
    ws = wb.create_sheet(sheet_title)
    fill_header_banner(ws, title)
    write_table_headers(ws, 3)
    for i, rec in enumerate(records):
        r = dict(rec)
        r["sheet_sl"] = i + 1
        style_data_row(ws, 4 + i, r, alt=(i % 2 == 1))
        ws.row_dimensions[4 + i].height = 36
    if records:
        ws.auto_filter.ref = f"A3:L{3 + len(records)}"
    set_col_widths(ws)
    return ws


def build_dashboard(wb, rows):
    ws = wb.create_sheet("Dashboard", 0)
    ws.merge_cells("A1:H1")
    ws.merge_cells("A2:H2")
    ws["A1"] = "College Contact Directory"
    ws["A1"].font = Font(name="Calibri", size=20, bold=True, color=NAVY)
    ws["A2"] = "Modern contact workbook — Soro | Paralakhemundi | Bilaspur"
    ws["A2"].font = Font(name="Calibri", size=12, bold=True, color=TEAL)
    ws.row_dimensions[1].height = 30
    ws.row_dimensions[2].height = 22

    total = len(rows)
    with_email = sum(1 for r in rows if r["has_email"])
    with_web = sum(1 for r in rows if r["website"] != "N/A")
    with_phone = sum(1 for r in rows if r["phone"] != "N/A")
    complete = sum(1 for r in rows if r["status"] == "Complete")
    multi = sum(1 for r in rows if r["email2"] != "N/A")

    kpi_labels = ["Total Colleges", "With Email", "With Website", "With Phone", "Complete Records", "Multiple Emails"]
    kpi_vals = [total, with_email, with_web, with_phone, complete, multi]
    kpi_colors = [NAVY, TEAL, BLUE, GREEN, PURPLE, ORANGE]
    for i, (lab, val, col) in enumerate(zip(kpi_labels, kpi_vals, kpi_colors)):
        c = ws.cell(4, i + 1, lab)
        c.fill = PatternFill("solid", fgColor=col)
        c.font = Font(name="Calibri", bold=True, color=WHITE)
        c.alignment = Alignment(horizontal="center")
        v = ws.cell(5, i + 1, val)
        v.fill = PatternFill("solid", fgColor=col)
        v.font = Font(name="Calibri", size=16, bold=True, color=WHITE)
        v.alignment = Alignment(horizontal="center")

    ws["A7"] = "Location-wise Summary"
    ws["A7"].font = Font(name="Calibri", size=14, bold=True, color=NAVY)

    sum_headers = ["Location", "Colleges", "With Email", "With Website", "With Phone", "Complete", "Partial", "Limited"]
    for i, h in enumerate(sum_headers, 1):
        cell = ws.cell(8, i, h)
        cell.fill = PatternFill("solid", fgColor=TEAL)
        cell.font = Font(name="Calibri", bold=True, color=WHITE)
        cell.alignment = Alignment(horizontal="center")

    for li, loc in enumerate(LOCATIONS):
        subset = [r for r in rows if r["location"] == loc]
        vals = [
            loc,
            len(subset),
            sum(1 for r in subset if r["has_email"]),
            sum(1 for r in subset if r["website"] != "N/A"),
            sum(1 for r in subset if r["phone"] != "N/A"),
            sum(1 for r in subset if r["status"] == "Complete"),
            sum(1 for r in subset if r["status"] == "Partial"),
            sum(1 for r in subset if r["status"] == "Limited"),
        ]
        for ci, v in enumerate(vals, 1):
            cell = ws.cell(9 + li, ci, v)
            cell.font = Font(name="Calibri", bold=(ci == 1))
            if li % 2 == 1:
                cell.fill = PatternFill("solid", fgColor=ALT_ROW)
            cell.border = THIN

    ws["A13"] = "Data Completeness (All Locations)"
    ws["A13"].font = Font(name="Calibri", size=14, bold=True, color=NAVY)
    ws["A14"] = "Status"
    ws["B14"] = "Count"
    for col in (1, 2):
        ws.cell(14, col).fill = PatternFill("solid", fgColor=TEAL)
        ws.cell(14, col).font = Font(bold=True, color=WHITE)

    for i, st in enumerate(["Complete", "Partial", "Limited"]):
        ws.cell(15 + i, 1, st if st != "Limited" else "Name only / Limited")
        ws.cell(15 + i, 2, sum(1 for r in rows if r["status"] == st))
        fill = {
            "Complete": STATUS_COMPLETE,
            "Partial": STATUS_PARTIAL,
            "Limited": STATUS_LIMITED,
        }[st]
        ws.cell(15 + i, 1).fill = PatternFill("solid", fgColor=fill)
        ws.cell(15 + i, 2).fill = PatternFill("solid", fgColor=fill)

    ws["A19"] = "Quick Navigation"
    ws["A19"].font = Font(name="Calibri", size=14, bold=True, color=NAVY)
    nav = [
        ("All Colleges", "Full combined directory"),
        ("Soro", "Soro town & surrounding Balasore (Odisha)"),
        ("Paralakhemundi", "Paralakhemundi / Gajapati district (Odisha)"),
        ("Bilaspur", "Bilaspur city & district (Chhattisgarh) — primary"),
        ("Email Ready", "Rows with at least one email"),
        ("Sources & Notes", "Blank-field notes + source catalogue"),
    ]
    ws["A20"] = "Sheet"
    ws["B20"] = "Description"
    ws["A20"].fill = PatternFill("solid", fgColor=NAVY)
    ws["B20"].fill = PatternFill("solid", fgColor=NAVY)
    ws["A20"].font = Font(bold=True, color=WHITE)
    ws["B20"].font = Font(bold=True, color=WHITE)
    for i, (name, desc) in enumerate(nav):
        ws.cell(21 + i, 1, name).font = Font(name="Calibri", bold=True, color=TEAL)
        ws.cell(21 + i, 2, desc)

    ws["A28"] = "Geographic note"
    ws["A28"].font = Font(name="Calibri", size=12, bold=True, color=NAVY)
    ws.merge_cells("A29:H30")
    ws["A29"] = (
        "Soro = Soro town/area in Balasore district, Odisha (nearby Khaira, Simulia/Markona, Nilgiri, "
        "Remuna & Balasore technical institutes included). Paralakhemundi = Gajapati district, Odisha. "
        "Bilaspur = primarily Bilaspur, Chhattisgarh (major city / ABVV & CSVTU hub). "
        "No separate major Odisha college-hub named Bilaspur was found; CG is the focus."
    )
    ws["A29"].alignment = Alignment(wrap_text=True, vertical="top")

    for col, w in enumerate([18, 14, 14, 14, 14, 12, 12, 12], 1):
        ws.column_dimensions[get_column_letter(col)].width = w
    return ws


def build_sources(wb, rows):
    ws = wb.create_sheet("Sources & Notes")
    ws["A1"] = "Sources & Notes"
    ws["A1"].font = Font(name="Calibri", size=18, bold=True, color=NAVY)
    ws.merge_cells("A1:B1")

    ws["A3"] = "Why many fields were blank"
    ws["A3"].font = Font(name="Calibri", size=13, bold=True, color=NAVY)
    notes = [
        "1. DHE Odisha / university affiliation lists often publish college NAME + place, but not always website/email/phone together.",
        "2. SAMS Odisha has strong contact emails for Diploma/Polytechnic and many ITIs; not every +3 degree college.",
        "3. Many rural/aided degree colleges do not maintain an active public website.",
        "4. Some directories list phone only; email may be outdated or unpublished.",
        "5. Blank/N/A fields mean 'not found in public sources used for this workbook' — not that the college has no contact.",
        "6. Where multiple emails were found, ALL emails are kept in Email 1 / Email 2 / Email 3 and All Contact Emails.",
        "7. Phone numbers with '/' in source data were split and joined with '; ' for Excel readability.",
        "8. Always verify the latest contact on the college website, SAMS, ABVV/CSVTU portals before official mailing.",
        "9. Bilaspur focus is Bilaspur, Chhattisgarh. No major Odisha Bilaspur college cluster was identified for a separate sheet.",
        "10. Soro sheet includes in-town colleges plus surrounding Balasore institutes commonly serving the Soro area.",
    ]
    for i, n in enumerate(notes):
        ws.cell(4 + i, 1, n)

    start = 15
    ws.cell(start, 1, "Official / Public Sources Used").font = Font(size=13, bold=True, color=NAVY)
    ws.cell(start + 1, 1, "#").fill = PatternFill("solid", fgColor=TEAL)
    ws.cell(start + 1, 2, "Source").fill = PatternFill("solid", fgColor=TEAL)
    ws.cell(start + 1, 1).font = Font(bold=True, color=WHITE)
    ws.cell(start + 1, 2).font = Font(bold=True, color=WHITE)

    sources_list = [
        "SAMS Odisha — Diploma College Contact Information (skill.samsodisha.gov.in)",
        "SAMS Odisha — ITI Institute Contact Information / NCC ITI lists",
        "DHE Odisha — Non-Government Aided / Degree college directories (dhe.odisha.gov.in)",
        "DTE&T Odisha — Diploma / Polytechnic institute directory (dtet.odisha.gov.in)",
        "Fakir Mohan University — Affiliated colleges email list (fmuniversity.nic.in)",
        "Berhampur University — District-wise affiliated colleges (Gajapati)",
        "Gajapati district public utilities — Colleges/Universities directory",
        "Individual Odisha college websites (uncollegesoro.com, skcgparala.ac.in, gpbalasore.org.in, cutm.ac.in, etc.)",
        "Atal Bihari Vajpayee Vishwavidyalaya (ABVV) — College Development Council contacts",
        "CSVTU — Affiliated engineering & diploma institute lists (csvtu.ac.in)",
        "Individual Bilaspur college websites (gecbsp.ac.in, cecbilaspur.ac.in, lcit.edu.in, abvv.ac.in, etc.)",
        "AICTE / public higher-education directories (cross-check)",
    ]
    for i, s in enumerate(sources_list, 1):
        ws.cell(start + 1 + i, 1, i)
        ws.cell(start + 1 + i, 2, s)

    usage_row = start + 3 + len(sources_list)
    ws.cell(usage_row, 1, "Source usage count in this workbook").font = Font(size=13, bold=True, color=NAVY)
    ws.cell(usage_row + 1, 1, "Source text (as written in Source of Record column)")
    ws.cell(usage_row + 1, 2, "Colleges")
    for col in (1, 2):
        ws.cell(usage_row + 1, col).fill = PatternFill("solid", fgColor=TEAL)
        ws.cell(usage_row + 1, col).font = Font(bold=True, color=WHITE)

    counts = Counter(r["source"] for r in rows)
    for i, (src, cnt) in enumerate(sorted(counts.items(), key=lambda x: (-x[1], x[0]))):
        ws.cell(usage_row + 2 + i, 1, src)
        ws.cell(usage_row + 2 + i, 2, cnt)

    legend_row = usage_row + 4 + len(counts)
    ws.cell(legend_row, 1, "Legend — Data Status").font = Font(size=13, bold=True, color=NAVY)
    legends = [
        ("Complete", "Website + Address + Email + Phone all present", STATUS_COMPLETE),
        ("Partial", "At least 2 of Website / Address / Email / Phone present", STATUS_PARTIAL),
        ("Name only / Limited", "Mostly name (+ maybe address); email/website/phone sparse", STATUS_LIMITED),
    ]
    for i, (a, b, color) in enumerate(legends):
        c1 = ws.cell(legend_row + 1 + i, 1, a)
        c2 = ws.cell(legend_row + 1 + i, 2, b)
        c1.fill = PatternFill("solid", fgColor=color)
        c2.fill = PatternFill("solid", fgColor=color)

    ws.column_dimensions["A"].width = 70
    ws.column_dimensions["B"].width = 70
    return ws


def main():
    rows = build_rows()
    wb = Workbook()
    # remove default
    default = wb.active
    wb.remove(default)

    build_dashboard(wb, rows)
    write_college_sheet(wb, "All Colleges — Contact Directory", "All Colleges", rows)

    for loc in LOCATIONS:
        subset = [r for r in rows if r["location"] == loc]
        title_map = {
            "Soro": "Soro (Balasore, Odisha) — College Contacts",
            "Paralakhemundi": "Paralakhemundi (Gajapati, Odisha) — College Contacts",
            "Bilaspur": "Bilaspur (Chhattisgarh) — College Contacts",
        }
        write_college_sheet(wb, title_map[loc], loc, subset)

    email_ready = [r for r in rows if r["has_email"]]
    write_college_sheet(wb, "Email Ready — Colleges with Contact Email", "Email Ready", email_ready)
    build_sources(wb, rows)

    OUT.parent.mkdir(parents=True, exist_ok=True)
    wb.save(OUT)

    # summary print
    print("Saved:", OUT)
    print("Total:", len(rows))
    for loc in LOCATIONS:
        subset = [r for r in rows if r["location"] == loc]
        print(f"  {loc}: {len(subset)} colleges, {sum(1 for r in subset if r['has_email'])} with email")
    print("With email:", sum(1 for r in rows if r["has_email"]))
    print("Complete:", sum(1 for r in rows if r["status"] == "Complete"))
    print("Partial:", sum(1 for r in rows if r["status"] == "Partial"))
    print("Limited:", sum(1 for r in rows if r["status"] == "Limited"))


if __name__ == "__main__":
    main()
