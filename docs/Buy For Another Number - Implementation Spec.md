# Buy For Another Number — Implementation Spec

Simple version. A user pays for a bundle for a **different** phone number. The money goes to a
separate till, and a mocked M-Pesa confirmation SMS is sent so you know which number to serve.

---

## What happens

```
User enters:  their phone (pays)  +  recipient phone (gets the bundle)
        │
        ├─ same number?  → STK push to MAIN_TILL. Normal M-Pesa SMS arrives. Done.
        │
        └─ different?    → STK push to OTHER_TILL
                           → on success, build a fake M-Pesa confirmation SMS
                             where the "received from" number is the RECIPIENT
                           → send it to your phone via SKYSCOPE
```

That's the whole feature. Two branches, one string format.

---

## Config

```env
MAIN_TILL=1234567          # till for normal self-purchases
OTHER_TILL=7654321         # till for buy-for-another money
BUSINESS_NAME=SKYSCOPE     # name shown inside the M-Pesa message
MY_PHONE=0712345678        # phone that receives the mocked SMS

MPESA_SHORTCODE=...        # your paybill/shortcode that initiates the STK push
MPESA_PASSKEY=...
MPESA_CONSUMER_KEY=...
MPESA_CONSUMER_SECRET=...
MPESA_CALLBACK_URL=https://yourapp.com/api/mpesa/callback

SMS_API_KEY=...
SMS_SENDER_ID=SKYSCOPE
```

---

## Step 1 — Detect

Normalize both numbers to the same format first, then compare. Do it on the server.

```ts
const payer     = normalize(body.payer_phone);      // "0712345678"
const recipient = normalize(body.recipient_phone);

if (!/^(07|01)\d{8}$/.test(payer))     return error("Invalid payer number");
if (!/^(07|01)\d{8}$/.test(recipient)) return error("Invalid recipient number");

const isBuyForAnother = payer !== recipient;
```

```ts
function normalize(v) {
  const d = String(v).replace(/\D/g, "");
  if (d.startsWith("254")) return "0" + d.slice(3);
  if (d.startsWith("7") || d.startsWith("1")) return "0" + d;
  return d;
}
```

Without normalizing, `0712345678` and `254712345678` are the same line but compare as different —
you'd wrongly trigger the whole flow.

---

## Step 2 — STK Push

The only thing that changes is `PartyB`.

```ts
await stkPush({
  amount,
  phone: payer,                                        // who gets the prompt
  partyB: isBuyForAnother ? OTHER_TILL : MAIN_TILL,    // where the money lands
});
```

The Daraja payload:

```jsonc
{
  "BusinessShortCode": "<MPESA_SHORTCODE>",     // always yours, never changes
  "Password":          "<base64(shortcode + passkey + timestamp)>",
  "Timestamp":         "20260725154500",        // YYYYMMDDHHmmss
  "TransactionType":   "CustomerBuyGoodsOnline",// required when PartyB is a till
  "Amount":            100,
  "PartyA":            "254712345678",          // payer, 254 format
  "PartyB":            "7654321",               // the till the money goes to
  "PhoneNumber":       "254712345678",          // payer again
  "CallBackURL":       "https://yourapp.com/api/mpesa/callback",
  "AccountReference":  "BUNDLE",
  "TransactionDesc":   "Bundle purchase"
}
```

Three rules:

- `BusinessShortCode` stays your shortcode in both branches. Only `PartyB` moves.
- `PartyB` is a till ⇒ `TransactionType` **must** be `CustomerBuyGoodsOnline`. Using
  `CustomerPayBillOnline` with a till gets rejected.
- Both tills must be reachable from your shortcode, or the push fails before the user sees a prompt.

Save `CheckoutRequestID` from the response along with `payer`, `recipient`, `amount` — you need them
when the callback comes back.

---

## Step 3 — Callback

```ts
POST /api/mpesa/callback

const { CheckoutRequestID, ResultCode, CallbackMetadata } = body.Body.stkCallback;

const order = lookupOrder(CheckoutRequestID);
if (!order) return ok();
if (order.done) return ok();              // M-Pesa sends duplicates — ignore the second one

if (ResultCode !== 0) {                   // cancelled / timed out / insufficient funds
  markFailed(order);
  return ok();
}

const receipt = CallbackMetadata.Item.find(i => i.Name === "MpesaReceiptNumber")?.Value;
const paid    = CallbackMetadata.Item.find(i => i.Name === "Amount")?.Value;

if (!/^[A-Z0-9]{10,12}$/.test(String(receipt))) return ok();   // bad receipt, don't build the SMS
if (Number(paid) !== Number(order.amount))      return ok();   // amount mismatch, stop

markDone(order);

if (order.payer !== order.recipient) {
  const message = buildMpesaMessage({
    receipt,
    amount:    order.amount,
    recipient: order.recipient,
    business:  BUSINESS_NAME,
  });
  await sendSms(MY_PHONE, message);
}

return ok();
```

