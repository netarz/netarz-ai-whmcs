# Changelog

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
