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

    function capture(origin) {
        var url = String(origin).replace(/\/$/, '') + '/rd/capture';
        return fetchTimeout(url, {
            method: 'CAPTURE',
            headers: { 'Content-Type': 'text/xml' },
            body: PID_OPTIONS
        }, 25000).then(function (res) {
            return res.text();
        });
    }

    global.MantraRd = {
        discover: discoverRd,
        capture: capture
    };
})(window);
