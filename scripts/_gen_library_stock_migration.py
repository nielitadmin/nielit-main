import openpyxl, re
wb = openpyxl.load_workbook(r'C:\Users\USER\Downloads\Library Stock_4.xlsx', read_only=True, data_only=True)
ws = wb['Sheet1']
rows = list(ws.iter_rows(min_row=2, values_only=True))


def norm(x):
    return '' if x is None else str(x).strip()


def expand(spec):
    s = norm(spec).upper().replace('\u2013', '-')
    m = re.fullmatch(r'(\d+)\s*(?:TO|-)\s*(\d+)', s)
    if m:
        a, b = m.group(1), m.group(2)
        w = len(a)
        return [str(n).zfill(w) for n in range(int(a), int(b) + 1)]
    return [p.strip() for p in re.split(r'[,\s]+', s) if p.strip() != '']


def ndate(d):
    d = norm(d)
    m = re.fullmatch(r'(\d{1,2})\.(\d{1,2})\.(\d{2,4})', d)
    if not m:
        return ''
    dd, mm, yy = m.groups()
    if len(yy) == 2:
        yy = '20' + yy
    return f"{yy}-{int(mm):02d}-{int(dd):02d}"


def nprice(p):
    p = norm(p)
    m = re.match(r'(\d+)', p)
    return m.group(1) if m else ''


def esc(s):
    return norm(s).replace('\\', '\\\\').replace("'", "\\'")


books = []
for r in rows:
    acc = norm(r[1])
    title = norm(r[2])
    if acc == '' or title == '':
        continue
    accs = expand(acc)
    books.append({
        'accs': accs,
        'title': esc(r[2]),
        'publisher': esc(r[3]),
        'author': esc(r[4]),
        'edition': esc(r[6]),
        'date': ndate(r[7]),
        'price': nprice(r[8]),
    })

total = sum(len(b['accs']) for b in books)
print('titles', len(books), 'copies', total)

lines = []
for b in books:
    accs = "['" + "','".join(b['accs']) + "']"
    lines.append(
        "    ['title'=>'%s','author'=>'%s','publisher'=>'%s','edition'=>'%s','purchase_date'=>'%s','price'=>'%s','accs'=>%s],"
        % (b['title'], b['author'], b['publisher'], b['edition'], b['date'], b['price'], accs))
data_php = "\n".join(lines)

