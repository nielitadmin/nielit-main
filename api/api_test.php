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
                    <input id="studentId" name="studentId" value="NIELIT/2026/BBSR/0185" required>
                </div>
                <div>
                    <label for="limit">Limit</label>
                    <input id="limit" name="limit" type="number" value="20" min="1" max="100">
                </div>
            </div>
            <button id="testButton" type="submit">Test Student API</button>
        </form>
        <div class="warning">Use a read-only API key for normal profile and enrollment testing. Do not paste an admin key into a shared computer.</div>
        <pre id="result">Response will appear here.</pre>
    </section>
</main>
<script>
const form = document.getElementById('apiTestForm');
const button = document.getElementById('testButton');
const result = document.getElementById('result');

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    button.disabled = true;
    result.textContent = 'Testing...';

    const key = document.getElementById('apiKey').value.trim();
    const studentId = document.getElementById('studentId').value.trim();
    const limit = document.getElementById('limit').value || '20';
    const query = new URLSearchParams({ action: 'search', q: studentId, limit });

    try {
        const response = await fetch('v1/students.php?' + query.toString(), {
            method: 'GET',
            headers: { 'X-API-Key': key, 'Accept': 'application/json' }
        });
        const body = await response.json();
        result.textContent = JSON.stringify({ http_status: response.status, response: body }, null, 2);
    } catch (error) {
        result.textContent = JSON.stringify({ error: error.message }, null, 2);
    } finally {
        button.disabled = false;
    }
});
</script>
</body>
</html>
