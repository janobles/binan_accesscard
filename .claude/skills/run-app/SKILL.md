---
name: run-app
description: Use when starting, serving, or driving this app: running the dev server, using XAMPP, exposing it to another device, starting the background queue worker, or when the app will not boot. Covers the run modes, the PHP binary trap, the worker-count flag, and the login. For first-time setup of a fresh checkout, see docs/03-setup.md.
---

# Running the app

This covers starting an already-configured checkout. First-time setup, meaning
importing the dump and writing `.env`, is `docs/03-setup.md`.

## The fast path

```bash
PHP_CLI_SERVER_WORKERS=8 php spark serve --port 8090
```

Then `http://localhost:8090`, logging in as `developer` / `developer123`.

MySQL has to be running. Start it through XAMPP, or `mysql.server start`.

## Two traps, in the order they bite

**Use an intl-enabled PHP.** XAMPP's bundled command-line PHP usually ships
without `intl`, and `spark` needs it. On the main Mac the right binary is the
MacPorts build:

```bash
php -m | grep intl        # should print intl
/opt/local/bin/php spark serve --port 8090
```

If `spark` complains about a missing `intl` extension, you are on the wrong
binary. This is a `PATH` problem, not an application problem.

**Set the worker count.** PHP's built-in server runs one worker by default, so
every CSS file, image, and script queues behind the page. That alone adds seconds
to a dashboard load and looks exactly like a slow application. `PHP_CLI_SERVER_WORKERS=8`
fixes it.

On Windows the prefix syntax does not work; set it first:

```
set PHP_CLI_SERVER_WORKERS=8
php spark serve --port 8090
```

## The other modes

**XAMPP and Apache** is the deployment-parity mode. Start Apache and MySQL from
the control panel; the app lives at `http://localhost/binan_accesscard/public/`
because CodeIgniter's entry point is `public/`, not the repository root. Set
`app.baseURL` to match.

**Reachable from another device**, for testing the scanner on a phone, is
`docs/04-networking.md`. It is a Cloudflare tunnel or a port forward, plus the
`baseURL` change.

The `developer` / `developer123` login above is a local-development convenience
and is published in the handbook, the README, and the dump. Before exposing the
app beyond localhost by any route, change or disable the accounts that shipped
with the dump and confirm none of them still works.

## Keep `app.baseURL` in sync

Whatever mode you are in, `app.baseURL` in `.env` must match the URL actually
typed into the browser. CodeIgniter builds every link, asset path, and redirect
from it. When it is wrong the page still loads, then the CSS and JavaScript 404,
form posts bounce, and generated QR codes point at the wrong host.

If you switch modes, change `baseURL` in the same breath, then hard-refresh.

## If the task involves an import

Start the queue worker too. A large Excel import is queued rather than run in the
request, so without a worker it sits on "queued, waiting for worker" forever with
no error:

```bash
./scripts/queue-worker.sh          # drain once
```

`docs/05-background-worker.md` covers running it on a schedule and diagnosing a
stuck queue.

## Checking a checkout is healthy

```bash
php spark routes      # every route resolves to a real controller method
vendor/bin/phpunit
composer lint
```
