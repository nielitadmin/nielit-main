/**
 * Registration document AI check settings.
 * Lenient mode reduces false rejections from OCR / framing / face detection.
 */
(function (global) {
    'use strict';

    global.RegistrationAiConfig = {
        /** Set false for strict verification (original behaviour). */
        lenientMode: true,

        /** Document must fill at least this much of the photo (was 0.58 / 0.40). */
        minFillRatio: 0.28,

        /** Max empty margin around document (was 0.10 / 0.22). */
        maxMarginRatio: 0.32,

        /** BlazeFace confidence threshold (was 0.65). */
        minFaceProbability: 0.40,

        /** Min face area vs image (was 0.06). */
        minFaceAreaRatio: 0.035,

        /** Max face area vs image (was 0.78). */
        maxFaceAreaRatio: 0.85,

        /** Min passport photo dimensions (was 180). */
        minPassportDimension: 150,

        /** Min document image width (was 320). */
        minDocumentWidth: 240,

        /** Min document image height (was 200). */
        minDocumentHeight: 160,

        /** Accept upload when OCR cannot read text but image looks like a document. */
        acceptOnOcrUnreadable: true,

        /** Do not reject marksheets/certificates/Aadhar when a face is detected in the scan. */
        skipPortraitRejectionOnDocuments: true,

        isLenient: function () {
            return !!this.lenientMode;
        },

        framingThresholds: function () {
            if (this.isLenient()) {
                return {
                    minFillRatio: this.minFillRatio,
                    maxMarginRatio: this.maxMarginRatio
                };
            }
            return {
                minFillRatio: 0.58,
                maxMarginRatio: 0.10
            };
        },

        lenientOcrAccept: function (docLabel) {
            return {
                valid: true,
                message: docLabel + ' accepted. If text was unclear, admin may verify the document later.'
            };
        },

        shouldAcceptUnreadableOcr: function (normalizedText, rejectPattern) {
            if (!this.isLenient() || !this.acceptOnOcrUnreadable) {
                return false;
            }
            var text = (normalizedText || '').trim();
            if (text.length >= 8 && rejectPattern && rejectPattern.test(text)) {
                return false;
            }
            return text.length < 8 || !rejectPattern || !rejectPattern.test(text);
        }
    };
})(window);
