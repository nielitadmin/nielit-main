(function () {
  'use strict';

  var root = document.getElementById('portal-assistant-root');
  if (!root) {
    return;
  }

  var apiUrl = root.getAttribute('data-api-url') || '/api/portal_assistant_chat.php';
  var coursesUrl = root.getAttribute('data-courses-url') || '/public/courses.php';
  var fab = document.getElementById('portal-assistant-fab');
  var closeBtn = document.getElementById('saarthi-close-btn');
  var resetBtn = document.getElementById('saarthi-reset-btn');
  var panel = document.getElementById('portal-assistant-panel');
  var messagesEl = document.getElementById('portal-assistant-messages');
  var chipsEl = document.getElementById('portal-assistant-chips');
  var form = document.getElementById('portal-assistant-form');
  var input = document.getElementById('portal-assistant-input');
  var headerAvatar = document.getElementById('saarthi-header-avatar');
  var fabAvatar = document.getElementById('saarthi-fab-avatar');
  var langPills = root.querySelectorAll('.saarthi-lang-pill');

  var STORAGE_KEY = 'saarthi_widget_state_v2';
  var activeLang = root.getAttribute('data-initial-lang') || 'en';
  var history = [];
  var transcript = [];

  var WELCOME_TEXT = {
    en: "Namaste! I'm **Saarthi**, your AI Campus & Portal Assistant for NIELIT Bhubaneswar.\nI can guide you with a **4-step portal roadmap**, search **live courses & seats**, or help you **register in chat** in English, हिन्दी, or ଓଡ଼ିଆ.",
    hi: "नमस्ते! मैं **सारथी** हूँ — NIELIT भुवनेश्वर का आपका AI कैंपस और पोर्टल सहायक।\nमैं आपको **4-चरणीय पोर्टल रोडमैप** दिखा सकता हूँ, **लाइव कोर्स व सीटें** बता सकता हूँ, और यहीं चैट में **रजिस्ट्रेशन** कर सकता हूँ।",
    or: "ନମସ୍କାର! ମୁଁ **ସାରଥୀ** — NIELIT ଭୁବନେଶ୍ୱରର ଆପଣଙ୍କ AI କ୍ୟାମ୍ପସ୍ ଏବଂ ପୋର୍ଟାଲ୍ ସହାୟକ।\nମୁଁ ଆପଣଙ୍କୁ **୪-ସୋପାନ ପୋର୍ଟାଲ୍ ରୋଡ୍‌ମ୍ୟାପ୍** ଦେଖାଇପାରିବି, **ଲାଇଭ୍ କୋର୍ସ ଓ ସିଟ୍** ଖୋଜିପାରିବି ଏବଂ ଚାଟ୍‌ରେ **ପଞ୍ଜୀକରଣ** କରାଇପାରିବି।"
  };

  var DEFAULT_CHIPS = {
    en: ['How does this site work?', 'Ongoing courses & centers', 'Register for a course', 'Odia / हिन्दी'],
    hi: ['पोर्टल कैसे काम करता है?', 'कोर्स और सीटें देखें', 'नया रजिस्ट्रेशन करें', 'Odia re kuha'],
    or: ['ପୋର୍ଟାଲ୍ କେମିତି କାମ କରେ?', 'ଚାଲୁଥିବା କୋର୍ସ ଦେଖନ୍ତୁ', 'ନୂଆ ପଞ୍ଜୀକରଣ କରନ୍ତୁ', 'Hindi mein batao']
  };

  var PLACEHOLDERS = {
    en: 'Ask Saarthi in English, हिन्दी, or ଓଡ଼ିଆ…',
    hi: 'सारथी से हिन्दी, English या ଓଡ଼ିଆ में पूछें…',
    or: 'ସାରଥୀଙ୍କୁ ଓଡ଼ିଆ, English କିମ୍ବା हिन्दी ରେ ପଚାରନ୍ତୁ…'
  };

  function setAvatarState(state) {
    var allowed = ['wave', 'thinking', 'guide', 'happy', 'celebrate'];
    var s = allowed.indexOf(state) !== -1 ? state : 'happy';
    [headerAvatar, fabAvatar].forEach(function (el) {
      if (!el) return;
      allowed.forEach(function (cls) {
        el.classList.remove('saarthi-avatar--' + cls);
      });
      el.classList.add('saarthi-avatar--' + s);
    });
  }

  function setLang(lang) {
    activeLang = ['en', 'hi', 'or'].indexOf(lang) !== -1 ? lang : 'en';
    langPills.forEach(function (pill) {
      pill.classList.toggle('is-active', pill.getAttribute('data-lang') === activeLang);
    });
    if (input) {
      input.setAttribute('placeholder', PLACEHOLDERS[activeLang] || PLACEHOLDERS.en);
    }
  }

  function saveState() {
    try {
      sessionStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
          lang: activeLang,
          open: panel.classList.contains('is-open'),
          history: history.slice(-12),
          transcript: transcript.slice(-18)
        })
      );
    } catch (e) {
      // Ignore storage quota errors
    }
  }

  function loadState() {
    try {
      var raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function createEl(tag, className, text) {
    var el = document.createElement(tag);
    if (className) el.className = className;
    if (typeof text === 'string') el.textContent = text;
    return el;
  }

  // Render sanitized markdown-lite (**bold**, `code`, URLs, newlines) safely without unsafe raw HTML
  function renderSafeFormattedText(container, text) {
    var lines = String(text || '').split(/\r?\n/);
    lines.forEach(function (line, lineIdx) {
      if (lineIdx > 0) {
        container.appendChild(document.createElement('br'));
      }
      var tokenRe = /(\*\*[^*]+\*\*|`[^`]+`|https?:\/\/[^\s<]+)/g;
      var lastIndex = 0;
      var match;
      while ((match = tokenRe.exec(line)) !== null) {
        if (match.index > lastIndex) {
          container.appendChild(document.createTextNode(line.slice(lastIndex, match.index)));
        }
        var tok = match[0];
        if (tok.indexOf('**') === 0 && tok.slice(-2) === '**') {
          var strong = document.createElement('strong');
          strong.textContent = tok.slice(2, -2);
          container.appendChild(strong);
        } else if (tok.indexOf('`') === 0 && tok.slice(-1) === '`') {
          var code = document.createElement('code');
          code.textContent = tok.slice(1, -1);
          container.appendChild(code);
        } else {
          var cleanUrl = tok.replace(/[),.;!?]+$/, '');
          var trailing = tok.slice(cleanUrl.length);
          var link = document.createElement('a');
          link.className = 'saarthi-msg-link';
          link.href = cleanUrl;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.textContent = cleanUrl;
          container.appendChild(link);
          if (trailing) {
            container.appendChild(document.createTextNode(trailing));
          }
        }
        lastIndex = tokenRe.lastIndex;
      }
      if (lastIndex < line.length) {
        container.appendChild(document.createTextNode(line.slice(lastIndex)));
      }
    });
  }

  function scrollCoursesSection(preferredUrl) {
    var targets = [
      document.getElementById('skill-long-section'),
      document.querySelector('.course-section'),
      document.querySelector('.dynamic-course'),
      document.querySelector('[data-saarthi-courses]')
    ];
    var target = null;
    for (var i = 0; i < targets.length; i++) {
      if (targets[i]) {
        target = targets[i];
        break;
      }
    }
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      target.classList.remove('saarthi-highlight-pulse');
      void target.offsetWidth;
      target.classList.add('saarthi-highlight-pulse');
      return true;
    }
    // Not on a page with course cards -> navigate cleanly to courses.php while keeping Saarthi state
    if (preferredUrl || coursesUrl) {
      saveState();
      window.location.href = preferredUrl || coursesUrl;
      return true;
    }
    return false;
  }

  function buildCardsFragment(cards) {
    if (!cards || typeof cards !== 'object') return null;
    var wrap = createEl('div', 'saarthi-card-wrap');
    var hasAny = false;

    // 1. Roadmap Card
    if (cards.roadmap && Array.isArray(cards.roadmap.steps)) {
      hasAny = true;
      var rCard = createEl('div', 'saarthi-roadmap-card');
      rCard.appendChild(createEl('div', 'saarthi-card-title', cards.roadmap.title || '4-Step Portal Roadmap'));
      if (cards.roadmap.subtitle) {
        rCard.appendChild(createEl('div', 'saarthi-step-desc', cards.roadmap.subtitle));
      }
      cards.roadmap.steps.forEach(function (st) {
        var row = createEl('div', 'saarthi-roadmap-step');
        row.appendChild(createEl('span', 'saarthi-step-num', String(st.step || '')));
        var info = createEl('div');
        info.appendChild(createEl('div', 'saarthi-step-title', st.title || ''));
        info.appendChild(createEl('div', 'saarthi-step-desc', st.desc || ''));
        if (st.action_label) {
          var actions = createEl('div', 'saarthi-card-actions');
          if (st.action_type === 'link' && st.url) {
            var a = createEl('a', 'saarthi-btn', st.action_label);
            a.href = st.url;
            a.target = '_blank';
            a.rel = 'noopener';
            actions.appendChild(a);
          } else if (st.action_type === 'scroll_courses') {
            var b = createEl('button', 'saarthi-btn saarthi-btn-primary', st.action_label);
            b.type = 'button';
            b.addEventListener('click', function () {
              scrollCoursesSection(st.url);
            });
            actions.appendChild(b);
          } else if (st.question) {
            var qb = createEl('button', 'saarthi-btn', st.action_label);
            qb.type = 'button';
            qb.addEventListener('click', function () {
              sendMessage(st.question);
            });
            actions.appendChild(qb);
          }
          info.appendChild(actions);
        }
        row.appendChild(info);
        rCard.appendChild(row);
      });
      wrap.appendChild(rCard);
    }

    // 2. Live Courses Cards
    if (Array.isArray(cards.courses) && cards.courses.length > 0) {
      hasAny = true;
      cards.courses.forEach(function (c) {
        var cCard = createEl('div', 'saarthi-course-card');
        var head = createEl('div', 'saarthi-card-title');
        head.appendChild(document.createTextNode(c.course_name || 'Course'));
        var pill = createEl(
          'span',
          'saarthi-status-pill ' + (c.is_closed ? 'is-closed' : 'is-open'),
          c.status_label || (c.is_closed ? 'Closed' : 'Open')
        );
        head.appendChild(pill);
        cCard.appendChild(head);

        var meta = createEl('div', 'saarthi-course-meta');
        if (c.campus) meta.appendChild(createEl('span', '', '📍 ' + c.campus));
        if (c.duration) meta.appendChild(createEl('span', '', '⏱ ' + c.duration));
        if (c.mode) meta.appendChild(createEl('span', '', '🖥 ' + c.mode));
        if (c.fees) meta.appendChild(createEl('span', '', '💳 ' + c.fees));
        if (c.seat_status) meta.appendChild(createEl('span', '', '💺 ' + c.seat_status));
        if (Array.isArray(c.batches) && c.batches.length > 0) {
          var b0 = c.batches[0];
          var bText = '📅 Batch: ' + (b0.batch_name || b0.batch_code || 'Active');
          if (b0.start_date) bText += ' (' + b0.start_date + ')';
          if (b0.class_time) bText += ' · ' + b0.class_time;
          meta.appendChild(createEl('span', '', bText));
        }
        cCard.appendChild(meta);

        if (!c.is_closed) {
          var cActions = createEl('div', 'saarthi-card-actions');
          var regBtn = createEl('button', 'saarthi-btn saarthi-btn-primary', 'Register via Saarthi');
          regBtn.type = 'button';
          regBtn.addEventListener('click', function () {
            sendMessage('Register me for ' + (c.course_name || 'this course') + ' at ' + (c.campus || 'NIELIT Bhubaneswar') + ' [course_id:' + (c.id || 0) + ']');
          });
          cActions.appendChild(regBtn);
          if (c.apply_url) {
            var extA = createEl('a', 'saarthi-btn', 'Full Form ↗');
            extA.href = c.apply_url;
            extA.target = '_blank';
            extA.rel = 'noopener';
            cActions.appendChild(extA);
          }
          cCard.appendChild(cActions);
        }
        wrap.appendChild(cCard);
      });
    }

    // 3. Registration Summary Card
    if (cards.registration_summary && typeof cards.registration_summary === 'object') {
      hasAny = true;
      var s = cards.registration_summary;
      var sCard = createEl('div', 'saarthi-summary-card');
      sCard.appendChild(createEl('div', 'saarthi-card-title', 'Registration Summary'));
      var grid = createEl('div', 'saarthi-summary-grid');
      var examDisplay = s.qualification || '';
      if (s.board_university || s.year_of_passing) {
        examDisplay += (examDisplay ? ' · ' : '') + (s.board_university || '') + (s.year_of_passing ? ' (' + s.year_of_passing + ')' : '');
      }
      var streamDisplay = [s.stream || '', s.percentage || ''].filter(Boolean).join(' · ') || '—';
      var fields = [
        ['Course', s.course_name],
        ['Campus', s.center_location],
        ['Name', s.name],
        ['Father / Mother', (s.father_name || '—') + ' / ' + (s.mother_name || '—')],
        ['Mobile', s.mobile],
        ['Email', s.email],
        ['DOB / Gender', (s.dob || '') + ' · ' + (s.gender || '')],
        ['Religion / Marital', (s.religion || 'Hindu') + ' · ' + (s.marital_status || 'Single')],
        ['Birth Mark', s.distinguishing_marks || 'None'],
        ['Examination', examDisplay || '—'],
        ['Stream / %', streamDisplay],
        ['Aadhar / APAAR', (s.aadhar || 'Pending') + ' / ' + (s.apaar_id || 'N/A')],
        ['Category / PwD', (s.category || 'General') + ' / PwD: ' + (s.pwd_status || 'No')],
        ['Address', s.address]
      ];
      fields.forEach(function (pair) {
        grid.appendChild(createEl('span', 'saarthi-summary-k', pair[0] + ':'));
        grid.appendChild(createEl('span', 'saarthi-summary-v', String(pair[1] || '—')));
      });
      sCard.appendChild(grid);

      var sActs = createEl('div', 'saarthi-card-actions');
      var confirmBtn = createEl('button', 'saarthi-btn saarthi-btn-gold', '✓ Confirm & Submit');
      confirmBtn.type = 'button';
      confirmBtn.addEventListener('click', function () {
        sendMessage('Confirm');
      });
      var editBtn = createEl('button', 'saarthi-btn', 'Edit Details');
      editBtn.type = 'button';
      editBtn.addEventListener('click', function () {
        sendMessage('Edit details');
      });
      sActs.appendChild(confirmBtn);
      sActs.appendChild(editBtn);
      sCard.appendChild(sActs);
      wrap.appendChild(sCard);
    }

    // 4. Registration Receipt & Admission PDF Download Card
    if (cards.registration_receipt && typeof cards.registration_receipt === 'object' && cards.registration_receipt.student_id) {
      hasAny = true;
      var r = cards.registration_receipt;
      var recCard = createEl('div', 'saarthi-receipt-card');
      recCard.appendChild(createEl('div', 'saarthi-card-title', '🎉 Registration Recorded'));
      var rGrid = createEl('div', 'saarthi-summary-grid');
      rGrid.appendChild(createEl('span', 'saarthi-summary-k', 'Student ID:'));
      rGrid.appendChild(createEl('span', 'saarthi-summary-v', String(r.student_id || '')));
      rGrid.appendChild(createEl('span', 'saarthi-summary-k', 'Course:'));
      rGrid.appendChild(createEl('span', 'saarthi-summary-v', String(r.course_name || '')));
      rGrid.appendChild(createEl('span', 'saarthi-summary-k', 'Campus:'));
      rGrid.appendChild(createEl('span', 'saarthi-summary-v', String(r.center_location || '')));
      recCard.appendChild(rGrid);

      var rActs = createEl('div', 'saarthi-card-actions');
      if (r.download_pdf_url) {
        var dlA = createEl('a', 'saarthi-btn saarthi-btn-gold', '⬇ Download Admission PDF');
        dlA.href = r.download_pdf_url;
        dlA.target = '_blank';
        dlA.rel = 'noopener';
        rActs.appendChild(dlA);
      }
      if (r.upload_portal_url) {
        var upLink = createEl('a', 'saarthi-btn saarthi-btn-primary', '📎 Upload Page ↗');
        upLink.href = r.upload_portal_url;
        upLink.target = '_blank';
        upLink.rel = 'noopener';
        rActs.appendChild(upLink);
      }
      if (r.student_login_url) {
        var lA = createEl('a', 'saarthi-btn', 'Student Portal Login');
        lA.href = r.student_login_url;
        lA.target = '_blank';
        lA.rel = 'noopener';
        rActs.appendChild(lA);
      }
      recCard.appendChild(rActs);

      // Inline document uploader drawer when upload_api_url & checklist are available
      if (r.upload_api_url && Array.isArray(r.document_checklist) && r.document_checklist.length > 0) {
        var upBox = createEl('div', 'saarthi-upload-box');
        var toggleBtn = createEl('button', 'saarthi-btn saarthi-btn-primary saarthi-upload-toggle', '📎 Upload Photo, Signature & Aadhar Here');
        toggleBtn.type = 'button';
        var formWrap = createEl('form', 'saarthi-upload-form');
        formWrap.hidden = true;

        // 12-digit Aadhar field
        var aadRow = createEl('div', 'saarthi-upload-row');
        var aadHead = createEl('div', 'saarthi-upload-label');
        aadHead.appendChild(createEl('span', '', 'Aadhar Number (12 digits)'));
        aadHead.appendChild(createEl('span', 'saarthi-upload-badge ' + (r.aadhar ? 'is-done' : 'is-req'), r.aadhar ? 'Saved' : 'Recommended'));
        aadRow.appendChild(aadHead);
        var aadInput = document.createElement('input');
        aadInput.type = 'text';
        aadInput.name = 'aadhar';
        aadInput.maxLength = 12;
        aadInput.placeholder = '12-digit Aadhar number';
        aadInput.value = r.aadhar || '';
        aadInput.className = 'saarthi-upload-text';
        aadRow.appendChild(aadInput);
        formWrap.appendChild(aadRow);

        var fileInputs = [];
        var badgesByKey = {};
        r.document_checklist.forEach(function (doc) {
          if (!doc || !doc.key) return;
          // Show required docs + core identity docs cleanly in compact chat widget
          if (!doc.required && ['passport_photo', 'signature', 'aadhar_card', 'graduation_certificate', 'tenth_marksheet'].indexOf(doc.key) === -1) {
            return;
          }
          var dRow = createEl('div', 'saarthi-upload-row');
          var dHead = createEl('div', 'saarthi-upload-label');
          dHead.appendChild(createEl('span', '', String(doc.label || doc.key)));
          var stBadge = createEl('span', 'saarthi-upload-badge ' + (doc.uploaded ? 'is-done' : (doc.required ? 'is-req' : 'is-opt')), doc.uploaded ? '✓ Uploaded' : (doc.required ? 'Required' : 'Optional'));
          badgesByKey[doc.key] = stBadge;
          dHead.appendChild(stBadge);
          dRow.appendChild(dHead);

          var fIn = document.createElement('input');
          fIn.type = 'file';
          fIn.name = String(doc.key);
          fIn.accept = String(doc.accept || '.jpg,.jpeg,.png,.pdf');
          fIn.className = 'saarthi-upload-file';
          fileInputs.push(fIn);
          dRow.appendChild(fIn);
          formWrap.appendChild(dRow);
        });

        var statusMsg = createEl('div', 'saarthi-upload-status', '');
        var submitBtn = createEl('button', 'saarthi-btn saarthi-btn-gold', '⬆ Save Selected Documents');
        submitBtn.type = 'submit';
        formWrap.appendChild(statusMsg);
        formWrap.appendChild(submitBtn);

        toggleBtn.addEventListener('click', function () {
          formWrap.hidden = !formWrap.hidden;
          if (!formWrap.hidden && messagesEl) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
          }
        });

        formWrap.addEventListener('submit', function (ev) {
          ev.preventDefault();
          var fd = new FormData(formWrap);
          fd.append('record_id', String(r.record_id || ''));
          if (r.upload_token) {
            fd.append('token', String(r.upload_token));
          }
          submitBtn.disabled = true;
          statusMsg.className = 'saarthi-upload-status';
          statusMsg.textContent = 'Uploading…';

          fetch(r.upload_api_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd,
          })
            .then(function (resp) {
              return resp.json().then(function (j) { return { status: resp.status, body: j }; });
            })
            .then(function (res) {
              var data = res.body || {};
              statusMsg.textContent = data.message || (res.status < 400 ? 'Uploaded.' : 'Upload failed.');
              statusMsg.className = 'saarthi-upload-status ' + (data.ok ? 'is-ok' : 'is-err');
              if (Array.isArray(data.checklist)) {
                data.checklist.forEach(function (item) {
                  if (item && badgesByKey[item.key]) {
                    var b = badgesByKey[item.key];
                    b.textContent = item.uploaded ? '✓ Uploaded' : (item.required ? 'Required' : 'Optional');
                    b.className = 'saarthi-upload-badge ' + (item.uploaded ? 'is-done' : (item.required ? 'is-req' : 'is-opt'));
                  }
                });
              }
              if (data.aadhar) {
                aadHead.lastChild.textContent = '✓ Saved';
                aadHead.lastChild.className = 'saarthi-upload-badge is-done';
              }
              if (data.download_pdf_url && dlA) {
                dlA.href = data.download_pdf_url;
              }
              fileInputs.forEach(function (fi) { fi.value = ''; });
            })
            .catch(function () {
              statusMsg.textContent = 'Network error during upload. Try "Upload Page ↗" above.';
              statusMsg.className = 'saarthi-upload-status is-err';
            })
            .finally(function () {
              submitBtn.disabled = false;
            });
        });

        upBox.appendChild(toggleBtn);
        upBox.appendChild(formWrap);
        recCard.appendChild(upBox);
      }

      wrap.appendChild(recCard);
    }

    return hasAny ? wrap : null;
  }

  function renderChips(chips) {
    if (!chipsEl) return;
    chipsEl.innerHTML = '';
    var list = Array.isArray(chips) && chips.length > 0 ? chips : (DEFAULT_CHIPS[activeLang] || DEFAULT_CHIPS.en);
    list.forEach(function (label) {
      var btn = createEl('button', 'portal-assistant-chip', String(label));
      btn.type = 'button';
      btn.addEventListener('click', function () {
        var q = String(label);
        if (q.toLowerCase().indexOf('show courses section') !== -1) {
          scrollCoursesSection(coursesUrl);
          return;
        }
        sendMessage(q);
      });
      chipsEl.appendChild(btn);
    });
  }

  function appendMessage(text, role, cards, skipPersist) {
    var div = createEl('div', 'portal-assistant-msg portal-assistant-msg--' + (role === 'user' ? 'user' : 'bot'));
    var body = createEl('div', 'saarthi-msg-body');
    if (role === 'user') {
      // Hide internal course_id tag from visible bubble while still sending it to API
      body.textContent = String(text || '').replace(/\s*\[course_id:\d+\]/gi, '');
    } else {
      renderSafeFormattedText(body, text);
    }
    div.appendChild(body);

    var cardsEl = buildCardsFragment(cards);
    if (cardsEl) {
      div.appendChild(cardsEl);
    }

    messagesEl.appendChild(div);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    if (!skipPersist && text !== '…') {
      transcript.push({ role: role, text: text, cards: cards || {} });
      saveState();
    }
    return div;
  }

  function executeUiAction(uiAction) {
    if (!uiAction || uiAction === 'NONE') return;
    if (uiAction === 'SCROLL_COURSES') {
      setTimeout(function () {
        scrollCoursesSection(coursesUrl);
      }, 450);
    }
  }

  function setOpen(open) {
    panel.classList.toggle('is-open', open);
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open && input) {
      input.focus();
    }
    saveState();
  }

  function sendMessage(rawText) {
    var text = String(rawText || '').trim();
    if (!text) return;

    if (!panel.classList.contains('is-open')) {
      setOpen(true);
    }

    appendMessage(text, 'user', null, false);
    history.push({ role: 'user', content: text });

    var submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;
    setAvatarState('thinking');
    var typing = appendMessage('…', 'bot', null, true);
    typing.classList.add('portal-assistant-msg--typing');

    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({
        action: 'chat',
        message: text,
        lang: activeLang,
        history: history.slice(-10)
      })
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { res: res, data: data };
        });
      })
      .then(function (pack) {
        typing.remove();
        var d = pack.data || {};
        var reply = d.reply || d.error || 'Sorry, something went wrong. Please try again or contact the centre.';
        if (!pack.res.ok && d.error) {
          reply = d.error;
        }
        if (d.lang) setLang(d.lang);
        setAvatarState(d.avatar_state || 'happy');
        appendMessage(reply, 'bot', d.cards || {}, false);
        history.push({ role: 'assistant', content: reply });
        renderChips(d.quick_replies || []);
        executeUiAction(d.ui_action || 'NONE');
        saveState();
      })
      .catch(function () {
        typing.remove();
        setAvatarState('thinking');
        appendMessage(
          'Could not reach the server. Check your internet connection or contact Helpdesk (0674-2960354).',
          'bot',
          null,
          false
        );
      })
      .finally(function () {
        if (submitBtn) submitBtn.disabled = false;
        if (input) input.focus();
      });
  }

  fab.addEventListener('click', function () {
    setOpen(!panel.classList.contains('is-open'));
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      setOpen(false);
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      history = [];
      transcript = [];
      messagesEl.innerHTML = '';
      try {
        sessionStorage.removeItem(STORAGE_KEY);
      } catch (e) {}
      setAvatarState('thinking');
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'reset', lang: activeLang })
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.lang) setLang(d.lang);
          setAvatarState((d && d.avatar_state) || 'wave');
          appendMessage((d && d.reply) || WELCOME_TEXT[activeLang], 'bot', {}, false);
          renderChips((d && d.quick_replies) || DEFAULT_CHIPS[activeLang]);
        })
        .catch(function () {
          setAvatarState('wave');
          appendMessage(WELCOME_TEXT[activeLang], 'bot', {}, false);
          renderChips(DEFAULT_CHIPS[activeLang]);
        });
    });
  }

  langPills.forEach(function (pill) {
    pill.addEventListener('click', function () {
      var targetLang = pill.getAttribute('data-lang') || 'en';
      setLang(targetLang);
      var switchMsg = targetLang === 'hi' ? 'Hindi mein batao' : (targetLang === 'or' ? 'Odia re kuha' : 'Switch to English');
      sendMessage(switchMsg);
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && panel.classList.contains('is-open')) {
      setOpen(false);
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = (input.value || '').trim();
    if (!text) return;
    input.value = '';
    sendMessage(text);
  });

  // Restore conversation state across public page navigations
  var restored = loadState();
  if (restored && Array.isArray(restored.transcript) && restored.transcript.length > 0) {
    setLang(restored.lang || activeLang);
    history = Array.isArray(restored.history) ? restored.history : [];
    restored.transcript.forEach(function (item) {
      appendMessage(item.text || '', item.role === 'user' ? 'user' : 'bot', item.cards || {}, true);
      transcript.push(item);
    });
    renderChips(DEFAULT_CHIPS[activeLang]);
    setAvatarState('happy');
    if (restored.open) {
      setOpen(true);
    }
  } else {
    setLang(activeLang);
    setAvatarState('wave');
    appendMessage(WELCOME_TEXT[activeLang] || WELCOME_TEXT.en, 'bot', {}, false);
    renderChips(DEFAULT_CHIPS[activeLang]);
  }
})();