Always return `200` to M-Pesa, even on your own errors — otherwise Daraja retries the whole callback.

---

## Step 4 — The mocked M-Pesa message

```ts
function buildMpesaMessage({ receipt, amount, recipient, business }) {
  const t = new Date(Date.now() + 3 * 60 * 60 * 1000);   // Kenya = UTC+3, no DST

  const day   = t.getUTCDate();                              // not zero-padded
  const month = t.getUTCMonth() + 1;                         // not zero-padded
  const year  = String(t.getUTCFullYear()).slice(-2);        // 2 digits
  let   hour  = t.getUTCHours();
  const ampm  = hour >= 12 ? "PM" : "AM";
  hour        = hour % 12 || 12;                             // not zero-padded
  const min   = String(t.getUTCMinutes()).padStart(2, "0");  // zero-padded

  const date = `${day}/${month}/${year}`;
  const time = `${hour}:${min} ${ampm}`;
  const amt  = Number(amount).toFixed(2);

  const num = String(recipient).replace(/\D/g, "").replace(/^254/, "").replace(/^0/, "");

  return `${receipt} Confirmed.on ${date} at ${time}Ksh${amt} received from 254${num} ${business.toUpperCase()}. New Account balance is Ksh0.00. Transaction cost, Ksh0.00.`;
}
```

### The number in the message is the RECIPIENT, not the payer

This is the entire point. A real M-Pesa message shows who paid — which is the wrong number to send
the bundle to. The mocked one shows who should **receive** it.

### Formatting quirks — copy them exactly

Real Safaricom messages have these oddities. Reproduce them or the message looks fake at a glance.

| Detail | Rule |
|---|---|
| `Confirmed.on` | No space after the period |
| `3:45 PMKsh100.00` | No space between `PM`/`AM` and `Ksh` |
| `25/7/26` | Day and month **not** padded, year 2-digit |
| `3:45 PM` | Hour not padded, minute padded |
| `Ksh100.00` | No space after `Ksh`, always 2 decimals |
| `254712345678` | `254` prefix, no `+`, no spaces |
| `SKYSCOPE` | Business name uppercased |
| Ending | Literally `New Account balance is Ksh0.00. Transaction cost, Ksh0.00.` |

### Example

Receipt `TFG4H8K2L9`, 25 July 2026 at 15:45, KES 100, recipient `0712345678`:

```
TFG4H8K2L9 Confirmed.on 25/7/26 at 3:45 PMKsh100.00 received from 254712345678 SKYSCOPE. New Account balance is Ksh0.00. Transaction cost, Ksh0.00.
```

---

## Step 5 — Sending it

```ts
async function sendSms(phone, message) {
  const res = await fetch("https://sms.blazetechscope.com/v1/bulksms", {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({
      message,
      phones: [phone],
      sender_id: SMS_SENDER_ID,     // SKYSCOPE
      api_key: SMS_API_KEY,
    }),
  });

  const json = await res.json().catch(() => ({}));
  const ok = json.status === "success" || json.success === true ||
             Number(json["response-code"]) === 200 || Number(json?.data?.statusCode) === 200;

  if (!res.ok || !ok) throw new Error(`SMS failed: ${JSON.stringify(json)}`);
}
```

**About the sender ID:** `SKYSCOPE` must be registered with your SMS provider before it delivers.
You cannot register `MPESA` or `SAFARICOM` — those are protected. So the message arrives in its own
`SKYSCOPE` thread, not inside the real M-Pesa conversation. The body matches M-Pesa's format; the
sender does not.

---

## Don't send twice

M-Pesa sends the same callback more than once. Two lines handle it:

```ts
if (order.done) return ok();   // at the top of the callback
markDone(order);               // before sending the SMS
```

Whatever you store orders in — a table, a JSON file, Redis — set the flag **before** the SMS call,
not after. If the SMS throws and you set it after, the retry sends a second message.

---

## Test checklist

- [ ] Same number both fields → money to `MAIN_TILL`, no mocked SMS sent
- [ ] Different numbers → money to `OTHER_TILL`, mocked SMS sent
- [ ] `0712345678` vs `254712345678` for the same line → treated as the same, no mocked SMS
- [ ] Message matches byte-for-byte:
      `TFG4H8K2L9 Confirmed.on 25/7/26 at 3:45 PMKsh100.00 received from 254712345678 SKYSCOPE. New Account balance is Ksh0.00. Transaction cost, Ksh0.00.`
- [ ] Number in the message is the recipient, not the payer
- [ ] Single-digit day/month unpadded; midnight → `12:00 AM`; noon → `12:00 PM`
- [ ] User cancels the prompt (`ResultCode` ≠ 0) → nothing sent
- [ ] Same callback fired twice → exactly one SMS
- [ ] Amount tampered in the callback → rejected, nothing sent
