/**
 * Marksheet / certificate check for registration uploads.
 */
(function (global) {
    'use strict';

    var MARKSHEET_MARKERS = /marksheet|mark sheet|mark-sheet|certificate|board of|examination|exam|matriculation|secondary school|higher secondary|class[\s-]*x\b|class[\s-]*xii|class[\s-]*12|10th|10[\s\/\-]*th|tenth|12th|12[\s\/\-]*th|twelfth|diploma|statement of marks|grade sheet|roll no|roll number|percentile|percentage|marks obtained|chse|cbse|icse|bse|odisha board|pass certificate|school leaving|course certificate/i;
    var AADHAR_MARKERS = /aadhaar|aadhar|uidai|unique identification authority|आधार/i;
    var ANALYSIS_MAX_DIM = 720;
    var OCR_MAX_DIM = 1600;

    function framingLimits() {
        var cfg = global.RegistrationAiConfig;
        return cfg && cfg.framingThresholds ? cfg.framingThresholds() : { minFillRatio: 0.58, maxMarginRatio: 0.10 };
    }

    function minDocumentSize() {
        var cfg = global.RegistrationAiConfig;
        if (cfg && cfg.isLenient && cfg.isLenient()) {
            return {
                width: cfg.minDocumentWidth || 240,
                height: cfg.minDocumentHeight || 160
            };
        }
        return { width: 320, height: 200 };
    }

    function loadImageFromFile(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('Could not read the image file.'));
            };
            img.src = url;
        });
    }

    function drawScaledCanvas(img, maxDim) {
        var w = img.naturalWidth || img.width;
        var h = img.naturalHeight || img.height;
        var scale = Math.min(1, maxDim / Math.max(w, h));
        var cw = Math.max(1, Math.round(w * scale));
        var ch = Math.max(1, Math.round(h * scale));
        var canvas = document.createElement('canvas');
        canvas.width = cw;
        canvas.height = ch;
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) {
            return null;
        }
        ctx.drawImage(img, 0, 0, cw, ch);
        return { canvas: canvas, ctx: ctx, width: cw, height: ch };
    }

    function luminance(r, g, b) {
        return 0.299 * r + 0.587 * g + 0.114 * b;
    }

    function sampleCorner(data, width, height, sx, sy, size) {
        var total = 0;
        var count = 0;
        for (var y = sy; y < sy + size; y++) {
            for (var x = sx; x < sx + size; x++) {
                var idx = (y * width + x) * 4;
                total += luminance(data[idx], data[idx + 1], data[idx + 2]);
                count++;
            }
        }
        return count ? total / count : 0;
    }

    /**
     * Find document bounds using contrast from corner background (brighter OR darker).
     * Old "bright-only" logic falsely rejected cream certificates on light tables.
     */
    function findDocumentBounds(data, width, height, bgLum, contrast) {
        contrast = contrast || 22;
        function isDocPixel(r, g, b) {
            return Math.abs(luminance(r, g, b) - bgLum) >= contrast;
        }
        function colRatio(x) {
            var c = 0;
            for (var y = 0; y < height; y++) {
                var idx = (y * width + x) * 4;
                if (isDocPixel(data[idx], data[idx + 1], data[idx + 2])) c++;
            }
            return c / height;
        }
        function rowRatio(y) {
            var c = 0;
            var rs = y * width * 4;
            for (var x = 0; x < width; x++) {
                var idx = rs + x * 4;
                if (isDocPixel(data[idx], data[idx + 1], data[idx + 2])) c++;
            }
            return c / width;
        }

        var lineThreshold = 0.45;
        var left = 0;
        var right = width - 1;
        var top = 0;
        var bottom = height - 1;
        for (var x = 0; x < width; x++) {
            if (colRatio(x) >= lineThreshold) { left = x; break; }
        }
        for (var x2 = width - 1; x2 >= 0; x2--) {
            if (colRatio(x2) >= lineThreshold) { right = x2; break; }
        }
        for (var y = 0; y < height; y++) {
            if (rowRatio(y) >= lineThreshold) { top = y; break; }
        }
        for (var y2 = height - 1; y2 >= 0; y2--) {
            if (rowRatio(y2) >= lineThreshold) { bottom = y2; break; }
        }

        if (left <= 2 && right >= width - 3 && top <= 2 && bottom >= height - 3) {
            return { skipped: true };
        }

        var docW = Math.max(0, right - left + 1);
        var docH = Math.max(0, bottom - top + 1);
        if (docW < 20 || docH < 20) {
            return { skipped: true };
        }

        return {
            docW: docW,
            docH: docH,
            fillRatio: (docW * docH) / (width * height),
            maxMargin: Math.max(left / width, (width - 1 - right) / width, top / height, (height - 1 - bottom) / height)
        };
    }

    function validateDocumentFraming(img) {
        var prepared = drawScaledCanvas(img, ANALYSIS_MAX_DIM);
        if (!prepared) {
            return { valid: true, skipped: true };
        }

        var width = prepared.width;
        var height = prepared.height;
        var data = prepared.ctx.getImageData(0, 0, width, height).data;
        // Smaller corners so thin margins around a full-page scan are not mixed into bg sample
        var cornerSize = Math.max(6, Math.round(Math.min(width, height) * 0.045));
        var bgLum = (
            sampleCorner(data, width, height, 0, 0, cornerSize) +
            sampleCorner(data, width, height, width - cornerSize, 0, cornerSize) +
            sampleCorner(data, width, height, 0, height - cornerSize, cornerSize) +
            sampleCorner(data, width, height, width - cornerSize, height - cornerSize, cornerSize)
        ) / 4;

        var bounds = findDocumentBounds(data, width, height, bgLum, 22);
        if (bounds.skipped) {
            return { valid: true, skipped: true };
        }

        var limits = framingLimits();
        if (bounds.fillRatio < limits.minFillRatio) {
            return {
                valid: false,
                soft: true,
                message: 'Document is too small in the photo. Move closer so the marksheet/certificate fills most of the image.'
            };
        }
        if (bounds.maxMargin > limits.maxMarginRatio) {
            return {
                valid: false,
                soft: true,
                message: 'Too much background around the document. Crop or retake so only the marksheet/certificate is visible.'
            };
        }
        return { valid: true };
    }

    function enhanceForOcr(ctx, width, height) {
        var imageData = ctx.getImageData(0, 0, width, height);
        var data = imageData.data;
        for (var i = 0; i < data.length; i += 4) {
            var gray = luminance(data[i], data[i + 1], data[i + 2]);
            gray = Math.max(0, Math.min(255, ((gray - 128) * 1.35) + 128));
            data[i] = data[i + 1] = data[i + 2] = gray;
        }
        ctx.putImageData(imageData, 0, 0);
    }

    function normalizeText(text) {
        return (text || '').replace(/\s+/g, ' ').toLowerCase().trim();
    }

    function analyzeMarksheetText(text, level) {
        var normalized = normalizeText(text);
        if (!normalized || normalized.length < 8) {
            if (global.RegistrationAiConfig && global.RegistrationAiConfig.shouldAcceptUnreadableOcr(normalized, AADHAR_MARKERS)) {
                return global.RegistrationAiConfig.lenientOcrAccept('Marksheet/certificate');
            }
            return {
                valid: false,
                message: 'Could not read the document. Upload a clearer photo of your marksheet or certificate.'
            };
        }
        if (AADHAR_MARKERS.test(normalized)) {
            return {
                valid: false,
                message: 'This looks like an Aadhar card. Upload your ' + (level === 'twelfth' ? '12th' : '10th') + ' marksheet or certificate instead.'
            };
        }
        if (MARKSHEET_MARKERS.test(normalized)) {
            return {
                valid: true,
                message: 'Marksheet/certificate verified — document accepted.'
            };
        }
        if (global.RegistrationAiConfig && global.RegistrationAiConfig.shouldAcceptUnreadableOcr(normalized, AADHAR_MARKERS)) {
            return global.RegistrationAiConfig.lenientOcrAccept('Marksheet/certificate');
        }
        return {
            valid: false,
            message: 'This does not look like a marksheet or certificate. Upload a clear photo showing board name, marks, or certificate details.'
        };
    }

    function runOcr(canvas) {
        if (typeof Tesseract === 'undefined' || !canvas) {
            return Promise.resolve('');
        }
        return Tesseract.recognize(canvas, 'eng', { logger: function () {} }).then(function (result) {
            return (result && result.data && result.data.text) ? result.data.text : '';
        }).catch(function () {
            return '';
        });
    }

    function ocrMarksheetImage(img, level) {
        if (typeof Tesseract === 'undefined') {
            return Promise.resolve({
                valid: false,
                message: 'Document verification could not start. Check your internet connection and refresh the page.'
            });
        }

        var prepared = drawScaledCanvas(img, OCR_MAX_DIM);
        if (!prepared) {
            return Promise.resolve({ valid: false, message: 'Could not process the image.' });
        }

        enhanceForOcr(prepared.ctx, prepared.width, prepared.height);

        var topH = Math.round(prepared.height * 0.32);
        var topCanvas = document.createElement('canvas');
        topCanvas.width = prepared.width;
        topCanvas.height = topH;
        var topCtx = topCanvas.getContext('2d');
        if (topCtx) {
            topCtx.drawImage(prepared.canvas, 0, 0, prepared.width, topH, 0, 0, prepared.width, topH);
            enhanceForOcr(topCtx, prepared.width, topH);
        }

        return runOcr(prepared.canvas).then(function (fullText) {
            var result = analyzeMarksheetText(fullText, level);
            if (result.valid || AADHAR_MARKERS.test(normalizeText(fullText))) {
                return result;
            }
            if (!topCtx) {
                return result;
            }
            return runOcr(topCanvas).then(function (topText) {
                return analyzeMarksheetText(fullText + ' ' + topText, level);
            });
        });
    }

    function isPortraitPhoto(faces, w, h) {
        if (!faces || faces.length !== 1) return false;
        var face = faces[0];
        var fw = face.bottomRight[0] - face.topLeft[0];
        var fh = face.bottomRight[1] - face.topLeft[1];
        var ratio = (fw * fh) / (w * h);
        if (ratio < 0.07) return false;
        var cx = ((face.topLeft[0] + face.bottomRight[0]) / 2) / w;
        var cy = ((face.topLeft[1] + face.bottomRight[1]) / 2) / h;
        return cx >= 0.2 && cx <= 0.8 && cy >= 0.12 && cy <= 0.78;
    }

    function detectPortrait(img) {
        if (typeof WorkshopPassportPhotoCheck === 'undefined') {
            return Promise.resolve(false);
        }
        return WorkshopPassportPhotoCheck.loadModel().then(function (model) {
            var prepared = drawScaledCanvas(img, 1280);
            if (!prepared) return false;
            return model.estimateFaces(prepared.canvas, false).then(function (faces) {
                return isPortraitPhoto(faces, prepared.width, prepared.height);
            });
        }).catch(function () {
            return false;
        });
    }

    global.RegistrationMarksheetCheck = {
        validate: function (file, options) {
            options = options || {};
            var level = options.level === 'twelfth' ? 'twelfth' : 'tenth';
            var levelLabel = level === 'twelfth' ? '12th' : '10th';

            if (!file) {
                return Promise.resolve({ valid: false, message: 'Please select a marksheet or certificate file.' });
            }

            var isPdf = file.type === 'application/pdf' || (file.name || '').toLowerCase().endsWith('.pdf');
            if (isPdf) {
                if (typeof RegistrationPdfOcr === 'undefined') {
                    return Promise.resolve({
                        valid: false,
                        message: 'PDF verification could not start. Refresh the page or upload JPG/PNG.'
                    });
                }
                return RegistrationPdfOcr.extractText(file, 2).then(function (text) {
                    var result = analyzeMarksheetText(text, level);
                    if (result.valid) {
                        result.message = levelLabel + ' marksheet PDF verified — document text detected.';
                    } else if (result.message.indexOf('Could not read') !== -1) {
                        result.message = 'Could not read the PDF. Upload a clearer scan or use JPG/PNG.';
                    }
                    return result;
                }).catch(function (err) {
                    return {
                        valid: false,
                        message: (err && err.message) ? err.message : 'Could not verify the PDF. Upload a clearer file or use JPG/PNG.'
                    };
                });
            }

            if (!file.type.startsWith('image/')) {
                return Promise.resolve({ valid: false, message: 'Marksheet must be JPG, PNG, or PDF.' });
            }

            return loadImageFromFile(file).then(function (img) {
                var minSize = minDocumentSize();
                if (img.naturalWidth < minSize.width || img.naturalHeight < minSize.height) {
                    return {
                        valid: false,
                        message: 'Image is too small. Upload a clear photo of the full marksheet or certificate.'
                    };
                }

                var framing = validateDocumentFraming(img);
                // Soft framing failure: still OCR — accept if document text is verified
                var framingSoftFail = framing && framing.valid === false && framing.soft;

                var skipPortrait = global.RegistrationAiConfig &&
                    global.RegistrationAiConfig.skipPortraitRejectionOnDocuments &&
                    global.RegistrationAiConfig.isLenient &&
                    global.RegistrationAiConfig.isLenient();

                function afterPortraitCheck() {
                    return ocrMarksheetImage(img, level).then(function (ocrResult) {
                        if (ocrResult && ocrResult.valid) {
                            return ocrResult;
                        }
                        if (framingSoftFail) {
                            return framing;
                        }
                        return ocrResult;
                    });
                }

                if (!framing.valid && !framingSoftFail) {
                    return framing;
                }

                if (skipPortrait) {
                    return afterPortraitCheck();
                }

                return detectPortrait(img).then(function (portrait) {
                    if (portrait) {
                        return {
                            valid: false,
                            message: 'This looks like a personal photo. Upload your ' + levelLabel + ' marksheet or certificate document.'
                        };
                    }
                    return afterPortraitCheck();
                });
            });
        }
    };
})(window);