php = r'''<?php
/**
 * Import "Library Stock_4.xlsx" into library_books.
 *
 * Source workbook: Library Stock_4.xlsx (Sheet1) -- 293 title rows expanded
 * into 561 individual copies (one library_books row per accession number).
 *
 * Usage (CLI):
 *   php import_library_stock_4.php install [CENTRE_ID]
 *   php import_library_stock_4.php verify  [CENTRE_ID]
 *   php import_library_stock_4.php rollback [CENTRE_ID]
 *
 * Usage (browser):
 *   /migrations/import_library_stock_4.php?action=install&centre_id=1
 *
 * Centre resolution order:
 *   1) CENTRE_ID passed as argument / ?centre_id=
 *   2) $FORCE_CENTRE_ID variable below (set it if you want to hard-code)
 *   3) If exactly one active centre exists, that one is used automatically
 *   4) Otherwise the script lists active centres and stops so you can pick one.
 *
 * Every inserted row is tagged with created_by = 'migration:library_stock_4'
 * so it can be safely re-run (idempotent) and rolled back.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/library_helper.php';

// Optional: hard-code a centre id here instead of passing it each time.
$FORCE_CENTRE_ID = 0;

const IMPORT_TAG = 'migration:library_stock_4';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.\n");
}

function out($m) {
    if (php_sapi_name() === 'cli') { echo $m . PHP_EOL; }
    else { echo htmlspecialchars($m) . "<br>\n"; }
}

function resolveCentreId(mysqli $conn, int $forced): int
{
    if ($forced > 0) { return $forced; }
    $res = $conn->query("SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY id ASC");
    $centres = [];
    if ($res) { while ($row = $res->fetch_assoc()) { $centres[] = $row; } }
    if (count($centres) === 1) {
        out("Auto-selected the only active centre: #" . $centres[0]['id'] . ' ' . $centres[0]['name']);
        return (int) $centres[0]['id'];
    }
    out("No centre id supplied. Active centres:");
    foreach ($centres as $c) {
        out("  id=" . $c['id'] . "  code=" . ($c['code'] ?? '') . "  name=" . $c['name']);
    }
    out("Re-run with the desired centre id, e.g.:  php import_library_stock_4.php install <CENTRE_ID>");
    return 0;
}

function books(): array
{
    return [
%DATA%
    ];
}

function doInstall(mysqli $conn, int $centreId): bool
{
    ensureLibraryTables($conn);

    $stmt = $conn->prepare(
        'INSERT INTO library_books
            (accession_no, title, author, publisher, edition, purchase_date, price, source, status, created_by, centre_id)
         VALUES (?, ?, ?, ?, ?, NULLIF(?, \'\'), NULLIF(?, 0), \'Purchased\', \'available\', \'' . IMPORT_TAG . '\', ?)
         ON DUPLICATE KEY UPDATE
            title = VALUES(title), author = VALUES(author), publisher = VALUES(publisher),
            edition = VALUES(edition), purchase_date = VALUES(purchase_date), price = VALUES(price)'
    );
    if (!$stmt) { out('Prepare failed: ' . $conn->error); return false; }

    $inserted = 0; $updated = 0; $failed = 0;
    $conn->begin_transaction();
    foreach (books() as $b) {
        $priceVal = ($b['price'] === '') ? 0.0 : (float) $b['price'];
        foreach ($b['accs'] as $acc) {
            $acc = strtoupper(trim($acc));
            $stmt->bind_param(
                'sssssdsi',
                $acc, $b['title'], $b['author'], $b['publisher'], $b['edition'],
                $b['purchase_date'], $priceVal, $centreId
            );
            if ($stmt->execute()) {
                if ($stmt->affected_rows === 1) { $inserted++; }
                elseif ($stmt->affected_rows === 2) { $updated++; }
            } else {
                $failed++; out('  Failed acc ' . $acc . ': ' . $stmt->error);
            }
        }
    }
    $conn->commit();
    $stmt->close();
    out("Done. Inserted: $inserted, Updated: $updated, Failed: $failed (centre #$centreId).");
    return $failed === 0;
}

function doVerify(mysqli $conn, int $centreId): void
{
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM library_books WHERE created_by = ? AND centre_id = ?");
    $tag = IMPORT_TAG;
    $stmt->bind_param('si', $tag, $centreId);
    $stmt->execute();
    $c = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    out("Imported rows for centre #$centreId: $c (expected 561).");
}

function doRollback(mysqli $conn, int $centreId): void
{
    $stmt = $conn->prepare("DELETE FROM library_books WHERE created_by = ? AND centre_id = ?");
    $tag = IMPORT_TAG;
    $stmt->bind_param('si', $tag, $centreId);
    $stmt->execute();
    out("Rolled back " . $stmt->affected_rows . " rows for centre #$centreId.");
    $stmt->close();
}

$action = 'install';
$argCentre = 0;
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'install';
    $argCentre = (int) ($argv[2] ?? 0);
} else {
    $action = $_GET['action'] ?? 'install';
    $argCentre = (int) ($_GET['centre_id'] ?? 0);
}

$centreId = resolveCentreId($conn, $argCentre > 0 ? $argCentre : $FORCE_CENTRE_ID);
if ($centreId <= 0) { exit; }

switch ($action) {
    case 'verify':   doVerify($conn, $centreId); break;
    case 'rollback': doRollback($conn, $centreId); break;
    case 'install':
    default:         doInstall($conn, $centreId); break;
}
'''
php = php.replace('%DATA%', data_php)
path = r'c:\xampp\htdocs\public_html\migrations\import_library_stock_4.php'
with open(path, 'w', encoding='utf-8') as f:
    f.write(php)
print('wrote', path, len(php), 'bytes')
