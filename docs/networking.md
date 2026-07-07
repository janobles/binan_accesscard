# Exposing the app to the internet

Sometimes localhost isn't enough — a field tester on another network needs to hit
your machine, or you want to demo the QR scanner from a phone that isn't on your
Wi-Fi. Two ways to do it, from easiest to most involved.

Whichever you pick, remember the golden rule: **`app.baseURL` in `.env` must match
the URL people actually type in.** CodeIgniter builds every link, asset path, and
redirect off `baseURL`. If it's wrong, the page loads but CSS/JS 404s, form posts
bounce, and QR links point at `localhost`.

---

## Option 1 — Cloudflare Quick Tunnel (`trycloudflare`)

Best for: a quick share, a demo, testing on a phone. No router access needed, no
account, HTTPS for free. The catch: the URL is **random and temporary** — it dies
when you Ctrl-C, and you get a new one next time.

You need [`cloudflared`](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/)
installed. It already is on the main dev machine (`/opt/local/bin/cloudflared`).

### macOS / Linux

```bash
# 1. Start the app first (see running-the-system.md). Say it's on :8090.
# 2. Point a quick tunnel at it:
cloudflared tunnel --url http://localhost:8090
```

`cloudflared` prints a line like:

```
https://imaging-christina-furniture-pays.trycloudflare.com
```

Copy that URL into `.env`:

```ini
app.baseURL = 'https://imaging-christina-furniture-pays.trycloudflare.com'
```

Leave the tunnel running in its own terminal. Share the URL. Done.

### Windows

Same idea — install `cloudflared` (`winget install --id Cloudflare.cloudflared`
or grab the `.exe`), then in PowerShell:

```powershell
cloudflared tunnel --url http://localhost:8090
```

Copy the printed `*.trycloudflare.com` URL into `.env`'s `app.baseURL`.

> **Gotcha:** every restart mints a new URL, so you'll be editing `baseURL` each
> time. That's the price of the free quick tunnel. If you need a URL that sticks,
> set up a [named tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/)
> with a Cloudflare account and your own domain — out of scope here.

---

## Option 2 — Port forwarding (the "normal" way)

Best for: a semi-permanent setup on a network you control (office router, a box
that stays on). You expose your machine's port through the router so the public
internet can reach it at your WAN IP.

The shape is the same on both OSes:

1. **Pick the port the app listens on** (e.g. `8090` for `spark serve`, or `80`
   if you're running it through XAMPP/Apache).
2. **Give your machine a static LAN IP** (or a DHCP reservation) so the forward
   rule doesn't break when the IP changes.
3. **Add a port-forward rule on the router:** external port → your machine's LAN
   IP + internal port. This is router-specific (look for "Port Forwarding" /
   "Virtual Server" / "NAT" in the admin panel, usually at `192.168.1.1`).
4. **Open the OS firewall** for that port (below).
5. **Set `baseURL`** to `http://<your-public-IP>:<port>/`. Find your public IP at
   [ifconfig.me](https://ifconfig.me) or `curl ifconfig.me`.

### macOS — allow the port through the firewall

macOS's application firewall is per-app, not per-port. Easiest path: when you
first run `php spark serve` (or Apache), macOS pops "Do you want the application
`php` to accept incoming connections?" — click **Allow**. To check/re-add:

```bash
# List current rules
/usr/libexec/ApplicationFirewall/socketfilterfw --listapps

# Allow your php binary explicitly (adjust the path to your intl-enabled php)
sudo /usr/libexec/ApplicationFirewall/socketfilterfw --add /opt/local/bin/php
sudo /usr/libexec/ApplicationFirewall/socketfilterfw --unblockapp /opt/local/bin/php
```

### Windows — open the port in Windows Defender Firewall

```powershell
# Run as Administrator. Opens inbound TCP 8090.
New-NetFirewallRule -DisplayName "Binan AccessCard 8090" `
  -Direction Inbound -Protocol TCP -LocalPort 8090 -Action Allow
```

For XAMPP/Apache on port 80, swap `8090` for `80` (and expect your ISP may block
inbound 80 on residential lines — many do).

> **Security reality check:** port forwarding puts your dev box on the open
> internet. Only do it on a network you're allowed to, prefer a high non-standard
> port, and take the rule down when you're finished. For anything beyond a quick
> internal test, the Cloudflare tunnel is safer because it doesn't open your
> router at all.

---

## Which one should I use?

| Situation                                   | Use                          |
|---------------------------------------------|------------------------------|
| Quick demo / test on a phone / share a link | **Cloudflare Quick Tunnel**  |
| No router access / locked-down network      | **Cloudflare Quick Tunnel**  |
| Stable box on a router you control          | **Port forwarding**          |
| Need a URL that never changes + a domain    | Cloudflare *named* tunnel    |

After changing `baseURL`, do a hard refresh (assets are cached against the old
host). If links still point at the wrong host, confirm you edited `.env` and not
`env`, and that no `app.baseURL` is hard-set in `app/Config/App.php`.
