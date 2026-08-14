/**
 * Mantra L1 / RD Service client for the attendance kiosk.
 * Runs only on the PC where the scanner is plugged in (localhost RD Service).
 */
(function (global) {
    'use strict';

    var PORT_MIN = 11100;
    var PORT_MAX = 11120;
    var PID_OPTIONS = '<?xml version="1.0"?><PidOptions ver="1.0"><Opts fCount="1" fType="0" iCount="0" pCount="0" format="0" pidVer="2.0" timeout="20000" posh="UNKNOWN" env="P"/></PidOptions>';

    function originsForPort(port) {
        return ['https://127.0.0.1:' + port, 'http://127.0.0.1:' + port];
    }

    function looksLikeRdXml(text) {
        return !!text && /RDService|rdsVer|status|DeviceInfo|PidData/i.test(text);
    }

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

    function probeOrigin(origin) {
        var attempts = [
            { method: 'RDSERVICE', url: origin + '/' },
            { method: 'GET', url: origin + '/' },
            { method: 'GET', url: origin + '/rd/info' }
        ];
        var seq = Promise.resolve(null);
        attempts.forEach(function (attempt) {
            seq = seq.then(function (found) {
                if (found) {
                    return found;
                }
                return fetchTimeout(attempt.url, { method: attempt.method }, 700).then(function (res) {
                    return res.text().then(function (text) {
                        if (looksLikeRdXml(text) || (res.ok && text)) {
                            return { origin: origin, infoXml: text };
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

    function discoverRd() {
        var jobs = [];
        var port;
        for (port = PORT_MIN; port <= PORT_MAX; port++) {
            originsForPort(port).forEach(function (origin) {
                jobs.push(probeOrigin(origin));
            });
        }
        return Promise.all(jobs).then(function (results) {
            var i;
            for (i = 0; i < results.length; i++) {
                if (results[i]) {
                    return results[i];
                }
            }
            return null;
        });
    }

    function xhrCapture(url, method, contentType) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open(method, url, true);
            xhr.timeout = 25000;
            if (contentType) {
                xhr.setRequestHeader('Content-Type', contentType);
            }
            xhr.onload = function () {
                if (xhr.responseText && looksLikeRdXml(xhr.responseText)) {
                    resolve(xhr.responseText);
                    return;
                }
                reject(new Error('RD returned an empty capture response.'));
            };
            xhr.onerror = function () {
                reject(new Error('Browser blocked reading the capture (CORS). The Mantra popup can still show Captured Success.'));
            };
            xhr.ontimeout = function () {
                reject(new Error('Fingerprint capture timed out.'));
            };
            xhr.send(PID_OPTIONS);
        });
    }

    function fetchCapture(url, method, contentType) {
        var headers = {};
        if (contentType) {
            headers['Content-Type'] = contentType;
        }
        return fetchTimeout(url, {
            method: method,
            headers: headers,
            body: PID_OPTIONS,
            targetAddressSpace: 'loopback'
        }, 25000).then(function (res) {
            return res.text();
        }).then(function (text) {
            if (looksLikeRdXml(text)) {
                return text;
            }
            throw new Error('RD returned an empty capture response.');
        });
    }

    function capture(origin) {
        var url = String(origin).replace(/\/$/, '') + '/rd/capture';
        return xhrCapture(url, 'CAPTURE', 'text/xml').catch(function (err) {
            var msg = (err && err.message) ? err.message : '';
            if (/CORS|blocked reading/i.test(msg)) {
                throw err;
            }
            return fetchCapture(url, 'CAPTURE', 'text/xml');
        });
    }

    function attr(node, name) {
        if (!node || !node.getAttribute) {
            return '';
        }
        return (node.getAttribute(name) || node.getAttribute(name.toLowerCase()) || '').trim();
    }

    function parsePidXml(xml) {
        var out = {
            err_code: '',
            err_info: '',
            q_score: '',
            nm_points: '',
            ts: '',
            dc: '',
            mi: '',
            rds_id: '',
            has_data: '0'
        };
        if (!xml) {
            return out;
        }
        var doc;
        try {
            doc = new DOMParser().parseFromString(xml, 'text/xml');
        } catch (e) {
            return out;
        }
        var resp = doc.querySelector('Resp') || doc.getElementsByTagName('Resp')[0];
        var dev = doc.querySelector('DeviceInfo') || doc.getElementsByTagName('DeviceInfo')[0];
        var data = doc.querySelector('Data') || doc.getElementsByTagName('Data')[0];
        out.err_code = attr(resp, 'errCode') || attr(resp, 'err_code');
        out.err_info = attr(resp, 'errInfo') || attr(resp, 'err_info');
        out.q_score = attr(resp, 'qScore') || attr(resp, 'q_score');
        out.nm_points = attr(resp, 'nmPoints') || attr(resp, 'nm_points');
        out.ts = attr(resp, 'ts');
        out.dc = attr(dev, 'dc');
        out.mi = attr(dev, 'mi');
        out.rds_id = attr(dev, 'rdsId') || attr(dev, 'rds_id');
        if (data && (data.textContent || '').trim() !== '') {
            out.has_data = '1';
        }
        return out;
    }

    function sha256Hex(text) {
        if (!global.crypto || !crypto.subtle) {
            return Promise.resolve('');
        }
        return crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(function (buf) {
            return Array.from(new Uint8Array(buf)).map(function (b) {
                return b.toString(16).padStart(2, '0');
            }).join('');
        }).catch(function () {
            return '';
        });
    }

    global.MantraRd = {
        discover: discoverRd,
        capture: capture,
        parsePid: parsePidXml,
        sha256: sha256Hex
    };
})(window);
