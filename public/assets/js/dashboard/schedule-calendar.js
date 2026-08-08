/**
 * Schedule tab: starts FullCalendar over #scheduleCalendar and wires the
 * create and edit modal.
 *
 * Events come from the data-feed-url endpoint. Dragging across days opens the
 * modal with those dates prefilled; clicking an event opens it for edit. A
 * save that collides answers 409 with the clashing batch, which becomes either
 * a replace confirmation or a plain refusal when that batch already has scans.
 */
(function () {
  const mount = document.getElementById('scheduleCalendar');
  if (!mount || typeof FullCalendar === 'undefined') return;

  const canManage = mount.dataset.canManage === '1';
  const form = document.getElementById('scheduleForm');
  const modalEl = document.getElementById('scheduleFormModal');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const KNOWN_COLORS = ['green', 'yellow', 'orange', 'red', 'purple', 'blue'];

  // A response the server never sent as JSON (a 500 HTML page, a dropped
  // connection) resolves body to null here instead of throwing, so every
  // caller can check res.body === null and give the same plain message.
  function readJsonResponse(r) {
    return r.json().catch(function () { return null; }).then(function (body) {
      return { status: r.status, body: body };
    });
  }

  function openForm(start, end, event) {
    if (!modal) return;
    form.reset();
    document.getElementById('scheduleBatchId').value = event ? event.id : 0;
    document.getElementById('scheduleStart').value = start;
    document.getElementById('scheduleEnd').value = end;
    document.getElementById('scheduleFormTitle').textContent = event ? 'Edit schedule' : 'New schedule';
    document.getElementById('scheduleDelete').classList.toggle('d-none', !event);

    if (event) {
      const p = event.extendedProps;
      document.getElementById('scheduleName').value = event.title;
      document.getElementById('scheduleVenue').value = p.venue || '';
      document.getElementById('scheduleSubsidyType').value = p.subsidyTypeId || '';
      document.getElementById('scheduleDailyStart').value = p.dailyStart || '08:00';
      document.getElementById('scheduleDailyEnd').value = p.dailyEnd || '17:00';
      selectColor(p.color || 'green');
    }
    modal.show();
  }

  function selectColor(color) {
    document.querySelectorAll('.batch-swatch').forEach(function (b) {
      const on = b.dataset.color === color;
      b.classList.toggle('selected', on);
      b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    document.getElementById('scheduleColor').value = color;
  }

  document.querySelectorAll('.batch-swatch').forEach(function (b) {
    b.addEventListener('click', function () { selectColor(b.dataset.color); });
  });

  const calendar = new FullCalendar.Calendar(mount, {
    initialView: 'dayGridMonth',
    height: 'auto',
    headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
    selectable: canManage,
    editable: canManage,
    eventSources: [{
      url: mount.dataset.feedUrl,
      extraParams: function () { return {}; }
    }],
    // The feed already answers with `from` and `to` defaults, and FullCalendar
    // appends its own start and end, so the month in view is what comes back.
    eventDataTransform: function (raw) {
      // The feed is our own endpoint, but the colour still lands here as a
      // plain string; fall back to green rather than build a CSS var name
      // out of whatever the response happens to contain.
      const color = KNOWN_COLORS.indexOf(raw.color) !== -1 ? raw.color : 'green';
      return {
        id: raw.id,
        title: raw.title,
        start: raw.start,
        end: raw.end,
        allDay: true,
        backgroundColor: 'var(--batch-' + color + ')',
        borderColor: 'var(--batch-' + color + ')',
        editable: canManage && raw.editable,
        classNames: raw.status === 'finished' ? ['batch-finished'] : [],
        extendedProps: raw
      };
    },
    eventContent: function (arg) {
      const p = arg.event.extendedProps;
      const label = p.status === 'running' ? 'Open' : (p.status === 'finished' ? 'Done' : '');
      const wrap = document.createElement('div');
      wrap.className = 'd-flex align-items-center';
      if (label) {
        const pill = document.createElement('span');
        pill.className = 'batch-status';
        pill.textContent = label;
        wrap.appendChild(pill);
      }
      const text = document.createElement('span');
      text.textContent = p.venue ? arg.event.title + ' - ' + p.venue : arg.event.title;
      wrap.appendChild(text);
      return { domNodes: [wrap] };
    },
    select: function (info) {
      if (!canManage) return;
      // FullCalendar hands back an exclusive end; the form wants the last day.
      const last = new Date(info.end.getTime() - 86400000);
      openForm(info.startStr, last.toISOString().slice(0, 10), null);
    },
    eventClick: function (info) {
      if (!canManage) return;
      const p = info.event.extendedProps;
      openForm(p.start, p.end === p.start ? p.start : isoMinusDay(p.end), info.event);
    }
  });

  function isoMinusDay(iso) {
    const d = new Date(iso + 'T00:00:00');
    d.setDate(d.getDate() - 1);
    return d.toISOString().slice(0, 10);
  }

  calendar.render();

  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(readJsonResponse)
      .then(function (res) {
        if (res.status === 200) {
          modal.hide();
          calendar.refetchEvents();
          return;
        }
        if (res.body === null) {
          window.alert('Could not save this schedule. Check the connection and try again.');
          return;
        }
        if (res.status === 409 && res.body.error === 'overlap') {
          const c = res.body.clash;
          const when = c.start === c.end ? c.start : c.start + ' to ' + c.end;
          if (!c.replaceable) {
            window.alert(c.name + ' already ran on these days. Pick other dates.');
            return;
          }
          if (window.confirm(c.name + ' is already on ' + when + '. Only one batch runs at a time, so saving replaces it.')) {
            fetch(mount.dataset.deleteUrl + '/' + c.id + '/delete', {
              method: 'POST',
              body: new FormData(form),
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function () { form.requestSubmit(); })
              .catch(function () { window.alert('Could not replace ' + c.name + '. Check the connection and try again.'); });
          }
          return;
        }
        window.alert(res.body.message || 'Could not save this schedule.');
      })
      .catch(function () { window.alert('Could not save this schedule. Check the connection and try again.'); });
  });

  document.getElementById('scheduleDelete').addEventListener('click', function () {
    const id = document.getElementById('scheduleBatchId').value;
    if (!id || id === '0') return;
    if (!window.confirm('Remove this schedule?')) return;

    fetch(mount.dataset.deleteUrl + '/' + id + '/delete', {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(readJsonResponse)
      .then(function (res) {
        if (res.status === 200) {
          modal.hide();
          calendar.refetchEvents();
          return;
        }
        if (res.body === null) {
          window.alert('Could not remove this schedule. Check the connection and try again.');
          return;
        }
        window.alert(res.body.message || 'Could not remove this schedule.');
      })
      .catch(function () { window.alert('Could not remove this schedule. Check the connection and try again.'); });
  });

  const newBtn = document.getElementById('newScheduleBtn');
  if (newBtn) {
    newBtn.addEventListener('click', function () {
      const today = new Date().toISOString().slice(0, 10);
      openForm(today, today, null);
    });
  }
})();
