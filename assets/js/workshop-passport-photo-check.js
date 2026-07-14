/**
 * Passport photo face check for workshop registration (browser-side).
 * Requires tf.min.js + blazeface.min.js loaded first.
 */
(function (global) {
    'use strict';

    var modelPromise = null;
    var tfReadyPromise = null;
    var MAX_DETECT_DIM = 1280;

    function ensureTfReady() {
        if (tfReadyPromise) {
            return tfReadyPromise;
        }
        if (typeof tf === 'undefined') {
            return Promise.reject(new Error('TensorFlow.js did not load. Check your internet connection and refresh the page.'));
        }
        tfReadyPromise = (async function () {
            var backends = ['webgl', 'cpu'];
            for (var i = 0; i < backends.length; i++) {
                try {
                    var ok = await tf.setBackend(backends[i]);
                    if (ok) {
                        await tf.ready();
                        return;
                    }
                } catch (e) {
                    /* try next backend */
                }
            }
            await tf.ready();
        })();
        return tfReadyPromise;
    }

    function loadModel() {
        if (modelPromise) {
            return modelPromise;
        }
        if (typeof blazeface === 'undefined') {
            return Promise.reject(new Error('Face detection library did not load. Check your internet connection and refresh the page.'));
        }
        modelPromise = ensureTfReady().then(function () {
            return blazeface.load();
        });
        return modelPromise;
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

    function prepareDetectionSurface(img) {
        var w = img.naturalWidth || img.width;
        var h = img.naturalHeight || img.height;
        if (w <= MAX_DETECT_DIM && h <= MAX_DETECT_DIM) {
            return { surface: img, width: w, height: h };
        }
        var scale = MAX_DETECT_DIM / Math.max(w, h);
        var cw = Math.max(1, Math.round(w * scale));
        var ch = Math.max(1, Math.round(h * scale));
        var canvas = document.createElement('canvas');
        canvas.width = cw;
        canvas.height = ch;
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return { surface: img, width: w, height: h };
        }
        ctx.drawImage(img, 0, 0, cw, ch);
        return { surface: canvas, width: cw, height: ch };
    }

    function faceBoxArea(face) {
        var w = face.bottomRight[0] - face.topLeft[0];
        var h = face.bottomRight[1] - face.topLeft[1];
        return Math.max(0, w) * Math.max(0, h);
    }

    function faceCenter(face) {
        return {
            x: (face.topLeft[0] + face.bottomRight[0]) / 2,
            y: (face.topLeft[1] + face.bottomRight[1]) / 2
        };
    }

    function faceProbability(face) {
        if (typeof face.probability === 'number') {
            return face.probability;
        }
        if (Array.isArray(face.probability) && face.probability.length) {
            return face.probability[0];
        }
        return null;
    }

    function validatePassportFaceGeometry(faces, imgWidth, imgHeight) {
        if (!faces || faces.length === 0) {
            return {
                valid: false,
                message: 'No face detected. Upload a clear front-facing passport photo with your face fully visible.'
            };
        }
        if (faces.length > 1) {
            return {
                valid: false,
                message: 'Multiple faces detected. Upload a photo with only one person (passport-style).'
            };
        }

        var face = faces[0];
        var probability = faceProbability(face);
        if (probability !== null && probability < 0.65) {
            return {
                valid: false,
                message: 'Face is not clear enough. Use a brighter, front-facing photo.'
            };
        }

        var imgArea = imgWidth * imgHeight;
        var ratio = faceBoxArea(face) / imgArea;
        if (ratio < 0.06) {
            return {
                valid: false,
                message: 'Face is too small in the photo. Move closer or crop so your face is clearly visible.'
            };
        }
        if (ratio > 0.78) {
            return {
                valid: false,
                message: 'Face is too close or cropped. Upload a standard passport-style photo showing head and shoulders.'
            };
        }

        var center = faceCenter(face);
        var cx = center.x / imgWidth;
        var cy = center.y / imgHeight;
        if (cx < 0.18 || cx > 0.82) {
            return {
                valid: false,
                message: 'Face is not centred. Keep your face in the middle of the photo.'
            };
        }
        if (cy < 0.10 || cy > 0.78) {
            return {
                valid: false,
                message: 'Face position looks wrong (too high or too low). Use a straight front-facing passport photo.'
            };
        }

        var faceW = face.bottomRight[0] - face.topLeft[0];
        var faceH = face.bottomRight[1] - face.topLeft[1];
        if (face.topLeft[1] < imgHeight * 0.02 || face.bottomRight[1] > imgHeight * 0.98) {
            return {
                valid: false,
                message: 'Face appears cut off at the edge. Upload a complete passport-style photo.'
            };
        }
        if (faceW < 32 || faceH < 32) {
            return {
                valid: false,
                message: 'Photo resolution is too low. Upload a clearer, larger image.'
            };
        }

        return { valid: true, message: 'Face detected — photo looks acceptable.' };
    }

    function validateDimensions(img) {
        if (img.naturalWidth < 180 || img.naturalHeight < 180) {
            return {
                valid: false,
                message: 'Photo is too small. Minimum size is 180×180 pixels.'
            };
        }
        return { valid: true };
    }

    function isPdfFile(file) {
        return !!(file && (file.type === 'application/pdf' || (file.name || '').toLowerCase().endsWith('.pdf')));
    }

    function loadImageForValidation(file) {
        if (isPdfFile(file)) {
            if (typeof RegistrationPdfOcr === 'undefined') {
                return Promise.reject(new Error('PDF verification could not start. Refresh the page or upload JPG/PNG.'));
            }
            return RegistrationPdfOcr.loadFirstPageAsImage(file);
        }
        if (!file.type.startsWith('image/')) {
            return Promise.reject(new Error('Photo must be JPG, PNG, or PDF.'));
        }
        return loadImageFromFile(file);
    }

    global.WorkshopPassportPhotoCheck = {
        loadModel: loadModel,
        validate: function (file) {
            if (!file) {
                return Promise.resolve({ valid: false, message: 'Please select a passport photo file.' });
            }
            return loadImageForValidation(file).then(function (img) {
                var dim = validateDimensions(img);
                if (!dim.valid) {
                    return dim;
                }
                var prepared = prepareDetectionSurface(img);
                return loadModel().then(function (model) {
                    return model.estimateFaces(prepared.surface, false).then(function (faces) {
                        return validatePassportFaceGeometry(faces, prepared.width, prepared.height);
                    });
                });
            }).catch(function (err) {
                return {
                    valid: false,
                    message: (err && err.message) ? err.message : 'Could not verify the photo. Upload a clear front-facing passport photo and try again.'
                };
            });
        }
    };
})(window);
