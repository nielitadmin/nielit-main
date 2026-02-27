# PDF Layout Diagram - Visual Guide
## Exact Layout Specifications

---

## 📐 Header Layout (Fixed - No Overlap)

### Horizontal Layout:
```
0mm         15mm    55mm              135mm 140mm           190mm    210mm
│           │       │                 │     │               │        │
│  MARGIN   │       │   TITLE AREA    │ GAP │   ID BADGE    │ MARGIN │
│   15mm    │       │     (80mm)      │ 5mm │    (50mm)     │  15mm  │
│           │       │                 │     │               │        │
│           │       ├─────────────────┤     ├───────────────┤        │
│           │       │ CANDIDATE       │     │ STUDENT ID    │        │
│           │       │ DETAILS         │     │ NIELIT/2025/  │        │
│           │       │                 │     │ PPI/0002      │        │
│           │       └─────────────────┘     └───────────────┘        │
│           │                                                         │
└───────────┴─────────────────────────────────────────────────────────┘
```

### Key Points:
- ✅ Title starts at 55mm (after logo)
- ✅ Title width: 80mm (55-135mm)
- ✅ Gap: 5mm (135-140mm) - NO CONTENT HERE
- ✅ ID badge starts at 140mm
- ✅ ID badge width: 50mm (140-190mm)
- ✅ **NO OVERLAP!**

---

## 📄 Page 1 Layout

### Vertical Layout:
```
0mm    ┌─────────────────────────────────────────┐
       │                                         │
15mm   ├─────────────────────────────────────────┤ ← Top Margin
       │ ╔═══════════════════════════════════╗   │
       │ ║ HEADER (45mm)                     ║   │
       │ ║ - Logo (30x30mm)                  ║   │
20mm   │ ║ - Title (left)                    ║   │
       │ ║ - ID Badge (right)                ║   │
60mm   │ ╚═══════════════════════════════════╝   │
       ├─────────────────────────────────────────┤
       │                                         │
       │ ┌──────────┐  ┌──────────────────────┐ │
       │ │ PHOTO    │  │ BASIC INFO           │ │
       │ │ (55x65mm)│  │ - Name               │ │
       │ │          │  │ - Course, Status     │ │
       │ │          │  │ - DOB, Age           │ │
       │ │          │  │ - Mobile, Email      │ │
       │ │ SIGN     │  │                      │ │
       │ │ (55x14mm)│  │                      │ │
145mm  │ └──────────┘  └──────────────────────┘ │
       ├─────────────────────────────────────────┤
       │ FAMILY DETAILS (25mm)                   │
       │ - Father's Name                         │
170mm  │ - Mother's Name                         │
       ├─────────────────────────────────────────┤
       │ ADDRESS & LOCATION (30mm)               │
       │ - Address                               │
200mm  │ - City, State, Pincode                  │
       ├─────────────────────────────────────────┤
       │ PERSONAL INFORMATION (35mm)             │
       │ - Gender, Religion                      │
       │ - Category, Marital Status              │
235mm  │ - Nationality, Aadhar                   │
       ├─────────────────────────────────────────┤
       │                                         │
282mm  ├─────────────────────────────────────────┤ ← Bottom Margin
       │                                         │
297mm  └─────────────────────────────────────────┘ ← A4 Height
```

### Page 1 Breakdown:
- Top Margin: 15mm
- Header: 45mm
- Photo & Info: 85mm
- Family: 25mm
- Address: 30mm
- Personal: 35mm
- Bottom Margin: 15mm
- **Total: ~250mm** (fits in 297mm A4)

---

## 📄 Page 2 Layout

### Vertical Layout:
```
0mm    ┌─────────────────────────────────────────┐
       │                                         │
15mm   ├─────────────────────────────────────────┤ ← Top Margin
       │ ACADEMIC DETAILS (35mm)                 │
       │ - Training Center                       │
       │ - College Name                          │
50mm   │ - UTR Number                            │
       ├─────────────────────────────────────────┤
       │                                         │
       │ DECLARATION (120mm)                     │
       │                                         │
       │ ┌─────────────────────────────────────┐ │
       │ │ I hereby declare that the           │ │
       │ │ information provided above is       │ │
       │ │ true and correct...                 │ │
       │ │                                     │ │
       │ │ Place: ___________                  │ │
       │ │ Date: ___________                   │ │
       │ │                                     │ │
       │ │                                     │ │
       │ │              Signature of Candidate │ │
       │ │              ┌──────────────────┐   │ │
       │ │              │ [SIGNATURE IMG]  │   │ │
170mm  │ │              │   (45x18mm)      │   │ │
       │ │              └──────────────────┘   │ │
       │ └─────────────────────────────────────┘ │
       ├─────────────────────────────────────────┤
       │                                         │
       │ FOOTER (20mm)                           │
190mm  │ Contact: dir-bbsr@nielit.gov.in        │
       ├─────────────────────────────────────────┤
       │                                         │
282mm  ├─────────────────────────────────────────┤ ← Bottom Margin
       │                                         │
297mm  └─────────────────────────────────────────┘ ← A4 Height
```

