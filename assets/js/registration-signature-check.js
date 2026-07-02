/**
 * Signature image check for registration uploads.
 */
(function (global) {
    'use strict';

    var STRONG_DOCUMENT_MARKERS = /aadhaar|aadhar|uidai|marksheet|mark sheet|unique identification authority|board of secondary|board of education|examination roll|roll number|statement of marks|matriculation certificate/i;

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
                reject(new Error('Could not read the signature image.'));
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

        function sample(x, y) {
            var idx = (y * width + x) * 4;
            rs += data[idx];
            gs += data[idx + 1];
            bs += data[idx + 2];
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

        return {
            r: rs / count,
            g: gs / count,
            b: bs / count,
            lum: luminance(rs / count, gs / count, bs / count)
        };
    }

    function analyzeInkContent(img) {
        var prepared = drawScaledCanvas(img, 900);
        if (!prepared) {
            return { valid: false, message: 'Could not process the signature image.' };
        }

        var width = prepared.width;
        var height = prepared.height;
        var data = prepared.ctx.getImageData(0, 0, width, height).data;
        var cornerSize = Math.max(6, Math.round(Math.min(width, height) * 0.1));
        var bg = sampleCornerBackground(data, width, height, cornerSize);

        var ink = 0;
        var total = width * height;
        var sumLum = 0;

        for (var i = 0; i < data.length; i += 4) {
            var r = data[i];
            var g = data[i + 1];
            var b = data[i + 2];
            var lum = luminance(r, g, b);
            sumLum += lum;

            var dist = colorDistance(r, g, b, bg.r, bg.g, bg.b);
            var darkerThanBg = lum < (bg.lum - 25);
            var differentFromBg = dist > 24;

            if (differentFromBg && darkerThanBg && lum < 215) {
                ink++;
            }
        }

        var inkRatio = ink / total;
        var avgLum = sumLum / total;

        if (bg.lum < 110 || avgLum < 100) {
            return {
                valid: false,
                message: 'Background is too dark. Sign on white paper and upload a clear signature photo.'
            };
        }
        if (inkRatio < 0.001) {
            return {
                valid: false,
                message: 'Signature not detected. Upload a clear photo of your handwritten signature on white paper.'
            };
        }
        if (inkRatio > 0.45) {
            return {
                valid: false,
                message: 'This looks like a full document or photo, not a signature. Upload only your signature on white paper.'
            };
        }

        return { valid: true, inkRatio: inkRatio };
    }

    function isPortraitPhoto(faces, w, h) {
        if (!faces || faces.length !== 1) {
            return false;
        }
        var face = faces[0];
        var fw = face.bottomRight[0] - face.topLeft[0];
        var fh = face.bottomRight[1] - face.topLeft[1];
        var ratio = (fw * fh) / (w * h);
        if (ratio < 0.08) {
            return false;
        }
        var cx = ((face.topLeft[0] + face.bottomRight[0]) / 2) / w;
        var cy = ((face.topLeft[1] + face.bottomRight[1]) / 2) / h;
        return cx >= 0.18 && cx <= 0.82 && cy >= 0.10 && cy <= 0.80;
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

    function ocrCheck(img) {
        if (typeof Tesseract === 'undefined') {
            return Promise.resolve({ valid: true });
        }
        var prepared = drawScaledCanvas(img, 1000);
        if (!prepared) {
            return Promise.resolve({ valid: true });
        }
        return Tesseract.recognize(prepared.canvas, 'eng', { logger: function () {} }).then(function (result) {
            var text = normalizeText((result && result.data && result.data.text) ? result.data.text : '');
            if (STRONG_DOCUMENT_MARKERS.test(text)) {
                return {
                    valid: false,
                    message: 'This looks like an Aadhar card or marksheet. Upload only your signature image.'
                };
            }
            if (text.length > 350) {
                return {
                    valid: false,
                    message: 'Too much text detected. Upload a signature image only — not a full document.'
                };
            }
            return { valid: true };
        }).catch(function () {
            return { valid: true };
        });
    }

    function normalizeText(text) {
        return (text || '').replace(/\s+/g, ' ').toLowerCase().trim();
    }

    global.RegistrationSignatureCheck = {
        validate: function (file) {
            if (!file) {
                return Promise.resolve({ valid: false, message: 'Please select your signature image.' });
            }
            if (!file.type.startsWith('image/')) {
                return Promise.resolve({ valid: false, message: 'Signature must be a JPG or PNG image.' });
            }

            return loadImageFromFile(file).then(function (img) {
                if (img.naturalWidth < 80 || img.naturalHeight < 40) {
                    return {
                        valid: false,
                        message: 'Signature image is too small. Upload a clearer signature photo.'
                    };
                }

                var ink = analyzeInkContent(img);
                if (!ink.valid) {
                    return ink;
                }

                return detectPortrait(img).then(function (portrait) {
                    if (portrait) {
                        return {
                            valid: false,
                            message: 'This looks like a photo, not a signature. Upload your handwritten signature on white paper.'
                        };
                    }
                    return ocrCheck(img).then(function (ocrResult) {
                        if (!ocrResult.valid) {
                            return ocrResult;
                        }
                        return {
                            valid: true,
                            message: 'Signature verified — image accepted.'
                        };
                    });
                });
            }).catch(function (err) {
                return {
                    valid: false,
                    message: (err && err.message) ? err.message : 'Could not verify the signature image. Try again with a clear photo on white paper.'
                };
            });
        }
    };
})(window);
