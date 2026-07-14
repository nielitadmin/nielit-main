/**
 * Aadhar card document check for workshop registration (browser-side).
 * Requires Aadhaar/UIDAI text via OCR — rejects marksheets, certificates, and portraits.
 */
(function (global) {
    'use strict';

    var STRONG_AADHAR_MARKERS = /aadhaar|aadhar|uidai|unique identification authority|आधार|aadhaar\s*no|aadhaar\s*number|your aadhaar/i;
    var REJECT_DOCUMENT_MARKERS = /marksheet|mark sheet|mark-sheet|certificate of|course certificate|school certificate|character certificate|board of secondary|board of education|higher secondary|matriculation|examination|exam roll|roll number|roll no|grade sheet|statement of marks|percentile|percentage obtained|cgpa|semester|provisional|odisha board|cbse|icse|chse|bse|university|college board|pass certificate|migration certificate|transfer certificate|bonafide|caste certificate|income certificate|domicile|birth certificate|driving licence|driving license|voter id|pan card|passport no/i;
    var MAX_MARGIN_RATIO = 0.10;
    var MIN_FILL_RATIO = 0.58;
    var ANALYSIS_MAX_DIM = 720;
    var OCR_MAX_DIM = 1600;

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

    function enhanceForOcr(ctx, width, height) {
        var imageData = ctx.getImageData(0, 0, width, height);
        var data = imageData.data;
        for (var i = 0; i < data.length; i += 4) {
            var gray = luminance(data[i], data[i + 1], data[i + 2]);
            gray = Math.max(0, Math.min(255, ((gray - 128) * 1.35) + 128));
            data[i] = gray;
            data[i + 1] = gray;
            data[i + 2] = gray;
        }
        ctx.putImageData(imageData, 0, 0);
    }

    function sampleCornerLuminance(data, width, height, sx, sy, cornerSize) {
        var total = 0;
        var count = 0;
        for (var y = sy; y < sy + cornerSize; y++) {
            for (var x = sx; x < sx + cornerSize; x++) {
                var idx = (y * width + x) * 4;
                total += luminance(data[idx], data[idx + 1], data[idx + 2]);
                count++;
            }
        }
        return count ? total / count : 0;
    }

    function columnCardRatio(data, width, height, x, lumThreshold) {
        var card = 0;
        for (var y = 0; y < height; y++) {
            var idx = (y * width + x) * 4;
            if (luminance(data[idx], data[idx + 1], data[idx + 2]) > lumThreshold) {
                card++;
            }
        }
        return card / height;
    }

    function rowCardRatio(data, width, height, y, lumThreshold) {
        var card = 0;
        var rowStart = y * width * 4;
        for (var x = 0; x < width; x++) {
            var idx = rowStart + x * 4;
            if (luminance(data[idx], data[idx + 1], data[idx + 2]) > lumThreshold) {
                card++;
            }
        }
        return card / width;
    }

    function validateDocumentFraming(img) {
        var prepared = drawScaledCanvas(img, ANALYSIS_MAX_DIM);
        if (!prepared) {
            return { valid: true, skipped: true };
        }

        var width = prepared.width;
        var height = prepared.height;
        var data = prepared.ctx.getImageData(0, 0, width, height).data;
        var cornerSize = Math.max(8, Math.round(Math.min(width, height) * 0.08));

        var bgLum = (
            sampleCornerLuminance(data, width, height, 0, 0, cornerSize) +
            sampleCornerLuminance(data, width, height, width - cornerSize, 0, cornerSize) +
            sampleCornerLuminance(data, width, height, 0, height - cornerSize, cornerSize) +
            sampleCornerLuminance(data, width, height, width - cornerSize, height - cornerSize, cornerSize)
        ) / 4;

        var lumThreshold = bgLum + 35;
        var lineThreshold = 0.68;

        var left = 0;
        var right = width - 1;
        var top = 0;
        var bottom = height - 1;

        for (var x = 0; x < width; x++) {
            if (columnCardRatio(data, width, height, x, lumThreshold) >= lineThreshold) {
                left = x;
                break;
            }
        }
        for (var x2 = width - 1; x2 >= 0; x2--) {
            if (columnCardRatio(data, width, height, x2, lumThreshold) >= lineThreshold) {
                right = x2;
                break;
            }
        }
        for (var y = 0; y < height; y++) {
            if (rowCardRatio(data, width, height, y, lumThreshold) >= lineThreshold) {
                top = y;
                break;
            }
        }
        for (var y2 = height - 1; y2 >= 0; y2--) {
            if (rowCardRatio(data, width, height, y2, lumThreshold) >= lineThreshold) {
                bottom = y2;
                break;
            }
        }

        if (left <= 2 && right >= width - 3 && top <= 2 && bottom >= height - 3) {
            return { valid: true, skipped: true };
        }

        var docW = Math.max(0, right - left + 1);
        var docH = Math.max(0, bottom - top + 1);
        if (docW < 10 || docH < 10) {
            return {
                valid: false,
                message: 'Could not detect the Aadhar card clearly. Retake the photo with the card filling most of the frame.'
            };
        }

        var fillRatio = (docW * docH) / (width * height);
        var maxMargin = Math.max(
            left / width,
            (width - 1 - right) / width,
            top / height,
            (height - 1 - bottom) / height
        );

        if (fillRatio < MIN_FILL_RATIO) {
            return {
                valid: false,
                message: 'Card is too small in the photo. Move closer so the Aadhar card fills most of the image (minimal background on the sides).'
            };
        }
        if (maxMargin > MAX_MARGIN_RATIO) {
            var sideHint = (height - 1 - bottom) / height > MAX_MARGIN_RATIO || top / height > MAX_MARGIN_RATIO
                ? 'top or bottom'
                : 'left or right';
            return {
                valid: false,
                message: 'Too much background on the ' + sideHint + '. Crop or retake so only the Aadhar card is visible — no bed sheet, table, or extra space around it.'
            };
        }

        return { valid: true };
    }

    function normalizeOcrText(text) {
        return (text || '')
            .replace(/\s+/g, ' ')
            .replace(/[|]/g, 'I')
            .toLowerCase()
            .trim();
    }

    function analyzeOcrText(text) {
        var normalized = normalizeOcrText(text);

        if (!normalized || normalized.length < 8) {
            return {
                valid: false,
                message: 'Could not read the document. Upload a sharper photo of your Aadhar card with Aadhaar / UIDAI text visible.'
            };
        }

        if (REJECT_DOCUMENT_MARKERS.test(normalized)) {
            return {
                valid: false,
                message: 'This is not an Aadhar card (looks like a marksheet, certificate, or other document). Upload your Aadhar card only.'
            };
        }

        if (STRONG_AADHAR_MARKERS.test(normalized)) {
            return {
                valid: true,
                message: 'Aadhar card verified — document text detected.'
            };
        }

        return {
            valid: false,
            message: 'This does not look like an Aadhar card. Upload a clear photo or scan showing Aadhaar / UIDAI text and the 12-digit Aadhaar number.'
        };
    }

    function cropCanvas(sourceCanvas, sx, sy, sw, sh) {
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, sw);
        canvas.height = Math.max(1, sh);
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return null;
        }
        ctx.drawImage(sourceCanvas, sx, sy, sw, sh, 0, 0, sw, sh);
        enhanceForOcr(ctx, sw, sh);
        return canvas;
    }

    function runOcrOnCanvas(canvas) {
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

    function ocrForAadharMarkers(img) {
        if (typeof Tesseract === 'undefined') {
            return Promise.resolve({
                valid: false,
                message: 'Document verification could not start. Check your internet connection and refresh the page.'
            });
        }

        var prepared = drawScaledCanvas(img, OCR_MAX_DIM);
        if (!prepared) {
            return Promise.resolve({
                valid: false,
                message: 'Could not process the image. Try another photo of your Aadhar card.'
            });
        }

        enhanceForOcr(prepared.ctx, prepared.width, prepared.height);
        var fullCanvas = prepared.canvas;

        var topH = Math.round(prepared.height * 0.28);
        var bottomH = Math.round(prepared.height * 0.32);
        var bottomY = prepared.height - bottomH;

        var topCanvas = cropCanvas(fullCanvas, 0, 0, prepared.width, topH);
        var bottomCanvas = cropCanvas(fullCanvas, 0, bottomY, prepared.width, bottomH);

        return runOcrOnCanvas(fullCanvas).then(function (fullText) {
            var result = analyzeOcrText(fullText);
            if (result.valid || REJECT_DOCUMENT_MARKERS.test(normalizeOcrText(fullText))) {
                return result;
            }
            return runOcrOnCanvas(topCanvas).then(function (topText) {
                result = analyzeOcrText(fullText + ' ' + topText);
                if (result.valid || REJECT_DOCUMENT_MARKERS.test(normalizeOcrText(fullText + ' ' + topText))) {
                    return result;
                }
                return runOcrOnCanvas(bottomCanvas).then(function (bottomText) {
                    return analyzeOcrText(fullText + ' ' + topText + ' ' + bottomText);
                });
            });
        });
    }

    function isPortraitPhoto(faces, imgWidth, imgHeight) {
        if (!faces || !faces.length || faces.length > 1) {
            return false;
        }
        var face = faces[0];
        var w = face.bottomRight[0] - face.topLeft[0];
        var h = face.bottomRight[1] - face.topLeft[1];
        var ratio = (w * h) / (imgWidth * imgHeight);
        if (ratio < 0.07) {
            return false;
        }
        var cx = ((face.topLeft[0] + face.bottomRight[0]) / 2) / imgWidth;
        var cy = ((face.topLeft[1] + face.bottomRight[1]) / 2) / imgHeight;
        return cx >= 0.2 && cx <= 0.8 && cy >= 0.12 && cy <= 0.78;
    }

    function detectPortraitWithBlazeFace(img) {
        if (typeof WorkshopPassportPhotoCheck === 'undefined') {
            return Promise.resolve(false);
        }
        return WorkshopPassportPhotoCheck.loadModel().then(function (model) {
            var prepared = drawScaledCanvas(img, 1280);
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

    function validateImage(img) {
        var w = img.naturalWidth;
        var h = img.naturalHeight;
        if (w < 320 || h < 200) {
            return Promise.resolve({
                valid: false,
                message: 'Aadhar card image is too small. Upload a clear scan or photo of the full card.'
            });
        }

        var framing = validateDocumentFraming(img);
        if (!framing.valid) {
            return Promise.resolve(framing);
        }

        return detectPortraitWithBlazeFace(img).then(function (portrait) {
            if (portrait) {
                return {
                    valid: false,
                    message: 'This looks like a personal photo. Upload a scan or photo of your Aadhar card document.'
                };
            }
            return ocrForAadharMarkers(img);
        });
    }

    global.WorkshopAadharCardCheck = {
        validate: function (file) {
            if (!file) {
                return Promise.resolve({ valid: false, message: 'Please select your Aadhar card file.' });
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
                    var result = analyzeOcrText(text);
                    if (result.valid) {
                        result.message = 'Aadhar card PDF verified — document text detected.';
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
                return Promise.resolve({ valid: false, message: 'Aadhar card must be JPG, PNG, or PDF.' });
            }

            return loadImageFromFile(file).then(validateImage);
        }
    };
})(window);
