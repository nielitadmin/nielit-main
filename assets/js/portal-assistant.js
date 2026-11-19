(function () {
  'use strict';

  var root = document.getElementById('portal-assistant-root');
  if (!root) {
    return;
  }

  var apiUrl = root.getAttribute('data-api-url') || '/api/portal_assistant_chat.php';
  var fab = document.getElementById('portal-assistant-fab');
  var panel = document.getElementById('portal-assistant-panel');
  var messagesEl = document.getElementById('portal-assistant-messages');
  var form = document.getElementById('portal-assistant-form');
  var input = document.getElementById('portal-assistant-input');
  var history = [];

  function appendMessage(text, role) {
    var div = document.createElement('div');
    div.className = 'portal-assistant-msg portal-assistant-msg--' + (role === 'user' ? 'user' : 'bot');
    div.textContent = text;
    messagesEl.appendChild(div);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    return div;
  }

  function setOpen(open) {
    panel.classList.toggle('is-open', open);
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      input.focus();
    }
  }

  fab.addEventListener('click', function () {
    setOpen(!panel.classList.contains('is-open'));
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      setOpen(false);
    }
  });

  root.querySelectorAll('.portal-assistant-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var q = chip.getAttribute('data-question') || chip.textContent;
      input.value = q;
      form.requestSubmit();
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = (input.value || '').trim();
    if (!text) {
      return;
    }
    input.value = '';
    appendMessage(text, 'user');
    history.push({ role: 'user', content: text });

    var submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    var typing = appendMessage('…', 'bot');
    typing.classList.add('portal-assistant-msg--typing');

    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ message: text, history: history.slice(-10) }),
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { res: res, data: data };
        });
      })
      .then(function (pack) {
        typing.remove();
        var reply =
          (pack.data && pack.data.reply) ||
          (pack.data && pack.data.error) ||
          'Sorry, something went wrong. Please try again or contact the centre.';
        if (!pack.res.ok && pack.data && pack.data.error) {
          reply = pack.data.error;
        }
        appendMessage(reply, 'bot');
        history.push({ role: 'assistant', content: reply });
      })
      .catch(function () {
        typing.remove();
        appendMessage(
          'Could not reach the server. Check your internet connection or try again later.',
          'bot'
        );
      })
      .finally(function () {
        submitBtn.disabled = false;
        input.focus();
      });
  });
})();