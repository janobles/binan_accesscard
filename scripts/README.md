# Background queue worker

Heavy work (a very large family Excel import today; exports/reports later) runs OFF
the web request through a generic job queue, so it never hits the request timeout /
memory limit and does **not** slow down other users. A web request only enqueues a
`job_queue` row; this worker drains it.

- **Worker:** [`queue-worker.ps1`](queue-worker.ps1) — drains the queue once and exits.
- **Installer:** [`install-cron-worker.ps1`](install-cron-worker.ps1) — registers the worker as a Scheduled Task.
- **Engine:** `php spark queue:work` (dispatches each job by `type` to its handler in `App\Config\Queue`).
- **Log:** `writable/logs/queue-worker.log`

## Install the worker as a scheduled task (Admin PowerShell)

```powershell
cd C:\xampp\htdocs\binan_accesscard
Set-ExecutionPolicy -Scope Process Bypass -Force
.\scripts\install-cron-worker.ps1 -EveryMinutes 1
```

This registers **BinanQueueWorker** as a Windows Scheduled Task that fires every
minute, drains the queue, and exits. It runs as **SYSTEM** (highest privileges) so it
works even when no one is logged in.

### Tuning options

| Option          | Default | Meaning                                                                 |
|-----------------|---------|-------------------------------------------------------------------------|
| `-EveryMinutes` | `1`     | Minutes between drains (ignored if `-At` is set).                       |
| `-At`           | —       | Nightly `HH:mm` local time; overrides the recurring schedule.           |
| `-Throttle`     | `250`   | Milliseconds paused between chunks — DB breathing room for other users. |
| `-Drainers`     | `1`     | Parallel drainers per fire. Claims are atomic so >1 is safe; keep at 1. |
| `-MaxSeconds`   | `50`    | Stop claiming new jobs after this many seconds (in-progress job finishes).|

```powershell
# Examples
.\scripts\install-cron-worker.ps1 -At 01:30          # nightly instead of every minute
.\scripts\install-cron-worker.ps1 -Throttle 500      # gentler on the database
.\scripts\install-cron-worker.ps1 -Uninstall         # remove the task
```

## Verify the worker is running

```powershell
Get-ScheduledTask BinanQueueWorker | Select TaskName, State
Get-ScheduledTaskInfo BinanQueueWorker | Select LastRunTime, LastTaskResult, NextRunTime

# Watch the log
Get-Content "C:\xampp\htdocs\binan_accesscard\writable\logs\queue-worker.log" -Tail 30 -Wait
```

## Run a drain by hand (no task)

```powershell
.\scripts\queue-worker.ps1                 # drain now, default 250ms throttle
php spark queue:work --throttle=250         # or call the engine directly
```

## Add a new background job type

1. Write a handler implementing `App\Jobs\JobHandlerInterface`.
2. Register it in `App\Config\Queue::$handlers` under a unique `type`.
3. Enqueue work: `(new \App\Models\Jobs\JobQueueModel())->enqueue('<type>', $payload, ...)`.

The worker dispatches by `type` automatically — no worker changes needed.
