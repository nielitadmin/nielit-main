/**
 * Shared document upload checks for registration forms.
 */
(function (global) {
    'use strict';

    function getStatusEl(input, className) {
        var parent = input.parentElement;
        if (!parent) {
            return null;
        }
        var el = parent.querySelector('.' + className);
        if (!el) {
            el = document.createElement('div');
            el.className = className;
            el.setAttribute('aria-live', 'polite');
            parent.appendChild(el);
        }
        return el;
    }

    function setStatus(input, className, message, type) {
        var el = getStatusEl(input, className);
        if (!el) {
            return;
        }
        el.className = className + (type ? ' ' + type : '');
        if (!message) {
            el.innerHTML = '';
            return;
        }
        var icon = type === 'ok' ? 'check-circle' : (type === 'fail' ? 'times-circle' : 'spinner fa-spin');
        el.innerHTML = '<i class="fas fa-' + icon + ' me-1"></i>' + message;
    }

    function clearPreview(input) {
        var preview = input.parentElement && input.parentElement.querySelector('.file-preview');
        if (preview) {
            preview.classList.remove('show');
            preview.innerHTML = '';
        }
    }

    function resetFlags(input) {
        input.dataset.faceValid = '0';
        input.dataset.cardValid = '0';
        input.dataset.marksheetValid = '0';
        input.dataset.signatureValid = '0';
        input.dataset.certificateValid = '0';
        input.dataset.thumbValid = '0';
        input.classList.remove('is-valid');
    }

    function ensureAiReady() {
        if (typeof global.RegistrationAiLoader !== 'undefined') {
            return global.RegistrationAiLoader.ensureReady();
        }
        return Promise.resolve();
    }

    function runAsyncCheck(input, deps, options) {
        deps = deps || {};
        options = options || {};
        resetFlags(input);
        input.dataset[options.flagName] = '0';

        var file = input.files[0];
        if (!file) {
            clearPreview(input);
            global.RegistrationDocumentChecks.clearStatuses(input);
            if (deps.clearFileError) deps.clearFileError(input);
            return;
        }

        if (deps.clearFileError) deps.clearFileError(input);
        clearPreview(input);

        var validation = deps.validateFileInput ? deps.validateFileInput(input) : { valid: true };
        if (!validation.valid) {
            input.value = '';
            if (deps.displayFileError) deps.displayFileError(input, validation.message);
            global.RegistrationDocumentChecks.setDocCheckStatus(input, validation.message, 'fail');
            return;
        }

        global.RegistrationDocumentChecks.setDocCheckStatus(input, options.checkingMessage, 'checking');
        if (typeof global.RegistrationSkeleton !== 'undefined') {
            global.RegistrationSkeleton.showFieldCheck(input, 'Verifying document…');
        }

        if (typeof options.checker === 'undefined') {
            input.value = '';
            var unavailable = options.unavailableMessage || 'Document check could not start. Refresh the page and try again.';
            if (deps.displayFileError) deps.displayFileError(input, unavailable);
            global.RegistrationDocumentChecks.setDocCheckStatus(input, 'Check unavailable', 'fail');
            if (typeof global.RegistrationSkeleton !== 'undefined') {
                global.RegistrationSkeleton.hideFieldCheck(input);
            }
            return;
        }

        ensureAiReady().then(function () {
            return options.validate(file);
        }).then(function (result) {
            if (typeof global.RegistrationSkeleton !== 'undefined') {
                global.RegistrationSkeleton.hideFieldCheck(input);
            }
            if (!result.valid) {
                input.value = '';
                clearPreview(input);
                if (deps.displayFileError) deps.displayFileError(input, result.message);
                global.RegistrationDocumentChecks.setDocCheckStatus(input, result.message, 'fail');
                return;
            }
            input.dataset[options.flagName] = '1';
            input.classList.add('is-valid');
            global.RegistrationDocumentChecks.setDocCheckStatus(input, result.message, 'ok');
            if (deps.renderPreview) deps.renderPreview(input, file);
            if (deps.onValidated) deps.onValidated(input);
        }).catch(function (err) {
            if (typeof global.RegistrationSkeleton !== 'undefined') {
                global.RegistrationSkeleton.hideFieldCheck(input);
            }
            input.value = '';
            clearPreview(input);
            var detail = err && err.message ? err.message : '';
            var message = detail || options.failMessage;
            if (deps.displayFileError) deps.displayFileError(input, message);
            global.RegistrationDocumentChecks.setDocCheckStatus(input, 'Document check failed — try again', 'fail');
        });
    }

    global.RegistrationDocumentChecks = {
        setFaceCheckStatus: function (input, message, type) {
            setStatus(input, 'face-check-status', message, type);
        },
        setDocCheckStatus: function (input, message, type) {
            setStatus(input, 'doc-check-status', message, type);
        },
        clearStatuses: function (input) {
            setStatus(input, 'face-check-status', '', '');
            setStatus(input, 'doc-check-status', '', '');
        },
        preload: function () {
            if (typeof global.RegistrationAiLoader !== 'undefined') {
                global.RegistrationAiLoader.preload();
            }
        },
        handlePassportPhotoChange: function (input, deps) {
            deps = deps || {};
            resetFlags(input);
            input.dataset.faceValid = '0';

            var file = input.files[0];
            if (!file) {
                clearPreview(input);
                global.RegistrationDocumentChecks.clearStatuses(input);
                if (deps.clearFileError) deps.clearFileError(input);
                return;
            }

            if (deps.clearFileError) deps.clearFileError(input);
            clearPreview(input);

            var validation = deps.validateFileInput ? deps.validateFileInput(input) : { valid: true };
            if (!validation.valid) {
                input.value = '';
                if (deps.displayFileError) deps.displayFileError(input, validation.message);
                global.RegistrationDocumentChecks.setFaceCheckStatus(input, validation.message, 'fail');
                return;
            }

            global.RegistrationDocumentChecks.setFaceCheckStatus(input, 'Loading verification… please wait', 'checking');
            if (typeof global.RegistrationSkeleton !== 'undefined') {
                global.RegistrationSkeleton.showFieldCheck(input, 'Checking face in photo…');
            }

            ensureAiReady().then(function () {
                if (typeof WorkshopPassportPhotoCheck === 'undefined') {
                    throw new Error('Face check could not start. Refresh the page and try again.');
                }
                global.RegistrationDocumentChecks.setFaceCheckStatus(input, 'Checking face in photo… please wait', 'checking');
                return WorkshopPassportPhotoCheck.validate(file);
            }).then(function (result) {
                if (typeof global.RegistrationSkeleton !== 'undefined') {
                    global.RegistrationSkeleton.hideFieldCheck(input);
                }
                if (!result.valid) {
                    input.value = '';
                    clearPreview(input);
                    if (deps.displayFileError) deps.displayFileError(input, result.message);
                    global.RegistrationDocumentChecks.setFaceCheckStatus(input, result.message, 'fail');
                    return;
                }
                input.dataset.faceValid = '1';
                input.classList.add('is-valid');
                global.RegistrationDocumentChecks.setFaceCheckStatus(input, 'Face detected — photo accepted', 'ok');
                if (deps.renderPreview) deps.renderPreview(input, file);
                if (deps.onValidated) deps.onValidated(input);
            }).catch(function (err) {
                if (typeof global.RegistrationSkeleton !== 'undefined') {
                    global.RegistrationSkeleton.hideFieldCheck(input);
                }
                input.value = '';
                clearPreview(input);
                var detail = err && err.message ? err.message : '';
                var message = detail || 'Could not verify the photo. Upload a clear front-facing passport photo and try again.';
                if (deps.displayFileError) deps.displayFileError(input, message);
                global.RegistrationDocumentChecks.setFaceCheckStatus(input, 'Face check failed — try again', 'fail');
            });
        },
        handleAadharCardChange: function (input, deps) {
            runAsyncCheck(input, deps, {
                flagName: 'cardValid',
                checker: global.WorkshopAadharCardCheck,
                checkingMessage: 'Checking Aadhar card document… this may take a few seconds',
                unavailableMessage: 'Document check could not start. Refresh the page and try again.',
                failMessage: 'Could not verify the Aadhar card. Upload a clear photo of your Aadhar card.',
                validate: function (file) {
                    return global.WorkshopAadharCardCheck.validate(file);
                }
            });
        },
        handleMarksheetChange: function (input, deps) {
            var level = input.dataset.requireMarksheet || 'tenth';
            var label = level === 'twelfth' ? '12th certificate, marksheet, or diploma' : '10th certificate or marksheet';
            runAsyncCheck(input, deps, {
                flagName: 'marksheetValid',
                checker: global.RegistrationMarksheetCheck,
                checkingMessage: 'Checking ' + label + '… this may take a few seconds',
                unavailableMessage: 'Marksheet check could not start. Refresh the page and try again.',
                failMessage: 'Could not verify the marksheet/certificate. Upload a clear photo of the correct document.',
                validate: function (file) {
                    return global.RegistrationMarksheetCheck.validate(file, { level: level });
                }
            });
        },
        handleSignatureChange: function (input, deps) {
            runAsyncCheck(input, deps, {
                flagName: 'signatureValid',
                checker: global.RegistrationSignatureCheck,
                checkingMessage: 'Checking signature image… please wait',
                unavailableMessage: 'Signature check could not start. Refresh the page and try again.',
                failMessage: 'Could not verify the signature. Upload a clear signature on white paper.',
                validate: function (file) {
                    return global.RegistrationSignatureCheck.validate(file);
                }
            });
        },
        handleCertificateChange: function (input, deps) {
            var type = input.dataset.requireCertificate || 'caste';
            var label = type === 'graduation' ? 'Graduation certificate' : 'Caste certificate';
            runAsyncCheck(input, deps, {
                flagName: 'certificateValid',
                checker: global.RegistrationCertificateCheck,
                checkingMessage: 'Checking ' + label.toLowerCase() + '… this may take a few seconds',
                unavailableMessage: 'Certificate check could not start. Refresh the page and try again.',
                failMessage: 'Could not verify the certificate. Upload a clear photo of the correct document.',
                validate: function (file) {
                    return global.RegistrationCertificateCheck.validate(file, { type: type });
                }
            });
        },
        handleThumbImpressionChange: function (input, deps) {
            deps = deps || {};
            var file = input.files[0];
            if (!file) {
                input.dataset.thumbValid = '0';
                clearPreview(input);
                global.RegistrationDocumentChecks.clearStatuses(input);
                if (deps.clearFileError) deps.clearFileError(input);
                return;
            }

            runAsyncCheck(input, deps, {
                flagName: 'thumbValid',
                checker: global.RegistrationThumbCheck,
                checkingMessage: 'Checking thumb impression… please wait',
                unavailableMessage: 'Thumb impression check could not start. Refresh the page and try again.',
                failMessage: 'Could not verify the thumb impression. Upload a clear thumb print on white paper.',
                validate: function (uploadedFile) {
                    return global.RegistrationThumbCheck.validate(uploadedFile);
                }
            });
        },
        validateBeforeSubmit: function (form) {
            var rules = [
                { name: 'passport_photo', enabled: function (el) { return el.dataset.requireFace === '1'; }, flag: 'faceValid', message: 'Upload a valid passport photo with one clear front-facing face before submitting.' },
                { name: 'aadhar_card', enabled: function (el) { return el.dataset.requireAadharCard === '1'; }, flag: 'cardValid', message: 'Upload a verified Aadhar card photo before submitting.' },
                { name: 'signature', enabled: function (el) { return el.dataset.requireSignature === '1'; }, flag: 'signatureValid', message: 'Upload a verified signature image before submitting.' },
                { name: 'tenth_marksheet', enabled: function (el) { return !!el.dataset.requireMarksheet; }, flag: 'marksheetValid', message: 'Upload a verified 10th certificate or marksheet before submitting.', optional: true },
                { name: 'twelfth_marksheet', enabled: function (el) { return !!el.dataset.requireMarksheet; }, flag: 'marksheetValid', message: 'Upload a verified 12th certificate, marksheet, or diploma before submitting.', optional: true },
                { name: 'caste_certificate', enabled: function (el) { return !!el.dataset.requireCertificate; }, flag: 'certificateValid', message: 'Upload a verified caste certificate before submitting.', optional: true },
                { name: 'graduation_certificate', enabled: function (el) { return !!el.dataset.requireCertificate; }, flag: 'certificateValid', message: 'Upload a verified graduation certificate before submitting.', optional: true },
                { name: 'left_thumb_impression', enabled: function (el) { return el.dataset.requireThumb === '1'; }, flag: 'thumbValid', message: 'Upload a verified thumb impression image before submitting, or remove the file if you do not have one.', optional: true }
            ];

            for (var i = 0; i < rules.length; i++) {
                var rule = rules[i];
                var input = form.querySelector('[name="' + rule.name + '"]');
                if (!input || !rule.enabled(input)) {
                    continue;
                }
                if (rule.optional && (!input.files || !input.files[0])) {
                    continue;
                }
                if (input.dataset[rule.flag] !== '1') {
                    return { valid: false, field: rule.name, message: rule.message };
                }
            }

            return { valid: true };
        }
    };
})(window);
