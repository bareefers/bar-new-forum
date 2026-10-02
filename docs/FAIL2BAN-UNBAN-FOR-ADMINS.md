# How to unblock someone who can’t open the forum

**Who this is for:** BAR board / forum administrators (no server knowledge needed).

**What this fixes:** Someone is blocked from the site by our automatic scraper protection (fail2ban). They may see a blank page, timeout, or “can’t connect” — **not** a normal XenForo “you are banned” message.

**What this does *not* fix:** A normal forum ban you set in **Users**. Those are separate.

---

## Quick path (usual case)

### 1. Ask them for their IP address

Have them do this on the **same phone or computer and same Wi‑Fi/network** that can’t reach the forum:

1. Open a browser
2. Go to [https://www.whatismyip.com/](https://www.whatismyip.com/)
3. Copy the **IPv4** address (looks like `69.153.247.253`)
4. Send that number to you (email, Discord, text, etc.)

> Without that IP, you may unban the wrong address. A username search is only a hint.

### 2. Open the admin tool

1. Log into the forum as an administrator  
2. Open the Admin Control Panel: [https://bareefers.org/forum/admin.php](https://bareefers.org/forum/admin.php)  
3. Go to **Tools → Fail2ban IP unban**

(You need ACP **Ban** permission. If you don’t see the link, ask a super-admin.)

### 3. Look up and unban

1. Paste the **IP they sent you** into **Username or IP**
2. Click **Look up**
3. If the status says **Banned**, click **Unban IP**
4. You should see a green **Success** message at the top of the page

Ask them to try the forum again (sometimes a refresh or waiting ~30 seconds helps).

---

## If lookup says “Not banned”

Then fail2ban is probably not the problem. Next checks:

- Are they locked out with a XenForo ban? (**Users → Search for users**)
- Are they on a bad/VPN network? Ask them to try phone cellular vs Wi‑Fi
- Is the whole site down for everyone? Check yourself while logged out / another network

Escalate to a technical operator if the site is down for everyone.

---

## Optional: look up by username first

You *can* type their **forum username** instead of an IP. That shows recent IPs XenForo has seen for them.

Use that only as a starting point. **Always prefer the IP they get from whatismyip.com right now**, because:

- They may be on a different network than last login
- The blocked address may never have made it into XenForo’s log

---

## Message you can copy to the member

```
Sorry you’re locked out — our anti-bot shield sometimes blocks real people by mistake.

On the same device/network that can’t open bareefers.org, please open:
https://www.whatismyip.com/

Send me the IPv4 address shown there (like 12.34.56.78), and an admin can unblock it.
```

---

## For technical operators

Server CLI, jail config, and install notes: [../ops/fail2ban/README.md](../ops/fail2ban/README.md)
