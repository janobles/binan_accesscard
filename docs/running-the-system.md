# Running the system

Three ways to get the app up, plus the background worker that makes big Excel
imports actually finish. Pick the run mode that fits what you're doing; the worker
section applies to all of them.

**Before anything:** the database must exist. MySQL on `127.0.0.1:3306`, database
`accesscard`, user `root`, no password (see `.env`). Import the current SQL dump
(`accesscardV14.sql`) — it's the source of truth, there are no CI4 migrations:

```bash
mysql -uroot accesscard < accesscardV14.sql
```

---

## Option 1 — XAMPP (Apache + the `htdocs` way)

This is how the app is laid out on the main machine: the repo lives at
`/Applications/XAMPP/xamppfiles/htdocs/binan_accesscard` (Windows:
`C:\xampp\htdocs\binan_accesscard`).

1. Open **XAMPP Control Panel** and start **Apache** and **MySQL**.
2. CodeIgniter's real entry point is the `public/` folder, not the repo root. So
   the app lives at:

   ```
   http://localhost/binan_accesscard/public/
   ```

3. Set `.env` to match:

   ```ini
   app.baseURL = 'http://localhost/binan_accesscard/public/'
   ```

Good for parity with the deployment box and for running the PowerShell/cron worker
against the same MySQL. The ugly `/public/` in the URL is the trade-off — clean it
up with a vhost or an `.htaccess` rewrite if it bothers you, but it's not required.

> **XAMPP PHP vs. `intl`:** XAMPP's bundled PHP is fine for serving through Apache.
> But if you ever run `php spark ...` from the command line, use an
> **intl-enabled** PHP (see Option 2) — several features need the `intl`
> extension and XAMPP's CLI php often ships without it.

---

## Option 2 — CodeIgniter dev server (`spark serve`, port 8090)

Fastest inner loop: no Apache, clean URLs (no `/public/`), instant restarts. This
is the day-to-day dev mode.

```bash
php spark serve --port 8090
```

Then open `http://localhost:8090`. Set `.env`:

```ini
app.baseURL = 'http://localhost:8090/'
```

**Use an intl-enabled `php`, not XAMPP's.** On the main Mac that's the MacPorts
build at `/opt/local/bin/php`. If `php spark serve` throws about a missing `intl`
extension, you're on the wrong binary — check with `php -m | grep intl` (should
print `intl`). Point at the right one explicitly if needed:

```bash
/opt/local/bin/php spark serve --port 8090
```

MySQL still has to be running (start it via XAMPP, or `mysql.server start`).

> Combine this with a Cloudflare tunnel (see `networking.md`) to share the
> `:8090` server externally without touching Apache.

---

## Option 3 — The background queue worker (for imports/exports)

Heavy work — the big family Excel import today, exports/reports later — does **not**
run inside the web request. A request only drops a row into the `job_queue` table;
a separate worker drains it. That's why an import returns instantly with
"queued — waiting for worker" instead of timing out.

The engine is `php spark queue:work`, which dispatches each job by `type` to its
handler in `App\Config\Queue`. You rarely call it directly — you either drain once
by hand or install it on a schedule.

Load the queue table once (part of the SQL dump; if you have a standalone file):

```bash
mysql -uroot accesscard < sql/job_queue.sql   # skip if already in the main dump
```

### Drain once, by hand

Good enough for local dev — run it after kicking off an import.

```bash
# macOS/Linux
./scripts/queue-worker.sh                 # drain now (250ms throttle)
THROTTLE=500 ./scripts/queue-worker.sh    # gentler on the DB

# or call the engine directly (any OS)
php spark queue:work --throttle=250
```

```powershell
# Windows
.\scripts\queue-worker.ps1
```

### Run it on a schedule (fire-and-forget)

Installs a cron job (mac/Linux) or Scheduled Task (Windows) that drains every
minute, so imports finish without you babysitting them.

```bash
# macOS/Linux — cron entry, every minute by default
./scripts/install-cron-worker.sh
EVERY_MINUTES=5 ./scripts/install-cron-worker.sh   # every 5 min instead
AT=01:30 ./scripts/install-cron-worker.sh          # nightly at 01:30
./scripts/install-cron-worker.sh --uninstall       # remove it

# It uses the first `php` on PATH — override for the intl build:
PHP_BIN=/opt/local/bin/php ./scripts/install-cron-worker.sh
```

```powershell
# Windows — Scheduled Task "BinanQueueWorker", elevated PowerShell
cd C:\xampp\htdocs\binan_accesscard
Set-ExecutionPolicy -Scope Process Bypass -Force
.\scripts\install-cron-worker.ps1 -EveryMinutes 1
.\scripts\install-cron-worker.ps1 -Uninstall        # remove it
```

Logs land in `writable/logs/queue-worker.log`. Watch them:

```bash
tail -f writable/logs/queue-worker.log
```

### "Import stuck on queued — waiting for worker"

Nothing is draining the queue. Check in order:

1. **Is MySQL up?** The worker aborts if it can't connect (see the log). Start it.
2. **Is the worker actually installed/running?** Drain once by hand
   (`./scripts/queue-worker.sh`) — if that clears it, your schedule isn't firing.
3. **Windows laptop on battery?** Scheduled Tasks skip on battery unless
   configured otherwise — the installer handles this, but a task made another way
   won't. Full details + fix in [`scripts/README.md`](../scripts/README.md).
4. **Machine was asleep/off?** Every-minute ticks don't run then; drain the
   backlog by hand once it's awake.

The full worker reference (tuning flags, adding a new job type, deeper Windows
troubleshooting) lives in [`scripts/README.md`](../scripts/README.md).

---

## Quick reference

| Mode              | Command                          | URL                                        | `intl` php? |
|-------------------|----------------------------------|--------------------------------------------|-------------|
| XAMPP / Apache    | start Apache + MySQL in XAMPP    | `http://localhost/binan_accesscard/public/`| CLI only    |
| `spark serve`     | `php spark serve --port 8090`    | `http://localhost:8090/`                   | **yes**     |
| Worker (once)     | `php spark queue:work`           | —                                          | **yes**     |
| Worker (schedule) | `./scripts/install-cron-worker.sh` | —                                        | **yes**     |

Whatever mode you run, keep `app.baseURL` in sync with the URL you're actually
using — see [`networking.md`](networking.md) for why that bites.
