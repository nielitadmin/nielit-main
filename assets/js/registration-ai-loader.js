/**
 * Lazy-load heavy AI libraries (TensorFlow, BlazeFace, Tesseract, PDF.js).
 * Keeps first page load fast — especially on mobile.
 */
(function (global) {
    'use strict';

    var loadPromise = null;
    var loadResolved = false;
    var loadStarted = false;
    var config = {
        needFace: true,
        needOcr: true,
        needPdf: true
    };

    var URLS = {
        tf: 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js',
        blazeface: 'https://cdn.jsdelivr.net/npm/@tensorflow-models/blazeface/dist/blazeface.min.js',
        tesseract: 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js',
        pdfjs: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js',
        pdfWorker: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js'
    };

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[src="' + src + '"]');
            if (existing) {
                if (existing.getAttribute('data-loaded') === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', function () { reject(new Error('Failed to load script')); });
                return;
            }
            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = function () {
                script.setAttribute('data-loaded', '1');
                resolve();
            };
            script.onerror = function () {
                reject(new Error('Could not load verification library. Check internet connection.'));
            };
            document.head.appendChild(script);
        });
    }

    function dispatchEvent(name) {
        try {
            document.dispatchEvent(new CustomEvent(name));
        } catch (e) { /* IE fallback not needed */ }
    }

    function loadAll() {
        if (loadPromise) {
            return loadPromise;
        }

        loadStarted = true;
        loadResolved = false;
        dispatchEvent('registration-ai-load-start');

        loadPromise = Promise.resolve();

        if (config.needFace) {
            loadPromise = loadPromise
                .then(function () { return loadScript(URLS.tf); })
                .then(function () { return loadScript(URLS.blazeface); });
        }

        if (config.needOcr) {
            loadPromise = loadPromise.then(function () { return loadScript(URLS.tesseract); });
        }

        if (config.needPdf) {
            loadPromise = loadPromise.then(function () {
                return loadScript(URLS.pdfjs).then(function () {
                    if (typeof pdfjsLib !== 'undefined') {
                        pdfjsLib.GlobalWorkerOptions.workerSrc = URLS.pdfWorker;
                    }
                });
            });
        }

        loadPromise = loadPromise.then(function () {
            loadResolved = true;
            dispatchEvent('registration-ai-load-done');
        }).catch(function (err) {
            loadPromise = null;
            loadStarted = false;
            loadResolved = false;
            dispatchEvent('registration-ai-load-done');
            throw err;
        });

        return loadPromise;
    }

    global.RegistrationAiLoader = {
        configure: function (options) {
            if (!options) return;
            if (typeof options.needFace === 'boolean') config.needFace = options.needFace;
            if (typeof options.needOcr === 'boolean') config.needOcr = options.needOcr;
            if (typeof options.needPdf === 'boolean') config.needPdf = options.needPdf;
        },
        preload: function () {
            return loadAll().catch(function () { /* background preload */ });
        },
        ensureReady: function () {
            return loadAll();
        },
        isLoading: function () {
            return loadStarted && !loadResolved;
        },
        isReady: function () {
            return loadResolved;
        },
        isLoaded: function () {
            return loadResolved;
        }
    };
})(window);
