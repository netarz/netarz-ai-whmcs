# Changelog

## 1.1.0 — 2026-10-31

**Fixed**
- Products sold out under WHMCS Stock Control were still offered with their order link. The assistant now sees them marked
  OUT OF STOCK, with no price and no order link, and a hard rule tells it never to sell or link them, never to promise a
  restock date, and to suggest an in-stock alternative instead. Add-ons of sold-out products are left out too. Reported by a
  user on 2026-10-31.

**Added**
- *Out-of-stock products* setting: tell the assistant they are out of stock (default), or leave them out entirely.
- "Only N left" for products with five or fewer in stock.
- *Products the assistant never offers*: a list of product IDs kept out of the knowledge.
- Product add-ons (shown on the order form) with prices and the products they belong to.
- Prices in the signed-in client's own currency when the shop has prices in it.
- Pending orders of the signed-in client in the client context ("where is my order?"); the order IP is never copied.
- Suggested questions: up to four starter questions under the greeting, sent with one tap.
- Chat ratings: "Did this chat help you?" after the first answer. A "no" adds a note to the conversation, raises its unread
  count, and shows as a pill in the inbox; the dashboard shows 30-day satisfaction.
- API address choice: gisoo.pro, ir.gisoo.pro (for servers in Iranian data centres) or a custom address. An address typed in
  under 1.0 is kept.
- The Gisoo edition (`gisoo-ai-whmcs-<version>.zip`, module `gisoo_ai`), built from the same source by `./build.sh gisoo` with
  its own module name, namespace and tables, so both editions can be installed side by side. `./build.sh test-gisoo` runs the
  whole suite against it.

**Changed**
- Every brand name, link and key prefix comes from one place (`Brand`), so the two editions never name each other.
- 159 unit/integration tests (was 141) and 59 browser checks (was 50).


## 1.0.2 — 2026-09-27

- The chat header under the assistant's name now says «دستیار هوش مصنوعی» / "AI assistant" instead of promising a reply time.

## 1.0.1 — 2026-09-27

- Links from the WHMCS admin back to netarz.ir (key, top-up, docs, panel, low-credit email) carry
  `utm_source=whmcs-plugin` tags, so NetArz can see which sign-ups and top-ups come from installs.
- Test suite: the hostile-input browser check runs last, keeping documentation screenshots clean.

## 1.0.0 — 2026-09-27

First public release.

- AI live-chat widget for the WHMCS client area: English and Persian (RTL), burst-aware replies, typing indicator,
  seen ticks, guest name/email step, conversation kept across page loads, full screen on phones, colour and position settings.
- Handover to staff with reason; ticket offer when nobody answers in time; chat-to-ticket with the full transcript.
- Staff inbox in the admin area: filters, take-over / hand-back, close, delete, and a "visitor is waiting" alert on every admin page.
- AI ticket replies: off, drafts for staff (default) or automatic above a confidence threshold, per-department, delayed or
  instant, auto-reply cap, internal note on handoff, steps back once staff reply, pause per ticket.
- Ticket-page panel: write / rewrite a draft, put it in the WHMCS reply box, dismiss.
- Knowledge from the owner's notes, public knowledgebase, published announcements, visible products and prices, domain
  prices, open network issues and the signed-in client's own account; Persian questions find English articles.
- NetArz AI credit on the module dashboard and the admin home widget; daily spending cap, minimum balance, low-credit email,
  per-request log with request IDs.
- Guards: JSON output contract, malformed output never shown, silence only for a closing goodbye, reply language pinned to
  the customer's, password-sharing warning, never a promised phone call.
- 140 unit/integration tests, 50 browser checks, a 15-scenario real-model evaluation.
