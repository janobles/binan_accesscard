# Reaching the app from elsewhere

Sometimes localhost is not enough. A field tester on another network needs to hit
your machine, or you want to demo the QR scanner from a phone that is not on your
Wi-Fi. There are two ways to do it, and one rule that applies to both.

## The rule that bites everyone once

**`app.baseURL` in `.env` must match the URL people actually type.**

CodeIgniter builds every link, every asset path, and every redirect from
`baseURL`. When it is wrong the app does not fail cleanly. The page loads, and
then the CSS and JavaScript 404, form posts bounce, and the QR codes you generate
point at `localhost`, which means nothing to the phone scanning them.

If you change how the app is reached, change `baseURL` in the same breath.

## Option 1: Cloudflare quick tunnel

Best for a quick share, a demo, or testing on a phone. No router access, no
account, and HTTPS for free. The catch is that the URL is random and temporary:
it dies when you press Ctrl-C and you get a different one next time.

**A quick tunnel is public.** The URL is unguessable, not protected: anyone who
has it reaches your application, and the login page is the only thing between
them and the records. So use it for demos and device testing, against a database
holding test or synthetic families rather than real ones, and change the shipped
account passwords before you share the URL, because the development login is
published in this handbook. Stop the tunnel when you are done rather than leaving
it running.

For anything with real family data on the other side, this is not the tool.

You need [`cloudflared`](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/)
installed. It is already on the main development machine at
`/opt/local/bin/cloudflared`.

Start the app first, then point a tunnel at it:

```bash
cloudflared tunnel --url http://localhost:8090
```

`cloudflared` prints a URL:

```
https://imaging-christina-furniture-pays.trycloudflare.com
```

Put it in `.env`:

```ini
app.baseURL = 'https://imaging-christina-furniture-pays.trycloudflare.com'
```

Leave the tunnel running in its own terminal and share the URL.

On Windows it is the same, after `winget install --id Cloudflare.cloudflared` or
grabbing the executable:

```powershell
cloudflared tunnel --url http://localhost:8090
```

Every restart mints a new URL, so you will be editing `baseURL` each time. That
is the price of the free quick tunnel. If you need an address that sticks, a
[named tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/)
with a Cloudflare account and your own domain does that, and is out of scope
here.

## Option 2: port forwarding

Best for a semi-permanent setup on a network you control: an office router, a box
that stays on. You expose your machine's port through the router so the internet
can reach it at your WAN address.

The shape is the same on both platforms:

1. **Pick the port** the app listens on. `8090` for the dev server, or `80` if
   you are running through XAMPP and Apache.
2. **Give the machine a static LAN address**, or a DHCP reservation, so the
   forwarding rule does not break when the address changes.
3. **Add the port-forward rule** on the router: external port to your machine's
   LAN address and internal port. This is router-specific; look for "Port
   Forwarding", "Virtual Server", or "NAT" in the admin panel, usually at
   `192.168.1.1`.
4. **Open the firewall** for that port.
5. **Set `baseURL`** to the address people will type. Find your public address
   with `curl ifconfig.me`.

**Serve it over HTTPS with a real certificate, or keep it on a VPN.** Plain
`http://` over the internet sends session cookies and every record in clear text,
readable by anything between the browser and your router. If you cannot terminate
TLS with a trusted certificate, either put the whole thing behind a VPN so it is
never publicly reachable, or use the Cloudflare tunnel above, which gives you
HTTPS without opening anything.

### Opening the firewall on macOS

The macOS application firewall works per application, not per port. The easy path
is to click **Allow** when macOS asks whether `php` should accept incoming
connections. To check or re-add it:

```bash
/usr/libexec/ApplicationFirewall/socketfilterfw --listapps

sudo /usr/libexec/ApplicationFirewall/socketfilterfw --add /opt/local/bin/php
sudo /usr/libexec/ApplicationFirewall/socketfilterfw --unblockapp /opt/local/bin/php
```

Adjust the path to whichever intl-enabled PHP you are actually running.

### Opening the firewall on Windows

```powershell
# Run as Administrator. Opens inbound TCP 8090.
New-NetFirewallRule -DisplayName "Binan AccessCard 8090" `
  -Direction Inbound -Protocol TCP -LocalPort 8090 -Action Allow
```

For Apache on port 80, swap `8090` for `80`, and expect that your ISP may block
inbound port 80 on a residential line. Many do.

### Before you forward a port

Port forwarding puts the machine on the open internet, where it will be found and
probed within hours whether or not anyone was told about it. Only do it on a
network you are allowed to, and take the rule down when you are finished.

A high non-standard port is not a security measure. It reduces log noise from
scanners and nothing else; port scans enumerate the whole range. What actually
protects the system is TLS, changed default passwords, and not being publicly
reachable in the first place.

For anything beyond a quick internal test the Cloudflare tunnel is the safer
choice, because it opens nothing on your router at all.

## Which one

| Situation | Use |
|---|---|
| Quick demo, testing on a phone, sharing a link | Cloudflare quick tunnel |
| No router access, or a locked-down network | Cloudflare quick tunnel |
| A stable box on a router you control | Port forwarding |
| A URL that never changes, with your own domain | Cloudflare named tunnel |

## When it still does not work

After changing `baseURL`, do a hard refresh. Assets are cached against the old
host and a normal reload will keep serving them.

If links still point at the wrong host, check two things: that you edited `.env`
and not `env`, and that nothing has hard-set `app.baseURL` in
`app/Config/App.php`.
