(function () {
    'use strict';

    var root = document.getElementById('hubInvoiceDesigner');
    if (!root) return;

    var layout = JSON.parse(root.getAttribute('data-layout') || '{}');
    var palette = JSON.parse(root.getAttribute('data-palette') || '[]');
    var canvas = document.getElementById('hubDesignerCanvas');
    var propsPanel = document.getElementById('hubDesignerProps');
    var jsonInput = document.getElementById('hubLayoutJson');
    var selectedId = null;
    var scale = 0.72;

    if (!layout.page) {
        layout.page = { width: 794, height: 1123, padding: 36, format: 'A4' };
    }

    function pageW() {
        return layout.page.width || 794;
    }

    function pageH() {
        return layout.page.height || 1123;
    }

    function uid() {
        return 'b_' + Math.random().toString(36).slice(2, 9);
    }

    function getBlock(id) {
        return layout.blocks.find(function (b) { return b.id === id; });
    }

    function clampBlock(block) {
        var pw = pageW();
        var ph = pageH();
        block.w = Math.max(40, Math.min(block.w, pw));
        block.h = Math.max(24, Math.min(block.h, ph));
        block.x = Math.max(0, Math.min(block.x, pw - block.w));
        block.y = Math.max(0, Math.min(block.y, ph - block.h));
    }

    function blockBottom(block) {
        return block.y + block.h;
    }

    function layoutOverflowPx() {
        var maxBottom = 0;
        layout.blocks.forEach(function (block) {
            if (block.visible === false) return;
            maxBottom = Math.max(maxBottom, blockBottom(block));
        });
        return Math.max(0, maxBottom - pageH());
    }

    function blockLabel(block) {
        if (block.type === 'logo') return 'Logo';
        if (block.type === 'static') return 'Custom text';
        var p = palette.find(function (x) { return x.key === block.key; });
        return p ? p.label : (block.key || 'Block');
    }

    function previewContent(block) {
        if (block.type === 'logo') {
            return '<img src="' + (root.getAttribute('data-logo') || '') + '" style="max-width:100%;max-height:100%;object-fit:contain" alt="">';
        }
        if (block.type === 'static') {
            return (block.text || 'Custom text').replace(/</g, '&lt;');
        }
        if (block.key === 'qr_code') {
            return '<div style="text-align:center;padding:4px"><div style="width:56px;height:56px;margin:0 auto;border:2px solid #93c5fd;background:#e0f2fe;border-radius:6px;line-height:56px;font-weight:700;color:#1d4ed8">QR</div><div style="font-size:8px;color:#64748b;margin-top:2px">Receipt link</div></div>';
        }
        if (block.key === 'system_notice') {
            return '<span style="font-size:9px;line-height:1.3;color:#0c2340">System generated money receipt — no authority signature required.</span>';
        }
        return '{' + (block.key || 'field') + '}';
    }

    function updatePageHint() {
        var hint = document.getElementById('hubDesPageHint');
        if (!hint) return;
        var over = layoutOverflowPx();
        if (over > 0) {
            hint.className = 'small text-danger fw-semibold mt-2 mb-0';
            hint.textContent = 'Content extends ' + over + 'px past the A4 bottom — move blocks up or use “Fit one A4 page”.';
        } else {
            hint.className = 'small text-success mt-2 mb-0';
            hint.textContent = 'All blocks fit on one A4 page (794 × 1123 px).';
        }
    }

    function renderCanvas() {
        if (!canvas || !layout.page) return;
        layout.page.width = 794;
        layout.page.height = 1123;
        canvas.style.width = pageW() + 'px';
        canvas.style.height = pageH() + 'px';
        canvas.style.transform = 'scale(' + scale + ')';
        canvas.innerHTML =
            '<div class="hub-des-a4-badge" aria-hidden="true">A4 · 210 × 297 mm</div>' +
            '<div class="hub-des-page-edge" aria-hidden="true"></div>';
        layout.blocks.forEach(function (block) {
            if (block.visible === false) return;
            clampBlock(block);
            var el = document.createElement('div');
            el.className = 'hub-des-block' + (block.id === selectedId ? ' selected' : '');
            el.dataset.id = block.id;
            el.style.left = block.x + 'px';
            el.style.top = block.y + 'px';
            el.style.width = block.w + 'px';
            el.style.height = block.h + 'px';
            el.style.fontSize = (block.fontSize || 11) + 'px';
            el.style.textAlign = block.align || 'left';
            el.style.fontWeight = block.bold ? '700' : '400';
            el.innerHTML = '<span class="hub-des-label">' + blockLabel(block) + '</span>' + previewContent(block);
            el.addEventListener('click', function (e) {
                e.stopPropagation();
                selectBlock(block.id);
            });
            canvas.appendChild(el);
        });
        syncJson();
        updatePageHint();
        initInteract();
    }

    function selectBlock(id) {
        selectedId = id;
        renderCanvas();
        renderProps();
    }

    function renderProps() {
        if (!propsPanel) return;
        var block = selectedId ? getBlock(selectedId) : null;
        if (!block) {
            propsPanel.innerHTML = '<p class="small text-secondary">Click a block on the canvas to edit position, size, and text. Everything must stay inside the A4 page.</p>';
            return;
        }
        propsPanel.innerHTML =
            '<p class="fw-semibold small mb-2">' + blockLabel(block) + '</p>' +
            '<div class="mb-2"><label class="form-label">X</label><input type="number" class="form-control form-control-sm prop-x" value="' + block.x + '"></div>' +
            '<div class="mb-2"><label class="form-label">Y</label><input type="number" class="form-control form-control-sm prop-y" value="' + block.y + '"></div>' +
            '<div class="mb-2"><label class="form-label">Width</label><input type="number" class="form-control form-control-sm prop-w" value="' + block.w + '"></div>' +
            '<div class="mb-2"><label class="form-label">Height</label><input type="number" class="form-control form-control-sm prop-h" value="' + block.h + '"></div>' +
            '<div class="mb-2"><label class="form-label">Font size</label><input type="number" class="form-control form-control-sm prop-fs" value="' + (block.fontSize || 11) + '"></div>' +
            '<div class="mb-2"><label class="form-label">Align</label><select class="form-select form-select-sm prop-align"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div>' +
            '<div class="form-check mb-2"><input class="form-check-input prop-bold" type="checkbox" id="propBold"><label class="form-check-label" for="propBold">Bold</label></div>' +
            '<div class="form-check mb-2"><input class="form-check-input prop-vis" type="checkbox" id="propVis"><label class="form-check-label" for="propVis">Visible on money receipt</label></div>' +
            (block.type === 'static' ? '<div class="mb-2"><label class="form-label">Text</label><textarea class="form-control form-control-sm prop-text" rows="3"></textarea></div>' : '') +
            '<button type="button" class="btn btn-sm btn-outline-danger w-100 mt-2" id="hubDesDelete">Remove block</button>';

        propsPanel.querySelector('.prop-align').value = block.align || 'left';
        propsPanel.querySelector('.prop-bold').checked = !!block.bold;
        propsPanel.querySelector('.prop-vis').checked = block.visible !== false;
        if (block.type === 'static') {
            propsPanel.querySelector('.prop-text').value = block.text || '';
        }

        function apply() {
            block.x = parseInt(propsPanel.querySelector('.prop-x').value, 10) || 0;
            block.y = parseInt(propsPanel.querySelector('.prop-y').value, 10) || 0;
            block.w = Math.max(40, parseInt(propsPanel.querySelector('.prop-w').value, 10) || 100);
            block.h = Math.max(24, parseInt(propsPanel.querySelector('.prop-h').value, 10) || 40);
            block.fontSize = parseInt(propsPanel.querySelector('.prop-fs').value, 10) || 11;
            block.align = propsPanel.querySelector('.prop-align').value;
            block.bold = propsPanel.querySelector('.prop-bold').checked;
            block.visible = propsPanel.querySelector('.prop-vis').checked;
            if (block.type === 'static' && propsPanel.querySelector('.prop-text')) {
                block.text = propsPanel.querySelector('.prop-text').value;
            }
            clampBlock(block);
            renderCanvas();
        }

        propsPanel.querySelectorAll('input,select,textarea').forEach(function (el) {
            el.addEventListener('change', apply);
            el.addEventListener('input', apply);
        });
        document.getElementById('hubDesDelete').addEventListener('click', function () {
            layout.blocks = layout.blocks.filter(function (b) { return b.id !== block.id; });
            selectedId = null;
            renderCanvas();
            renderProps();
        });
    }

    function syncJson() {
        if (jsonInput) {
            jsonInput.value = JSON.stringify(layout);
        }
    }

    function addBlock(spec) {
        var w = 200;
        var h = 48;
        if (spec.type === 'logo') { w = 64; h = 64; }
        if (spec.key === 'line_items') { w = 500; h = 118; }
        if (spec.key === 'payer_block' || spec.key === 'issuer_block') { w = 320; h = 82; }
        if (spec.key === 'qr_code') { w = 128; h = 132; }
        if (spec.key === 'system_notice') { w = 400; h = 64; }
        var block = {
            id: uid(),
            type: spec.type,
            key: spec.key || '',
            text: spec.type === 'static' ? 'Custom text' : '',
            x: 40,
            y: 40,
            w: w,
            h: h,
            fontSize: 11,
            align: 'left',
            bold: false,
            visible: true
        };
        clampBlock(block);
        layout.blocks.push(block);
        selectBlock(block.id);
    }

    function initInteract() {
        if (typeof interact === 'undefined') return;
        interact('.hub-des-block').unset();
        interact('.hub-des-block')
            .draggable({
                listeners: {
                    move: function (event) {
                        var block = getBlock(event.target.dataset.id);
                        if (!block) return;
                        block.x = Math.round(block.x + event.dx / scale);
                        block.y = Math.round(block.y + event.dy / scale);
                        clampBlock(block);
                        event.target.style.left = block.x + 'px';
                        event.target.style.top = block.y + 'px';
                        syncJson();
                        updatePageHint();
                        if (selectedId === block.id && propsPanel && propsPanel.querySelector('.prop-x')) {
                            propsPanel.querySelector('.prop-x').value = block.x;
                            propsPanel.querySelector('.prop-y').value = block.y;
                        }
                    }
                }
            })
            .resizable({
                edges: { left: false, right: true, bottom: true, top: false },
                listeners: {
                    move: function (event) {
                        var block = getBlock(event.target.dataset.id);
                        if (!block) return;
                        block.w = Math.max(40, Math.round(event.rect.width / scale));
                        block.h = Math.max(24, Math.round(event.rect.height / scale));
                        clampBlock(block);
                        event.target.style.width = block.w + 'px';
                        event.target.style.height = block.h + 'px';
                        syncJson();
                        updatePageHint();
                    }
                },
                modifiers: [
                    interact.modifiers.restrictSize({ min: { width: 40, height: 24 } })
                ]
            });
    }

    document.getElementById('hubPalette').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-add]');
        if (!btn) return;
        var idx = parseInt(btn.getAttribute('data-add'), 10);
        if (palette[idx]) addBlock(palette[idx]);
    });

    canvas.addEventListener('click', function () {
        selectedId = null;
        renderCanvas();
        renderProps();
    });

    document.getElementById('hubDesReset').addEventListener('click', function () {
        if (confirm('Reset layout to default? Unsaved changes will be lost.')) {
            layout = JSON.parse(root.getAttribute('data-default') || '{}');
            if (!layout.page) {
                layout.page = { width: 794, height: 1123, padding: 36, format: 'A4' };
            }
            selectedId = null;
            renderCanvas();
            renderProps();
        }
    });

    var fitBtn = document.getElementById('hubDesFitA4');
    if (fitBtn) {
        fitBtn.addEventListener('click', function () {
            if (confirm('Apply the compact one-page A4 default layout? Unsaved changes will be lost.')) {
                layout = JSON.parse(root.getAttribute('data-default') || '{}');
                selectedId = null;
                renderCanvas();
                renderProps();
            }
        });
    }

    var scaleInput = document.getElementById('hubDesScale');
    if (scaleInput) {
        scaleInput.value = Math.round(scale * 100);
        scaleInput.addEventListener('input', function () {
            scale = Math.max(0.4, Math.min(1, parseInt(scaleInput.value, 10) / 100));
            renderCanvas();
        });
    }

    renderCanvas();
    renderProps();

    var form = document.getElementById('hubDesignerForm');
    if (form) {
        form.addEventListener('submit', function () {
            layout.blocks.forEach(clampBlock);
            syncJson();
        });
    }
})();
