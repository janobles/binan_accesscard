/**
 * Schedule tab: starts FullCalendar over #scheduleCalendar and wires the
 * create and edit modal.
 *
 * Events come from the data-feed-url endpoint. Dragging across empty days
 * opens the modal with those dates prefilled; dragging or resizing an existing
 * event saves the new span directly, reverting on refusal. Clicking an event
 * opens it for edit or deletion, according to the feed's separate `editable`
 * and `deletable` flags (a started batch with no scans may still be removed,
 * just not re-planned) - a batch that is neither says so instead of opening
 * an empty form. A save that collides answers 409 with the clashing batch,
 * which becomes either a replace confirmation or a plain refusal when that
 * batch already has scans. The confirmation is a second pane of the same
 * dialog, since Bootstrap supports only one open modal at a time.
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

  /**
   * Opens the modal for a new schedule (event is null, always fully
   * editable) or an existing one. The feed's `editable`/`deletable` flags
   * mirror saveSchedule()'s and deleteSchedule()'s own server-side rules
   * exactly (they are not the same rule - a started batch with no scans may
   * still be deleted but not re-planned), so the modal locks the plan fields
   * rather than guess whether a save would be accepted.
   */
  function openForm(start, end, event) {
    if (!modal) return;
    form.reset();
    // A dialog closed while the replace question was up reopens on the form.
    showPane('form');
    document.getElementById('scheduleBatchId').value = event ? event.id : 0;
    document.getElementById('scheduleStart').value = start;
    document.getElementById('scheduleEnd').value = end;

    const p        = event ? event.extendedProps : null;
    const canEdit   = !event || p.editable;
    const canDelete = event ? p.deletable : false;

    document.getElementById('scheduleFormTitle').textContent = event
      ? (canEdit ? 'Edit schedule' : 'Schedule details')
      : 'New schedule';
    document.getElementById('scheduleDelete').classList.toggle('d-none', !canDelete);
    document.getElementById('scheduleSubmit').classList.toggle('d-none', !canEdit);
    document.getElementById('scheduleFields').disabled = !canEdit;
    document.getElementById('scheduleReadOnlyNote').classList.toggle('d-none', canEdit);

    if (event) {
      document.getElementById('scheduleName').value = event.title;
      document.getElementById('scheduleVenue').value = p.venue || '';
      document.getElementById('scheduleSubsidyType').value = p.subsidyTypeId || '';
      document.getElementById('scheduleDailyStart').value = p.dailyStart || '08:00';
      document.getElementById('scheduleDailyEnd').value = p.dailyEnd || '17:00';
      selectColor(p.color || 'green');
      checkCovers('barangay', p.barangayIds || []);
      checkCovers('sector', p.sectorIds || []);
    } else {
      checkCovers('barangay', []);
      checkCovers('sector', []);
    }
    updateCoversLabel('barangay');
    updateCoversLabel('sector');
    refreshEligibleCount();
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

  /**
   * Covers is two closed, one-line dropdowns of checkboxes (barangays,
   * sectors) rather than the tall native multi-select boxes the mock
   * dropped: each toggle button reads "All barangays"/"All sectors" with
   * nothing checked, or "N selected" once some are, same as the mock's
   * closed state.
   */
  function coversBoxes(kind) {
    return document.querySelectorAll('input[name="' + kind + '_ids[]"]');
  }

  function checkCovers(kind, ids) {
    const wanted = ids.map(String);
    coversBoxes(kind).forEach(function (box) {
      box.checked = wanted.indexOf(box.value) !== -1;
    });
  }

  function updateCoversLabel(kind) {
    const toggle = document.getElementById(kind === 'barangay' ? 'scheduleBarangayToggle' : 'scheduleSectorToggle');
    if (!toggle) return;
    const checked = Array.prototype.filter.call(coversBoxes(kind), function (b) { return b.checked; });
    toggle.textContent = checked.length === 0
      ? 'All ' + (kind === 'barangay' ? 'barangays' : 'sectors')
      : checked.length + ' selected';
  }

  document.querySelectorAll('input[name="barangay_ids[]"], input[name="sector_ids[]"]').forEach(function (box) {
    box.addEventListener('change', function () {
      updateCoversLabel('barangay');
      updateCoversLabel('sector');
      refreshEligibleCount();
    });
  });

  /**
   * Refreshes the "N families so far" count under Covers from
   * GET distribution/batches/preview, the same eligibility query the roster
   * freezes on, so the estimate shown here and the eventual roster cannot
   * disagree in shape.
   */
  function refreshEligibleCount() {
    const url = form.dataset.previewUrl;
    const target = document.querySelector('[data-eligible-count]');
    if (!url || !target) return;

    const params = new URLSearchParams();
    coversBoxes('barangay').forEach(function (b) { if (b.checked) params.append('barangay_ids[]', b.value); });
    coversBoxes('sector').forEach(function (s) { if (s.checked) params.append('sector_ids[]', s.value); });

    fetch(url + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (body) {
        if (body && typeof body.eligible === 'number') {
          target.textContent = body.eligible.toLocaleString() + ' families';
        }
      })
      .catch(function () { /* leave the last known count showing */ });
  }

  const calendar = new FullCalendar.Calendar(mount, {
    initialView: 'dayGridMonth',
    height: 'auto',
    headerToolbar: { left: 'title', center: '', right: 'prev,next today' },
    buttonText: { today: 'Today' },
    selectable: canManage,
    editable: canManage,
    // The feed reads ?from=&to=; FullCalendar's own names for these are
    // `start`/`end`, so the request param names are remapped to match rather
    // than the feed guessing at FullCalendar's defaults. Without this, prev
    // and next silently keep serving the server's current-month fallback.
    startParam: 'from',
    endParam: 'to',
    eventSources: [{
      url: mount.dataset.feedUrl
    }],
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
      wrap.className = 'd-flex align-items-center w-100 overflow-hidden';
      wrap.style.padding = '2px 4px';
      if (label) {
        const pill = document.createElement('span');
        pill.className = 'batch-status shadow-sm';
        pill.textContent = label;
        wrap.appendChild(pill);
      }
      const text = document.createElement('span');
      text.className = 'text-truncate';
      text.textContent = eventLabel(arg.event);
      wrap.appendChild(text);
      return { domNodes: [wrap] };
    },
    // A day cell is narrower than most labels, so the visible text is usually
    // clipped; the tooltip is where the whole thing is readable. Built here
    // rather than by view-interactions.js's bindTooltips(), which runs once at
    // load and would miss every event FullCalendar draws afterwards, and torn
    // down again so a month change leaves no orphan instances behind.
    eventDidMount: function (info) {
      if (!window.bootstrap || typeof window.bootstrap.Tooltip !== 'function') return;
      new window.bootstrap.Tooltip(info.el, {
        title: eventLabel(info.event),
        container: 'body'
      });
    },
    eventWillUnmount: function (info) {
      if (!window.bootstrap || typeof window.bootstrap.Tooltip !== 'function') return;
      const tip = window.bootstrap.Tooltip.getInstance(info.el);
      if (tip) tip.dispose();
    },
    select: function (info) {
      if (!canManage) return;
      // FullCalendar hands back an exclusive end; the form wants the last day.
      const last = new Date(info.end);
      last.setDate(last.getDate() - 1);
      openForm(info.startStr, isoLocal(last), null);
    },
    eventClick: function (info) {
      if (!canManage) return;
      const p = info.event.extendedProps;
      // A batch that may be neither re-planned nor removed has nothing this
      // modal can do for it - say so rather than open a form with nothing
      // live, or do nothing and leave the click unexplained.
      if (!p.editable && !p.deletable) {
        window.alert('This batch has already run and cannot be changed or removed.');
        return;
      }
      openForm(p.start, isoMinusDay(p.end), info.event);
    },
    // eventDataTransform sets editable per event from the feed, so FullCalendar
    // already refuses to start a drag or resize on a started/finished batch;
    // these two just persist the ones it does allow.
    eventDrop: persistMovedEvent,
    eventResize: persistMovedEvent
  });

  /**
   * Saves a batch's new span after it is dragged or resized, reverting the
   * move on any refusal so the calendar never shows a change the server did
   * not keep. Posts the full schedule (not just the moved dates) because
   * saveSchedule() rewrites the row wholesale from what it is given.
   */
  function persistMovedEvent(info) {
    const event = info.event;
    const p = event.extendedProps;
    const end = event.end ? isoMinusDay(event.endStr) : event.startStr;

    const fd = new FormData(form);
    const fields = {
      batch_id: event.id,
      name: event.title,
      venue: p.venue || '',
      subsidy_type_id: p.subsidyTypeId || '',
      scheduled_start: event.startStr,
      scheduled_end: end,
      daily_start_time: p.dailyStart || '08:00',
      daily_end_time: p.dailyEnd || '17:00',
      color: p.color || 'green'
    };
    Object.keys(fields).forEach(function (key) {
      fd.delete(key);
      fd.append(key, fields[key]);
    });
    fd.delete('barangay_ids[]');
    fd.delete('sector_ids[]');
    (p.barangayIds || []).forEach(function (id) { fd.append('barangay_ids[]', id); });
    (p.sectorIds || []).forEach(function (id) { fd.append('sector_ids[]', id); });

    fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(readJsonResponse)
      .then(function (res) {
        if (res.status === 200) {
          calendar.refetchEvents();
          return;
        }
        info.revert();
        window.alert(describeSaveError(res));
      })
      .catch(function () {
        info.revert();
        window.alert('Could not move this schedule. Check the connection and try again.');
      });
  }

  /** The same refusal copy the save form uses, for any failed save response. */
  function describeSaveError(res) {
    if (res.body === null) {
      return 'Could not save this schedule. Check the connection and try again.';
    }
    if (res.status === 409 && res.body.error === 'overlap') {
      const c = res.body.clash;
      return c.replaceable
        ? c.name + ' is already on ' + (c.start === c.end ? c.start : c.start + ' to ' + c.end) + '.'
        : c.name + ' already ran on these days. Pick other dates.';
    }
    return res.body.message || 'Could not save this schedule.';
  }

  // Local-date formatting, not toISOString(): toISOString() converts to UTC
  // first, which lands on the previous day for any timezone ahead of UTC.
  function isoLocal(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  }

  function isoMinusDay(iso) {
    const d = new Date(iso + 'T00:00:00');
    d.setDate(d.getDate() - 1);
    return isoLocal(d);
  }

  /** An event's one-line label: name, venue and daily hours. Drawn in the day cell and repeated in full by the tooltip. */
  function eventLabel(event) {
    const p = event.extendedProps;
    const time = (p.dailyStart && p.dailyEnd) ? ' · ' + shortHour(p.dailyStart) + '-' + shortHour(p.dailyEnd) : '';

    return (p.venue ? event.title + ' · ' + p.venue : event.title) + time;
  }

  /** '17:00' -> '5', '08:30' -> '8:30': a round hour drops its minutes, for the compact "8-5" event label. */
  function shortHour(hhmm) {
    const [h, m] = hhmm.split(':');
    const hour12 = ((parseInt(h, 10) % 12) || 12);
    return m === '00' ? String(hour12) : hour12 + ':' + m;
  }

  const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  /** 'Aug 12' for a single day, 'Aug 12-13' within a month, 'Aug 30-Sep 1' across one. */
  function formatWhen(startIso, endIso) {
    const s = new Date(startIso + 'T00:00:00');
    const e = new Date(endIso + 'T00:00:00');
    const startLabel = MONTH_NAMES[s.getMonth()] + ' ' + s.getDate();
    if (startIso === endIso) return startLabel;
    return s.getMonth() === e.getMonth()
      ? startLabel + '-' + e.getDate()
      : startLabel + '-' + MONTH_NAMES[e.getMonth()] + ' ' + e.getDate();
  }

  const formPane     = document.getElementById('scheduleFormPane');
  const conflictPane = document.getElementById('scheduleConflictPane');

  /** Shows one of the dialog's two panes. Bootstrap supports only one open modal, so the confirmation is a state of this one. */
  function showPane(which) {
    if (!formPane || !conflictPane) return;
    formPane.classList.toggle('d-none', which === 'conflict');
    conflictPane.classList.toggle('d-none', which !== 'conflict');
  }

  /**
   * Turns the dialog into the replace question a 409 overlap response
   * describes, with the two named actions the mock specifies rather than
   * window.confirm's generic OK/Cancel. Back returns to the form still
   * filled in, which is what someone who decides not to replace needs in
   * order to pick other dates.
   */
  function confirmReplace(clash, onConfirm) {
    const when = formatWhen(clash.start, clash.end);

    if (!conflictPane) {
      if (window.confirm(clash.name + ' is already on ' + when + '. Only one batch runs at a time, so saving replaces it.')) {
        onConfirm();
      }
      return;
    }

    document.getElementById('scheduleConflictTitle').textContent = 'Replace the schedule on ' + when + '?';

    const message = conflictPane.querySelector('[data-conflict-message]');
    message.textContent = '';
    const name = document.createElement('strong');
    name.textContent = clash.name;
    const consequence = document.createElement('strong');
    consequence.textContent = 'delete that schedule';
    message.appendChild(name);
    message.appendChild(document.createTextNode(
      ' is already plotted for ' + when + ' at ' + clash.venue + '. Only one batch can run at a time, so saving this will '
    ));
    message.appendChild(consequence);
    message.appendChild(document.createTextNode('.'));

    document.getElementById('scheduleConflictConfirm').onclick = function () {
      showPane('form');
      onConfirm();
    };
    showPane('conflict');
  }

  const conflictBack = document.getElementById('scheduleConflictBack');
  if (conflictBack) {
    conflictBack.addEventListener('click', function () { showPane('form'); });
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
          window.alert(describeSaveError(res));
          return;
        }
        if (res.status === 409 && res.body.error === 'overlap' && res.body.clash.replaceable) {
          const c = res.body.clash;
          confirmReplace(c, function () {
            fetch(mount.dataset.deleteUrl + '/' + c.id + '/delete', {
              method: 'POST',
              body: new FormData(form),
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
              .then(readJsonResponse)
              .then(function (delRes) {
                // A failed delete (already served, or another clash) must not
                // resubmit the save - that would hit the same 409 and loop.
                if (delRes.status === 200) {
                  form.requestSubmit();
                  return;
                }
                window.alert((delRes.body && delRes.body.message) || 'Could not replace ' + c.name + '. Check the connection and try again.');
              })
              .catch(function () { window.alert('Could not replace ' + c.name + '. Check the connection and try again.'); });
          });
          return;
        }
        window.alert(describeSaveError(res));
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
      const today = isoLocal(new Date());
      openForm(today, today, null);
    });
  }
})();
