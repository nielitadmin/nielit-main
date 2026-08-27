/**
 * SecuGen WebAPI (SGIBIOSRV) client for Hamster Pro 20 — ISO template capture and 1:1 match.
 * Runs in the browser on the kiosk PC where the SecuGen WebAPI service and reader are installed.
 *
 * Endpoints (default): https://localhost:8443/SGIFPCapture and .../SGIMatchScore
 * A page served over HTTPS can only reach the HTTPS (8443) service; HTTP (8000) is used
 * only when the portal itself is opened over HTTP (local XAMPP).
 */
(function (global) {
    'use strict';

    var LIC = String(global.SECUGEN_WEBAPI_LICSTR || '');
    var THRESHOLD = parseInt(global.SECUGEN_MATCH_THRESHOLD, 10);
    if (!THRESHOLD || THRESHOLD < 1) {
        THRESHOLD = 100; // 0-199 scale; 100 ≈ FAR 0.01%
    }

    function bases() {
        var https = ['https://localhost:8443/', 'https://127.0.0.1:8443/'];
        var http = ['http://localhost:8000/', 'http://127.0.0.1:8000/'];
        // Never call http:// from an https:// page (mixed content is blocked).
        if (String(location.protocol).toLowerCase() === 'https:') {
            return https;
        }
        return http.concat(https);
    }

    function fetchTimeout(url, options, ms) {
        var ctrl = new AbortController();
        var timer = setTimeout(function () { ctrl.abort(); }, ms);
        var opts = {};
        Object.keys(options || {}).forEach(function (k) { opts[k] = options[k]; });
        opts.signal = ctrl.signal;
        opts.cache = 'no-store';
        opts.mode = opts.mode || 'cors';
        if (!opts.targetAddressSpace) {
            opts.targetAddressSpace = 'loopback';
        }
        return fetch(url, opts).finally(function () { clearTimeout(timer); });
    }

    function parseJson(text) {
        text = String(text || '').trim();
        if (!text) {
            return null;
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            var start = text.indexOf('{');
            var end = text.lastIndexOf('}');
            if (start >= 0 && end > start) {
                try {
                    return JSON.parse(text.slice(start, end + 1));
                } catch (e2) {
                    return null;
                }
            }
            return null;
        }
    }

    function lowerMap(resp) {
        var out = {};
        if (!resp || typeof resp !== 'object') {
            return out;
        }
        Object.keys(resp).forEach(function (k) {
            out[String(k).toLowerCase()] = resp[k];
        });
        return out;
    }

    function field(resp, names) {
        var map = lowerMap(resp);
        for (var i = 0; i < names.length; i++) {
            var v = map[String(names[i]).toLowerCase()];
            if (v !== undefined && v !== null && String(v).trim() !== '') {
                return v;
            }
        }
        return '';
    }

    function errorText(code) {
        code = parseInt(code, 10) || 0;
        var map = {
            2: 'Invalid parameter sent to the reader.',
            3: 'Invalid parameters sent to the fingerprint sensor.',
            7: 'SecuGen algorithm DLL failed to load. Reinstall the SecuGen WebAPI client (SGIBIOSRV).',
            51: 'System file load failure.',
            52: 'Sensor chip initialization failed.',
            53: 'Reader not found. Plug in the SecuGen Hamster Pro 20 and try again.',
            54: 'Capture timed out. Place the finger firmly on the reader and try again.',
            55: 'No reader available. Check the USB connection.',
            56: 'Driver load failed. Reinstall the SecuGen driver.',
            57: 'Wrong image. Lift the finger and place it flat again.',
            58: 'Lack of USB bandwidth. Use a direct USB port (not a hub).',
            59: 'Reader is busy. Close other fingerprint apps and try again.',
            60: 'Could not read the reader serial number.',
            61: 'Unsupported reader.',
            63: 'SecuGen WebAPI service (SGIBIOSRV) is not running. Start it, then refresh.',
            101: 'Fingerprint has too few details. Press the thumb firmer and flatter, then try again.',
            105: 'Could not extract fingerprint features. Clean the reader and press the thumb firmly.',
            4000: 'Invalid parameter sent to SecuGen WebAPI.',
            10001: 'SecuGen WebAPI license error. Install a domain license key.',
            10002: 'SecuGen WebAPI license does not match this website domain.',
            10003: 'SecuGen WebAPI license expired. Install a domain license key.',
            10004: 'SecuGen WebAPI did not receive the page origin. Open this kiosk over https and retry.'
        };
        if (map[code]) {
            return map[code];
        }
        if (code >= 10000) {
            return 'SecuGen WebAPI license error (code ' + code + '). Install a domain license key.';
        }
        return 'Fingerprint operation failed (code ' + code + ').';
    }

    function looksLikeImage(v) {
        v = String(v || '').replace(/\s/g, '');
        return v.indexOf('Qk') === 0 || v.indexOf('/9j') === 0 || v.indexOf('iVBOR') === 0;
    }

    function isoFrom(resp) {
        if (!resp) {
            return '';
        }
        var named = [
            'TemplateBase64', 'templateBase64', 'BIRTemplateBase64',
            'IsoTemplate', 'ISOTemplate', 'ISOTemplateBase64', 'IsoTemplateBase64',
            'FPTemplate', 'FPTemplateBase64', 'MinTemplate'
        ];
        var v = String(field(resp, named) || '').replace(/\s/g, '');
        if (v.length > 40 && !looksLikeImage(v)) {
            return v;
        }
        var map = lowerMap(resp);
        var keys = Object.keys(map);
        var i;
        for (i = 0; i < keys.length; i++) {
            var name = keys[i];
            var val = String(map[name] || '').replace(/\s/g, '');
            if (val.length < 80 || looksLikeImage(val)) {
                continue;
            }
            // ISO 19794-2 FMR header is ASCII "FMR" → base64 "Rk1S"
            if (val.indexOf('Rk1S') === 0 || name.indexOf('template') !== -1) {
                return val;
            }
        }
        return '';
    }

    function serialFrom(resp) {
        var v = String(field(resp, ['SerialNumber', 'SerialNo', 'DeviceSerial', 'SN', 'Serial']) || '').trim();
        if (v && v !== '0' && v.length >= 4) {
            return v;
        }
        return '';
    }

    // Probe each base with a no-cors request; fetch resolves if the port is listening,
    // rejects if the connection is refused. Lets us pick the live base for capture/match.
    function discover() {
        var list = bases();
        var seq = Promise.resolve(null);
        list.forEach(function (base) {
            seq = seq.then(function (found) {
                if (found) {
                    return found;
                }
                return fetchTimeout(base + 'SGIFPCapture', { method: 'GET', mode: 'no-cors' }, 2500)
                    .then(function () { return { base: base }; })
                    .catch(function () { return null; });
            });
        });
        return seq;
    }

    function post(base, method, body, ms) {
        var url = String(base || '') + method;
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            var timer = setTimeout(function () {
                try { xhr.abort(); } catch (e) {}
                reject(new Error('SecuGen WebAPI timed out. Confirm SGIBIOSRV is running.'));
            }, ms || 20000);
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) {
                    return;
                }
                clearTimeout(timer);
                if (xhr.status === 404) {
                    reject(new Error('SecuGen WebAPI endpoint not found. Reinstall the WebAPI client.'));
                    return;
                }
                if (xhr.status === 0) {
                    reject(new Error('Cannot reach SecuGen WebAPI. Start SGIBIOSRV and open https://localhost:8443/SGIFPCapture once to accept the certificate.'));
                    return;
                }
                if (xhr.status !== 200) {
                    reject(new Error('SecuGen WebAPI HTTP ' + xhr.status + '.'));
                    return;
                }
                var json = parseJson(xhr.responseText);
                if (!json) {
                    reject(new Error('SecuGen WebAPI did not return JSON. Confirm SGIBIOSRV is running.'));
                    return;
                }
                resolve(json);
            };
            xhr.onerror = function () {
                clearTimeout(timer);
                reject(new Error('Cannot reach SecuGen WebAPI. Start SGIBIOSRV and accept the HTTPS certificate.'));
            };
            // Match the official SecuGen demo: POST form body, let the browser set Content-Type.
            xhr.open('POST', url, true);
            xhr.send(body);
        });
    }

    function capture(base, quality, timeout) {
        base = base || bases()[0];
        quality = quality || 50;
        timeout = timeout || 10000;
        // Exact parameter names/order from SecuGen Demo1.aspx. Duplicate PascalCase
        // keys (TemplateFormat + templateFormat) can make SGIBIOSRV skip the template.
        var body = 'Timeout=' + encodeURIComponent(String(timeout))
            + '&Quality=' + encodeURIComponent(String(quality))
            + '&licstr=' + encodeURIComponent(LIC)
            + '&templateFormat=ISO'
            + '&imageWSQRate=0.75';
        return post(base, 'SGIFPCapture', body, timeout + 8000).then(function (resp) {
            var code = parseInt(field(resp, ['ErrorCode', 'errorCode', 'ErrCode']), 10) || 0;
            if (code !== 0) {
                throw new Error(errorText(code));
            }
            var iso = isoFrom(resp);
            var qualityN = parseInt(field(resp, ['ImageQuality', 'Quality']), 10) || 0;
            var nfiq = parseInt(field(resp, ['NFIQ']), 10) || 0;
            if (!iso) {
                if (nfiq >= 4 || (qualityN > 0 && qualityN < 40)) {
                    throw new Error('Fingerprint image is too faint or smudged. Wipe the glass, press the thumb firmly and flat, then try again.');
                }
                throw new Error('The reader got an image but SecuGen did not extract a template. Restart SGIBIOSRV. If this website has been used with this reader for more than 60 days, install a SecuGen WebAPI license for this domain.');
            }
            return {
                iso: iso,
                quality: qualityN,
                device_id: serialFrom(resp),
                model: String(field(resp, ['Model']) || '').trim(),
                raw: resp
            };
        });
    }

    function match(base, probeIso, galleryIso) {
        base = base || bases()[0];
        var body = 'licstr=' + encodeURIComponent(LIC)
            + '&templateFormat=ISO'
            + '&Template1=' + encodeURIComponent(probeIso)
            + '&Template2=' + encodeURIComponent(galleryIso);
        return post(base, 'SGIMatchScore', body, 15000).then(function (resp) {
            var code = parseInt(field(resp, ['ErrorCode', 'errorCode', 'ErrCode']), 10) || 0;
            if (code !== 0) {
                throw new Error(errorText(code));
            }
            var score = parseInt(field(resp, ['MatchingScore', 'MatchScore', 'Score']), 10) || 0;
            return {
                matched: score >= THRESHOLD,
                score: score,
                raw: resp
            };
        });
    }

    function isMatch(resp) {
        var code = parseInt(field(resp, ['ErrorCode', 'errorCode', 'ErrCode']), 10) || 0;
        if (code !== 0) {
            return false;
        }
        return (parseInt(field(resp, ['MatchingScore', 'MatchScore', 'Score']), 10) || 0) >= THRESHOLD;
    }

    global.SecuGenWebApi = {
        discover: discover,
        capture: capture,
        match: match,
        isoFrom: isoFrom,
        isMatch: isMatch,
        threshold: THRESHOLD
    };
})(window);
