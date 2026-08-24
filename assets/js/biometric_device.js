/**
 * Unified fingerprint adapter — auto-detects whichever local reader service is
 * running on the kiosk PC and exposes one common API for the pages.
 *
 * Supported providers (probed in this order):
 *   1) SecuGen WebAPI / SGIBIOSRV  (Hamster Pro 20)   -> window.SecuGenWebApi
 *   2) Mantra MFS110/MFS100 Client Service            -> window.MantraMfs100
 *
 * Both underlying clients already share the same shape, so this layer just picks
 * the live one and forwards calls:
 *   discover()                         -> { base, provider, label } | null
 *   capture(base[, quality, timeout])  -> { iso, quality, raw }
 *   match(base, probeIso, galleryIso)  -> { matched, score, raw }
 *
 * Load order on the page: secugen_webapi.js, mantra_mfs100.js, then this file.
 */
(function (global) {
    'use strict';

    var PROVIDERS = [
        { key: 'secugen', label: 'SecuGen WebAPI', get: function () { return global.SecuGenWebApi; } },
        { key: 'mantra', label: 'Mantra MFS110 Client', get: function () { return global.MantraMfs100; } }
    ];

    var state = { api: null, base: '', provider: '', label: '' };

    function discover() {
        var seq = Promise.resolve(null);
        PROVIDERS.forEach(function (p) {
            seq = seq.then(function (found) {
                if (found) {
                    return found;
                }
                var api = p.get();
                if (!api || typeof api.discover !== 'function') {
                    return null;
                }
                return api.discover().then(function (res) {
                    if (res && res.base) {
                        return { api: api, base: res.base, provider: p.key, label: p.label };
                    }
                    return null;
                }).catch(function () {
                    return null;
                });
            });
        });
        return seq.then(function (found) {
            if (found) {
                state.api = found.api;
                state.base = found.base;
                state.provider = found.provider;
                state.label = found.label;
                return { base: found.base, provider: found.provider, label: found.label };
            }
            state = { api: null, base: '', provider: '', label: '' };
            // No matching-capable reader. If the Mantra *RD Service* is running
            // (Aadhaar-only, ports 11100-11120), report it distinctly so the page
            // can tell the user to install the MFS110 Client Service instead — the
            // RD Service cannot return a template for local 1:1 matching.
            if (global.MantraRd && typeof global.MantraRd.discover === 'function') {
                return global.MantraRd.discover().then(function (rd) {
                    if (rd && rd.origin) {
                        return { rdOnly: true, provider: 'mantra_rd', label: 'Mantra RD Service' };
                    }
                    return null;
                }).catch(function () {
                    return null;
                });
            }
            return null;
        });
    }

    function ensureApi() {
        if (!state.api) {
            throw new Error('No fingerprint reader detected on this PC.');
        }
        return state.api;
    }

    function capture(base, quality, timeout) {
        try {
            return ensureApi().capture(base || state.base, quality, timeout);
        } catch (e) {
            return Promise.reject(e);
        }
    }

    function match(base, probeIso, galleryIso) {
        try {
            return ensureApi().match(base || state.base, probeIso, galleryIso);
        } catch (e) {
            return Promise.reject(e);
        }
    }

    global.BiometricDevice = {
        discover: discover,
        capture: capture,
        match: match,
        get provider() { return state.provider; },
        get label() { return state.label; },
        get base() { return state.base; }
    };
})(window);