### Page 2 Breakdown:
- Top Margin: 15mm
- Academic: 35mm
- Declaration: 120mm
- Footer: 20mm
- Bottom Margin: 15mm
- **Total: ~205mm** (fits in 297mm A4)

---

## 🎨 Color Scheme

### Header:
```
┌─────────────────────────────────────────┐
│ ╔═══════════════════════════════════╗   │
│ ║ DEEP BLUE BACKGROUND              ║   │
│ ║ RGB: 13, 71, 161                  ║   │
│ ║ HEX: #0d47a1                      ║   │
│ ║                                   ║   │
│ ║ WHITE TEXT                        ║   │
│ ║ RGB: 255, 255, 255                ║   │
│ ╚═══════════════════════════════════╝   │
└─────────────────────────────────────────┘
```

### ID Badge:
```
┌──────────────────┐
│  STUDENT ID      │ ← Gold background (255, 193, 7)
├──────────────────┤
│ NIELIT/2025/     │ ← White background (255, 255, 255)
│ PPI/0002         │   Gold border (255, 193, 7)
└──────────────────┘
```

### Section Headers:
```
┌─────────────────────────────────────────┐
│ SECTION TITLE                           │ ← Deep Blue (13, 71, 161)
└─────────────────────────────────────────┘   White text
```

### Table Cells:
```
┌─────────────────┬─────────────────┐
│ FIELD LABEL     │ Field Value     │ ← Light Blue (227, 242, 253)
└─────────────────┴─────────────────┘   Black text
```

---

## 📏 Element Sizes

### Logo:
- Width: 30mm
- Height: 30mm
- Position: (20, 20)

### Photo:
- Width: 55mm
- Height: 65mm
- Border: 2mm rounded
- Color: Deep Blue

### Signature (in photo card):
- Width: 55mm
- Height: 14mm
- Border: 2mm rounded
- Color: Deep Blue

### Signature (at bottom):
- Width: 45mm
- Height: 18mm
- Position: Right aligned

### ID Badge:
- Width: 50mm
- Label Height: 4mm
- ID Box Height: 6mm
- Total Height: 10mm

---

## 🔤 Font Sizes

### Header:
- Title: 18pt Bold (CANDIDATE DETAILS)
- Organization: 10pt Regular
- Subtitle: 8pt Regular

### ID Badge:
- Label: 7pt Bold (STUDENT ID)
- ID Number: 8pt Bold

### Section Headers:
- 11pt Bold (Blue background)

### Field Labels:
- 8pt Bold (Light blue background)

### Field Values:
- 9pt Regular (White background)

### Declaration:
- 10pt Regular (Justified)

### Footer:
- 9pt Italic (Gray)

---

## 📊 Spacing Rules

### Section Headers:
- Height: 8mm
- Top margin: 4mm
- Bottom margin: 2mm

### Table Cells:
- Height: 7mm
- Padding: 1mm

### Gaps Between Sections:
- Small gap: 2mm
- Medium gap: 4mm
- Large gap: 8mm

### Margins:
- All sides: 15mm
- Border: 1mm thick (Deep Blue)

---

## ✅ Quality Checklist

### Header:
- [ ] Logo displays at 20mm from left
- [ ] Title starts at 55mm
- [ ] Title width is 80mm
- [ ] Gap from 135-140mm is empty
- [ ] ID badge starts at 140mm
- [ ] ID badge width is 50mm
- [ ] No overlap between title and badge

### Content:
- [ ] Photo is 55x65mm
- [ ] Signature is 55x14mm (card) or 45x18mm (bottom)
- [ ] All sections have proper spacing
- [ ] Table cells are 7mm height
- [ ] Section headers are 8mm height

### Pages:
- [ ] Page 1 ends after Personal Information
- [ ] Page 2 starts with Academic Details
- [ ] Total pages: Exactly 2
- [ ] No overflow to page 3

---

## 🎯 Critical Measurements

### Must Be Exact:
1. Title X position: 55mm ✅
2. Title width: 80mm ✅
3. ID badge X position: 140mm ✅
4. ID badge width: 50mm ✅
5. Gap between: 5mm (135-140mm) ✅

### Must Be Optimized:
1. Section headers: 8mm ✅
2. Table cells: 7mm ✅
3. Gaps: 2-4mm ✅
4. Photo card: 85mm total ✅

---

## 📐 A4 Paper Dimensions

```
┌─────────────────────────────────────────┐
│                                         │
│         210mm (Width)                   │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │                                   │  │
│  │                                   │  │
│  │                                   │  │
│  │         CONTENT AREA              │  │
│  │         180mm x 267mm             │  │
│  │                                   │  │
│  │                                   │  │
│  │                                   │  │
│  └───────────────────────────────────┘  │
│                                         │
│         297mm (Height)                  │
│                                         │
└─────────────────────────────────────────┘
```

### Usable Area:
- Width: 180mm (210 - 15 - 15)
- Height: 267mm (297 - 15 - 15)

---

**This is the exact layout implemented in the PDF!** ✅

