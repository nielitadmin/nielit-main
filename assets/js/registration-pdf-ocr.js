/**
 * Render PDF pages in the browser and OCR them with Tesseract.
 * Requires pdfjs-dist (pdf.min.js) and Tesseract.js loaded first.
 */
(function (global) {
    'use strict';

    var PDFJS_WORKER = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
    var RENDER_MAX_DIM = 1800;

    function ensurePdfJs() {
        if (typeof pdfjsLib === 'undefined') {
            return Promise.reject(new Error('PDF library did not load. Refresh the page or upload JPG/PNG instead.'));
        }
        if (!pdfjsLib.GlobalWorkerOptions.workerSrc) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER;
        }
        return Promise.resolve();
    }

    function enhanceForOcr(ctx, width, height) {
        var imageData = ctx.getImageData(0, 0, width, height);
        var data = imageData.data;
        for (var i = 0; i < data.length; i += 4) {
            var gray = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
            gray = Math.max(0, Math.min(255, ((gray - 128) * 1.35) + 128));
            data[i] = data[i + 1] = data[i + 2] = gray;
        }
        ctx.putImageData(imageData, 0, 0);
    }

    function runTesseract(canvas) {
        if (typeof Tesseract === 'undefined' || !canvas) {
            return Promise.resolve('');
        }
        return Tesseract.recognize(canvas, 'eng', {
            logger: function () { /* quiet */ }
        }).then(function (result) {
            return (result && result.data && result.data.text) ? result.data.text : '';
        }).catch(function () {
            return '';
        });
    }

    function renderPageToCanvas(pdf, pageNumber) {
        return pdf.getPage(pageNumber).then(function (page) {
            var baseViewport = page.getViewport({ scale: 1 });
            var scale = RENDER_MAX_DIM / Math.max(baseViewport.width, baseViewport.height);
            scale = Math.max(1.2, Math.min(scale, 2.5));
            var viewport = page.getViewport({ scale: scale });
            var canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            var ctx = canvas.getContext('2d');
            if (!ctx) {
                return Promise.reject(new Error('Could not render the PDF page.'));
            }
            return page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function () {
                enhanceForOcr(ctx, canvas.width, canvas.height);
                return canvas;
            });
        });
    }

    function ocrPdfFile(file, maxPages) {
        maxPages = maxPages || 2;
        return ensurePdfJs().then(function () {
            return file.arrayBuffer();
        }).then(function (buffer) {
            return pdfjsLib.getDocument({ data: buffer }).promise;
        }).then(function (pdf) {
            var pagesToRead = Math.min(maxPages, pdf.numPages);
            var combined = '';

            function readPage(pageNum) {
                if (pageNum > pagesToRead) {
                    return combined;
                }
                return renderPageToCanvas(pdf, pageNum).then(function (canvas) {
                    return runTesseract(canvas).then(function (text) {
                        combined += ' ' + text;
                        return readPage(pageNum + 1);
                    });
                });
            }

            return readPage(1);
        });
    }

    function isPdfFile(file) {
        return !!(file && (file.type === 'application/pdf' || (file.name || '').toLowerCase().endsWith('.pdf')));
    }

    function canvasToImage(canvas) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = function () { reject(new Error('Could not convert the PDF page to an image.')); };
            img.src = canvas.toDataURL('image/png');
        });
    }

    function loadFirstPageAsImage(file) {
        if (!isPdfFile(file)) {
            return Promise.reject(new Error('File is not a PDF.'));
        }
        return ensurePdfJs().then(function () {
            return file.arrayBuffer();
        }).then(function (buffer) {
            return pdfjsLib.getDocument({ data: buffer }).promise;
        }).then(function (pdf) {
            if (!pdf.numPages) {
                return Promise.reject(new Error('The PDF has no pages.'));
            }
            return renderPageToCanvas(pdf, 1);
        }).then(canvasToImage);
    }

    global.RegistrationPdfOcr = {
        isPdf: isPdfFile,
        extractText: function (file, maxPages) {
            if (!file) {
                return Promise.reject(new Error('No PDF file provided.'));
            }
            if (!isPdfFile(file)) {
                return Promise.reject(new Error('File is not a PDF.'));
            }
            return ocrPdfFile(file, maxPages).then(function (text) {
                return (text || '').replace(/\s+/g, ' ').trim();
            });
        },
        loadFirstPageAsImage: loadFirstPageAsImage
    };
})(window);
