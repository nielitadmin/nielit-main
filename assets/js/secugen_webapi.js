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
        return fetch(url, opts).finally(function () { clearTimeout(timer); });
    }

    function form(params) {
        return Object.keys(params).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
        }).join('&');
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

    function errorText(code) {
        code = parseInt(code, 10) || 0;
        var map = {
            2: 'Invalid parameter sent to the reader.',
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
            63: 'SecuGen WebAPI service (SGIBIOSRV) is not running. Start it, then refresh.'
        };
        if (map[code]) {
            return map[code];
        }
        if (code >= 10000) {
            return 'SecuGen WebAPI license error (code ' + code + '). Install a domain license key.';
        }
        return 'Fingerprint operation failed (code ' + code + ').';
    }

    function isoFrom(resp) {
        if (!resp) {
            return '';
        }
        var keys = ['TemplateBase64', 'BIRTemplateBase64', 'IsoTemplate', 'ISOTemplate'];
        for (var i = 0; i < keys.length; i++) {
            var v = String(resp[keys[i]] || '').trim();
            if (v.length > 40) {
                return v;
            }
        }
        return '';
    }

    function serialFrom(resp) {
        if (!resp) {
            return '';
        }
        var lower = {};
        Object.keys(resp).forEach(function (k) {
            lower[String(k).toLowerCase()] = resp[k];
        });
        var keys = ['serialnumber', 'serialno', 'deviceserial', 'sn', 'serial'];
        for (var i = 0; i < keys.length; i++) {
            var v = String(lower[keys[i]] || '').trim();
            if (v && v !== '0' && v.length >= 4) {
                return v;
            }
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

    function post(base, method, params, ms) {
        return fetchTimeout(base + method, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: form(params)
        }, ms || 20000).then(function (res) {
            return res.text().then(function (text) {
                var json = parseJson(text);
                if (!json) {
                    throw new Error('SecuGen WebAPI did not return JSON. Confirm SGIBIOSRV is running.');
                }
                return json;
            });
        });
    }

    function capture(base, quality, timeout) {
        base = base || bases()[0];
        quality = quality || 60;
        timeout = timeout || 10000;
        return post(base, 'SGIFPCapture', {
            Timeout: timeout,
            Quality: quality,
            TemplateFormat: 'ISO',
            licstr: LIC
        }, timeout + 8000).then(function (resp) {
            var code = parseInt(resp.ErrorCode, 10) || 0;
            if (code !== 0) {
                throw new Error(errorText(code));
            }
            var iso = isoFrom(resp);
            if (!iso) {
                throw new Error('The reader did not return an ISO template. Update the SecuGen WebAPI client.');
            }
            return {
                iso: iso,
                quality: parseInt(resp.ImageQuality || resp.Quality || '0', 10) || 0,
                device_id: serialFrom(resp),
                model: String(resp.Model || '').trim(),
                raw: resp
            };
        });
    }

    function match(base, probeIso, galleryIso) {
        base = base || bases()[0];
        return post(base, 'SGIMatchScore', {
            Template1: probeIso,
            Template2: galleryIso,
            TemplateFormat: 'ISO',
            licstr: LIC
        }, 15000).then(function (resp) {
            var code = parseInt(resp.ErrorCode, 10) || 0;
            if (code !== 0) {
                throw new Error(errorText(code));
            }
            var score = parseInt(resp.MatchingScore, 10) || 0;
            return {
                matched: score >= THRESHOLD,
                score: score,
                raw: resp
            };
        });
    }

    function isMatch(resp) {
        var code = parseInt(resp && resp.ErrorCode, 10) || 0;
        if (code !== 0) {
            return false;
        }
        return (parseInt(resp.MatchingScore, 10) || 0) >= THRESHOLD;
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
