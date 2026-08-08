/**
 * Schedule tab: starts FullCalendar over #scheduleCalendar and wires the
 * create and edit modal.
 *
 * Events come from the data-feed-url endpoint. Dragging across empty days
 * opens the modal with those dates prefilled; dragging or resizing an existing
 * event saves the new span directly, reverting on refusal. Clicking an event
 * opens it for edit, when the feed says it is still editable. A save that
 * collides answers 409 with the clashing batch, which becomes either a
 * replace confirmation or a plain refusal when that batch already has scans.
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
      const last = new Date(info.end);
      last.setDate(last.getDate() - 1);
      openForm(info.startStr, isoLocal(last), null);
    },
    eventClick: function (info) {
      if (!canManage) return;
      const p = info.event.extendedProps;
      // The server refuses to save over a batch that has started or has scans
      // (DistributionBatchModel::saveSchedule()); don't offer an edit it would
      // only reject. Reuses the same `editable` flag the feed computes for
      // drag/resize.
      if (!p.editable) return;
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
          const when = c.start === c.end ? c.start : c.start + ' to ' + c.end;
          if (window.confirm(c.name + ' is already on ' + when + '. Only one batch runs at a time, so saving replaces it.')) {
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
          }
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
