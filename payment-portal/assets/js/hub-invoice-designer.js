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

    function uid() {
        return 'b_' + Math.random().toString(36).slice(2, 9);
    }

    function getBlock(id) {
        return layout.blocks.find(function (b) { return b.id === id; });
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
        return '{' + (block.key || 'field') + '}';
    }

    function renderCanvas() {
        if (!canvas || !layout.page) return;
        canvas.style.width = layout.page.width + 'px';
        canvas.style.height = layout.page.height + 'px';
        canvas.style.transform = 'scale(' + scale + ')';
        canvas.innerHTML = '';
        layout.blocks.forEach(function (block) {
            if (block.visible === false) return;
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
            propsPanel.innerHTML = '<p class="small text-secondary">Click a block on the canvas to edit position, size, and text.</p>';
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
            '<div class="form-check mb-2"><input class="form-check-input prop-vis" type="checkbox" id="propVis"><label class="form-check-label" for="propVis">Visible on invoice</label></div>' +
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
        if (spec.type === 'logo') { w = 72; h = 72; }
        if (spec.key === 'line_items') { w = 500; h = 140; }
        if (spec.key === 'payer_block' || spec.key === 'issuer_block') { w = 320; h = 100; }
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
                        block.x = Math.max(0, Math.round(block.x + event.dx / scale));
                        block.y = Math.max(0, Math.round(block.y + event.dy / scale));
                        event.target.style.left = block.x + 'px';
                        event.target.style.top = block.y + 'px';
                        syncJson();
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
                        event.target.style.width = block.w + 'px';
                        event.target.style.height = block.h + 'px';
                        syncJson();
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
            selectedId = null;
            renderCanvas();
            renderProps();
        }
    });

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
            syncJson();
        });
    }
})();
