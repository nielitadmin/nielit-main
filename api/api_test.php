<?php
/**
 * Student MIS API tester.
 * This page does not store or log the API key entered by the tester.
 */
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student MIS API Test</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; }
        body { margin: 0; background: #f4f7fb; color: #172033; }
        main { max-width: 920px; margin: 40px auto; padding: 0 20px; }
        .panel { background: #fff; border: 1px solid #dce3ee; border-radius: 12px; padding: 24px; box-shadow: 0 8px 24px rgba(23, 32, 51, .08); }
        h1 { margin-top: 0; font-size: 26px; }
        .muted { color: #65728a; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input { width: 100%; box-sizing: border-box; padding: 11px 12px; border: 1px solid #c8d2e1; border-radius: 7px; font: inherit; }
        .full { grid-column: 1 / -1; }
        button { margin-top: 18px; padding: 11px 18px; border: 0; border-radius: 7px; background: #1459c7; color: #fff; font-weight: 700; cursor: pointer; }
        button:disabled { opacity: .6; cursor: wait; }
        #result { margin-top: 20px; padding: 16px; background: #101827; color: #d9e5f5; border-radius: 8px; overflow: auto; white-space: pre-wrap; min-height: 80px; }
        .warning { margin-top: 16px; padding: 12px; background: #fff7df; border: 1px solid #f0d78a; border-radius: 7px; color: #654f00; }
        @media (max-width: 680px) { .grid { grid-template-columns: 1fr; } .full { grid-column: auto; } }
    </style>
</head>
<body>
<main>
    <section class="panel">
        <h1>Student MIS API Test</h1>
        <p class="muted">Tests the multi-course Student MIS GET endpoint. The key is used only in this browser request and is not stored.</p>
        <form id="apiTestForm">
            <div class="grid">
                <div class="full">
                    <label for="apiKey">X-API-Key</label>
                    <input id="apiKey" name="apiKey" type="password" autocomplete="off" required>
                </div>
                <div>
                    <label for="studentId">Student ID</label>
                    <input id="studentId" name="studentId" value="NIELIT/2026/BBSR/0185" placeholder="Optional ID search">
                </div>
                <div>
                    <label for="studentName">Student Name</label>
                    <input id="studentName" name="studentName" placeholder="Optional name search">
                </div>
                <div>
                    <label for="limit">Limit</label>
                    <input id="limit" name="limit" type="number" value="20" min="1" max="100">
                </div>
                <div>
                    <label for="photo">Capture Photo</label>
                    <input id="photo" name="photo" type="file" accept="image/*" capture="user">
                </div>
                <div class="full">
                    <img id="photoPreview" alt="Captured photo preview" hidden style="max-width: 180px; max-height: 180px; border-radius: 8px; border: 1px solid #c8d2e1;">
                </div>
                <div class="full" id="studentCardWrap" style="display:none;">
                    <div id="studentCard" style="display:flex;align-items:center;gap:18px;padding:16px;background:#f0f5ff;border:1px solid #c8d2e1;border-radius:10px;">
                        <img id="studentPhoto" alt="Student Photo" style="width:90px;height:110px;object-fit:cover;border-radius:8px;border:2px solid #1459c7;background:#dce3ee;">
                        <div>
                            <div id="studentName2" style="font-size:18px;font-weight:700;"></div>
                            <div id="studentId2" style="color:#65728a;font-size:13px;margin-top:3px;"></div>
                            <div id="studentCourse" style="color:#1459c7;font-size:13px;margin-top:3px;"></div>
                            <div id="studentDetails" style="color:#444;font-size:12px;margin-top:4px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <button id="testButton" type="submit">Test Student API</button>
        </form>
        <div class="warning">Use a read-only API key for normal profile and enrollment testing. The photo preview stays in this browser and is not uploaded or stored.</div>
        <pre id="result">Response will appear here.</pre>
    </section>
</main>
<script>
const form = document.getElementById('apiTestForm');
const button = document.getElementById('testButton');
const result = document.getElementById('result');
const photoInput = document.getElementById('photo');
const photoPreview = document.getElementById('photoPreview');

photoInput.addEventListener('change', () => {
    const file = photoInput.files[0];
    if (!file) {
        photoPreview.hidden = true;
        photoPreview.removeAttribute('src');
        return;
    }
    photoPreview.src = URL.createObjectURL(file);
    photoPreview.hidden = false;
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    button.disabled = true;
    result.textContent = 'Testing...';
    document.getElementById('studentCardWrap').style.display = 'none';

    const key = document.getElementById('apiKey').value.trim();
    const studentId = document.getElementById('studentId').value.trim();
    const studentName = document.getElementById('studentName').value.trim();
    const limit = document.getElementById('limit').value || '20';
    const searchQuery = studentId || studentName;
    if (!searchQuery) {
        result.textContent = JSON.stringify({ error: 'Enter a student ID or name.' }, null, 2);
        button.disabled = false;
        return;
    }
    const query = new URLSearchParams({ action: 'search', q: searchQuery, limit });

    try {
        const response = await fetch('v1/students.php?' + query.toString(), {
            method: 'GET',
            headers: { 'X-API-Key': key, 'Accept': 'application/json' }
        });
        const body = await response.json();

        // Show student photo card if results found
        const students = body.students || body.student ? [body.student] : [];
        const list = body.students ?? (body.student ? [body.student] : []);
        if (list.length > 0) {
            const s = list[0];
            const photoUrl = s.photo_url || null;
            const imgEl = document.getElementById('studentPhoto');
            if (photoUrl) {
                imgEl.src = photoUrl;
                imgEl.onerror = () => { imgEl.src = ''; imgEl.style.background = '#dce3ee'; imgEl.alt = 'No photo'; };
            } else {
                imgEl.removeAttribute('src');
                imgEl.alt = 'No photo';
            }
            document.getElementById('studentName2').textContent = s.name || '—';
            document.getElementById('studentId2').textContent = 'ID: ' + (s.student_id || '—');
            document.getElementById('studentCourse').textContent = s.course_name || s.course_code || '';
            document.getElementById('studentDetails').textContent = [
                s.gender ? s.gender : null,
                s.dob ? 'DOB: ' + s.dob : null,
                s.mobile ? '📞 ' + s.mobile : null
            ].filter(Boolean).join('  ·  ');
            document.getElementById('studentCardWrap').style.display = 'block';
        }

        result.textContent = JSON.stringify({
            http_status: response.status,
            search_query: searchQuery,
            captured_photo: photoInput.files.length > 0,
            response: body
        }, null, 2);
    } catch (error) {
        result.textContent = JSON.stringify({ error: error.message }, null, 2);
    } finally {
        button.disabled = false;
    }
});
</script>
</body>
</html>
