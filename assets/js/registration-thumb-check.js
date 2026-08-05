/**
 * Left thumb impression image check for registration uploads.
 */
(function (global) {
    'use strict';

    var DOCUMENT_MARKERS = /aadhaar|aadhar|uidai|passbook|pass book|bank|account|savings|deposit|branch|ifsc|micr|cheque|check|marksheet|mark sheet|certificate|unique identification|examination|roll number|pan card|income tax|voter|driving licence|driving license|government of|republic of/i;

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
                reject(new Error('Could not read the thumb impression image.'));
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

    function colorDistance(r1, g1, b1, r2, g2, b2) {
        return Math.sqrt(
            (r1 - r2) * (r1 - r2) +
            (g1 - g2) * (g1 - g2) +
            (b1 - b2) * (b1 - b2)
        );
    }

    function sampleCornerBackground(data, width, height, cornerSize) {
        var rs = 0;
        var gs = 0;
        var bs = 0;
        var count = 0;
        var lumSamples = [];

        function sample(x, y) {
            var idx = (y * width + x) * 4;
            var r = data[idx];
            var g = data[idx + 1];
            var b = data[idx + 2];
            rs += r;
            gs += g;
            bs += b;
            lumSamples.push(luminance(r, g, b));
            count++;
        }

        for (var y = 0; y < cornerSize; y++) {
            for (var x = 0; x < cornerSize; x++) {
                sample(x, y);
                sample(width - 1 - x, y);
                sample(x, height - 1 - y);
                sample(width - 1 - x, height - 1 - y);
            }
        }

        var avgLum = lumSamples.reduce(function (a, b) { return a + b; }, 0) / lumSamples.length;
        var variance = 0;
        lumSamples.forEach(function (lum) {
            variance += (lum - avgLum) * (lum - avgLum);
        });
        variance = variance / lumSamples.length;

        return {
            r: rs / count,
            g: gs / count,
            b: bs / count,
            lum: avgLum,
            variance: variance
        };
    }

    function isInkPixel(r, g, b, bg) {
        var lum = luminance(r, g, b);
        var dist = colorDistance(r, g, b, bg.r, bg.g, bg.b);
        var maxCh = Math.max(r, g, b);
        var minCh = Math.min(r, g, b);
        var saturation = maxCh > 0 ? (maxCh - minCh) / maxCh : 0;

        var darkInk = dist > 18 && lum < (bg.lum - 12) && lum < 230;
        var coloredInk = saturation > 0.08 && dist > 20 && lum < (bg.lum + 12) && lum < 240;

        return darkInk || coloredInk;
    }

    function analyzeThumbImage(img) {
        var prepared = drawScaledCanvas(img, 900);
        if (!prepared) {
            return { valid: false, message: 'Could not process the thumb impression image.' };
        }

        var width = prepared.width;
        var height = prepared.height;
        var data = prepared.ctx.getImageData(0, 0, width, height).data;
        var cornerSize = Math.max(6, Math.round(Math.min(width, height) * 0.10));
        var bg = sampleCornerBackground(data, width, height, cornerSize);

        var ink = 0;
        var cornerInk = 0;
        var cornerTotal = 0;
        var centerInk = 0;
        var centerTotal = 0;
        var total = width * height;

        var cx0 = Math.round(width * 0.18);
        var cx1 = Math.round(width * 0.82);
        var cy0 = Math.round(height * 0.18);
        var cy1 = Math.round(height * 0.82);

        for (var y = 0; y < height; y++) {
            for (var x = 0; x < width; x++) {
                var idx = (y * width + x) * 4;
                var r = data[idx];
                var g = data[idx + 1];
                var b = data[idx + 2];
                var inkPixel = isInkPixel(r, g, b, bg);

                if (inkPixel) {
                    ink++;
                }

                var inCorner = (x < cornerSize || x >= width - cornerSize ||
                    y < cornerSize || y >= height - cornerSize);
                if (inCorner) {
                    cornerTotal++;
                    if (inkPixel) {
                        cornerInk++;
                    }
                }

                if (x >= cx0 && x <= cx1 && y >= cy0 && y <= cy1) {
                    centerTotal++;
                    if (inkPixel) {
                        centerInk++;
                    }
                }
            }
        }

        var inkRatio = ink / total;
        var cornerInkRatio = cornerTotal ? cornerInk / cornerTotal : 0;
        var centerInkRatio = centerTotal ? centerInk / centerTotal : 0;
        var centerShare = ink > 0 ? centerInk / ink : 0;
        var isCloseUpPrint = centerInkRatio >= 0.22 && centerShare >= 0.55;
        var isLightPaper = bg.lum >= 155;

        if (bg.lum < 115) {
            return {
                valid: false,
                message: 'Background is too dark. Use plain white paper for the thumb impression.'
            };
        }

        if (!isLightPaper && bg.variance > 1400) {
            return {
                valid: false,
                message: 'Use plain white paper for the thumb impression. Coloured bedsheets or tablecloths are not accepted.'
            };
        }

        if (!isCloseUpPrint && cornerInkRatio > 0.22 && centerInkRatio < 0.06) {
            return {
                valid: false,
                message: 'Too much content around the edges. Photograph your thumb impression on clean white paper.'
            };
        }

        if (inkRatio < 0.003) {
            return {
                valid: false,
                message: 'Thumb impression not detected. Press your left thumb on white paper with ink/pad and upload a clear photo.'
            };
        }

        if (!isCloseUpPrint && inkRatio > 0.55) {
            return {
                valid: false,
                message: 'This looks like a document or photo, not a thumb impression. Upload only your thumb print on white paper.'
            };
        }

        if (!isCloseUpPrint && centerInkRatio < 0.008 && inkRatio > 0.10) {
            return {
                valid: false,
                message: 'This looks like a full document with text, not a thumb impression.'
            };
        }

        if (inkRatio > 0.88) {
            return {
                valid: false,
                message: 'Image is too dark or unclear. Upload a thumb impression on white paper with visible ridge lines.'
            };
        }

        return {
            valid: true,
            inkRatio: inkRatio,
            centerInkRatio: centerInkRatio,
            isCloseUpPrint: isCloseUpPrint
        };
    }

    function isPortraitPhoto(faces, w, h) {
        if (!faces || faces.length !== 1) {
            return false;
        }
        var face = faces[0];
        var fw = face.bottomRight[0] - face.topLeft[0];
        var fh = face.bottomRight[1] - face.topLeft[1];
        var ratio = (fw * fh) / (w * h);
        if (ratio < 0.06) {
            return false;
        }
        var cx = ((face.topLeft[0] + face.bottomRight[0]) / 2) / w;
        var cy = ((face.topLeft[1] + face.bottomRight[1]) / 2) / h;
        return cx >= 0.15 && cx <= 0.85 && cy >= 0.08 && cy <= 0.85;
    }

    function detectPortrait(img) {
        if (typeof WorkshopPassportPhotoCheck === 'undefined') {
            return Promise.resolve(false);
        }
        return WorkshopPassportPhotoCheck.loadModel().then(function (model) {
            var prepared = drawScaledCanvas(img, 960);
            if (!prepared) {
                return false;
            }
            return model.estimateFaces(prepared.canvas, false).then(function (faces) {
                return isPortraitPhoto(faces, prepared.width, prepared.height);
            });
        }).catch(function () {
            return false;
        });
    }

    function normalizeText(text) {
        return (text || '').replace(/\s+/g, ' ').toLowerCase().trim();
    }

    function ocrCheck(img) {
        if (typeof Tesseract === 'undefined') {
            return Promise.resolve({ valid: true, textLength: 0 });
        }
        var prepared = drawScaledCanvas(img, 1000);
        if (!prepared) {
            return Promise.resolve({ valid: true, textLength: 0 });
        }
        return Tesseract.recognize(prepared.canvas, 'eng', { logger: function () {} }).then(function (result) {
            var text = normalizeText((result && result.data && result.data.text) ? result.data.text : '');
            if (DOCUMENT_MARKERS.test(text)) {
                return {
                    valid: false,
                    textLength: text.length,
                    message: 'This looks like an ID card, passbook, or certificate — not a thumb impression. Upload your thumb print on white paper only.'
                };
            }
            if (text.length > 80) {
                return {
                    valid: false,
                    textLength: text.length,
                    message: 'Too much text detected. Upload a thumb impression on white paper, not a document photo.'
                };
            }
            return { valid: true, textLength: text.length };
        }).catch(function () {
            return { valid: true, textLength: 0 };
        });
    }

    function isPdfFile(file) {
        return !!(file && (file.type === 'application/pdf' || (file.name || '').toLowerCase().endsWith('.pdf')));
    }

    function loadThumbImage(file) {
        if (isPdfFile(file)) {
            if (typeof RegistrationPdfOcr === 'undefined') {
                return Promise.reject(new Error('PDF verification could not start. Refresh the page or upload JPG/PNG.'));
            }
            return RegistrationPdfOcr.loadFirstPageAsImage(file);
        }
        if (!file.type.startsWith('image/')) {
            return Promise.reject(new Error('Thumb impression must be JPG, PNG, or PDF.'));
        }
        return loadImageFromFile(file);
    }

    function minThumbSize() {
        var cfg = global.RegistrationAiConfig;
        if (cfg && cfg.isLenient && cfg.isLenient()) {
            return {
                width: cfg.minThumbWidth || 48,
                height: cfg.minThumbHeight || 48,
                area: cfg.minThumbArea || 2500
            };
        }
        return { width: 80, height: 80, area: 6400 };
    }

    function isThumbTooSmall(img) {
        var w = img.naturalWidth || img.width || 0;
        var h = img.naturalHeight || img.height || 0;
        var limits = minThumbSize();
        if (w < limits.width || h < limits.height) {
            return true;
        }
        return (w * h) < limits.area;
    }

    global.RegistrationThumbCheck = {
        validate: function (file) {
            if (!file) {
                return Promise.resolve({ valid: true });
            }

            return loadThumbImage(file).then(function (img) {
                if (isThumbTooSmall(img)) {
                    return {
                        valid: false,
                        message: 'Image is too small. Upload a clearer photo of your thumb impression.'
                    };
                }

                var analysis = analyzeThumbImage(img);
                if (!analysis.valid) {
                    return analysis;
                }

                var skipPortrait = global.RegistrationAiConfig &&
                    global.RegistrationAiConfig.isLenient &&
                    global.RegistrationAiConfig.isLenient();

                function finishOk() {
                    return ocrCheck(img).then(function (ocrResult) {
                        if (!ocrResult.valid) {
                            return ocrResult;
                        }
                        return {
                            valid: true,
                            message: 'Thumb impression verified — image accepted.'
                        };
                    });
                }

                if (skipPortrait) {
                    return finishOk();
                }

                return detectPortrait(img).then(function (portrait) {
                    if (portrait) {
                        return {
                            valid: false,
                            message: 'This looks like a person\'s photo, not a thumb impression. Upload your thumb print on white paper.'
                        };
                    }
                    return finishOk();
                });
            }).catch(function (err) {
                return {
                    valid: false,
                    message: (err && err.message) ? err.message : 'Could not verify the thumb impression. Try again with a clear photo on white paper.'
                };
            });
        }
    };
})(window);

