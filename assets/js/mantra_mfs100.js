/**
 * Mantra MFS100 Client Service (ISO template capture / 1:1 match).
 * Runs on the PC where the scanner and MFS100 Client Service are installed.
 */
(function (global) {
    'use strict';

    var BASES = [
        'https://127.0.0.1:8003/mfs100/',
        'http://127.0.0.1:8004/mfs100/',
        'http://127.0.0.1:8003/mfs100/',
        'https://127.0.0.1:8004/mfs100/'
    ];

    function fetchTimeout(url, options, ms) {
        var ctrl = new AbortController();
        var timer = setTimeout(function () { ctrl.abort(); }, ms);
        var opts = {};
        Object.keys(options || {}).forEach(function (k) { opts[k] = options[k]; });
        opts.signal = ctrl.signal;
        opts.cache = 'no-store';
        opts.mode = 'cors';
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

    function looksReady(text, json) {
        if (json && (json.ErrorCode !== undefined || json.DeviceInfo || json.IsoTemplate)) {
            return true;
        }
        return /ErrorCode|DeviceInfo|MFS100|IsoTemplate/i.test(String(text || ''));
    }

    function isoFrom(resp) {
        if (!resp) {
            return '';
        }
        var keys = ['IsoTemplate', 'ISOTemplate', 'isoTemplate', 'AnsiTemplate', 'ANSITemplate'];
        for (var i = 0; i < keys.length; i++) {
            var v = String(resp[keys[i]] || '').trim();
            if (v.length > 40) {
                return v;
            }
        }
        return '';
    }

    function errorCode(resp) {
        return String((resp && (resp.ErrorCode || resp.errorCode)) || '').trim();
    }

    function isMatch(resp) {
        if (!resp) {
            return false;
        }
        var err = errorCode(resp);
        if (err && err !== '0') {
            return false;
        }
        if (Object.prototype.hasOwnProperty.call(resp, 'Status')) {
            return resp.Status === true || resp.Status === 1 || resp.Status === '1' || resp.Status === 'true';
        }
        var score = parseInt(resp.MatchScore || resp.Score || '0', 10) || 0;
        return score >= 14000 || (score >= 40 && score <= 100);
    }

    function postJson(base, method, body, ms) {
        return fetchTimeout(base + method, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json; charset=utf-8', 'Accept': 'application/json' },
            body: JSON.stringify(body || {})
        }, ms || 25000).then(function (res) {
            return res.text().then(function (text) {
                var json = parseJson(text);
                if (!json) {
                    throw new Error('MFS100 Client Service did not return JSON.');
                }
                return json;
            });
        });
    }

    function discover() {
        var seq = Promise.resolve(null);
        BASES.forEach(function (base) {
            seq = seq.then(function (found) {
                if (found) {
                    return found;
                }
                return fetchTimeout(base + 'info?Key=info', { method: 'GET' }, 2500).then(function (res) {
                    return res.text().then(function (text) {
                        var json = parseJson(text);
                        if (looksReady(text, json)) {
                            return { base: base };
                        }
                        return null;
                    });
                }).catch(function () {
                    return null;
                });
            });
        });
        return seq;
    }

    function capture(base, quality, timeout) {
        quality = quality || 55;
        timeout = timeout || 15;
        return postJson(base, 'capture', { Quality: quality, TimeOut: timeout }, (timeout + 8) * 1000).then(function (resp) {
            var err = errorCode(resp);
            if (err && err !== '0') {
                var msg = String(resp.ErrorDescription || '');
                if (!msg) {
                    msg = err === '-1140' ? 'Capture timed out. Place the thumb firmly and try again.' : ('Fingerprint capture failed (code ' + err + ').');
                }
                throw new Error(msg);
            }
            var iso = isoFrom(resp);
            if (!iso) {
                throw new Error('Device did not return an ISO template. Install MFS100 Client Service (not only RD Service).');
            }
            return { iso: iso, quality: parseInt(resp.Quality || '0', 10) || 0, raw: resp };
        });
    }

    function match(base, probeIso, galleryIso) {
        return postJson(base, 'match', {
            ProbTemplate: probeIso,
            GalleryTemplate: galleryIso,
            BioType: 'FMR'
        }, 12000).then(function (resp) {
            var err = errorCode(resp);
            if (err && err !== '0') {
                throw new Error(String(resp.ErrorDescription || 'Fingerprint match failed.'));
            }
            return {
                matched: isMatch(resp),
                score: parseInt(resp.MatchScore || resp.Score || '0', 10) || 0,
                raw: resp
            };
        });
    }

    global.MantraMfs100 = {
        discover: discover,
        capture: capture,
        match: match,
        isoFrom: isoFrom,
        isMatch: isMatch
    };
})(window);
