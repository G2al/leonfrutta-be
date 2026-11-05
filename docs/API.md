# LeonFrutta — API pubblica

Documentazione dell'API REST usata dalla PWA (Next.js) per inviare le richieste d'ordine e consultarne lo stato.

- **Versione:** v1
- **Autenticazione:** nessuna. I clienti non si registrano: ogni ordine si consulta con il suo codice segreto (`tracking_token`).
- **Formato:** JSON (UTF-8)

## Endpoint disponibili

| Metodo | URL | Uso | Limite |
|---|---|---|---|
| `POST` | `/api/v1/orders` | Invia una nuova richiesta d'ordine | 5 al minuto per IP |
| `GET` | `/api/v1/orders/{tracking_token}` | Consulta una richiesta: stato, importo, pagamento | 60 al minuto per IP |

Non esistono endpoint pubblici per elencare, modificare, confermare, pagare o cancellare gli ordini: queste operazioni le fa solo l'amministratore dal pannello `/admin`.

### Header

| Header | Valore | Quando |
|---|---|---|
| `Accept` | `application/json` | Sempre (così anche gli errori arrivano in JSON) |
| `Content-Type` | `application/json` | Solo nella `POST` |

---

## L'oggetto ordine

Entrambi gli endpoint restituiscono l'ordine in `data`, con questa forma:

```json
{
  "id": 12,
  "tracking_token": "01a1a105-4c31-708e-961a-11cad464f992",
  "status": "confirmed",
  "status_label": "Confermato",
  "business_name": "Pizzeria Da Mario",
  "message": "Buongiorno, vorrei ordinare 10 kg di pomodori e 5 kg di patate.",
  "amount": "45.50",
  "payment_status": "unpaid",
  "payment_status_label": "Da pagare",
  "created_at": "2026-11-03T09:30:00+00:00",
  "confirmed_at": "2026-11-03T10:05:00+00:00",
  "cancelled_at": null,
  "paid_at": null
}
```

| Campo | Tipo | Descrizione |
|---|---|---|
| `id` | number | Numero della richiesta, da mostrare al cliente ("Richiesta n. 12"). **Non** serve a consultarla. |
| `tracking_token` | string (UUID) | Codice segreto della richiesta. È l'unico modo per consultarla: va conservato. |
| `status` | `new` \| `confirmed` \| `cancelled` | Stato della richiesta |
| `status_label` | string | Etichetta in italiano: `Nuovo`, `Confermato`, `Annullato` |
| `business_name` | string | Attività indicata dal cliente |
| `message` | string | Testo dell'ordine inviato dal cliente |
| `amount` | string \| null | Importo in euro con due decimali e il punto come separatore (`"45.50"`). `null` finché la richiesta non è confermata |
| `payment_status` | `paid` \| `unpaid` \| null | Pagamento. `null` se la richiesta non è confermata |
| `payment_status_label` | string \| null | `Pagato`, `Da pagare` oppure `null` |
| `created_at` | string ISO 8601 | Data di invio |
| `confirmed_at` | string \| null | Data di conferma |
| `cancelled_at` | string \| null | Data di annullamento |
| `paid_at` | string \| null | Data del pagamento |

Note:

- Tutte le date sono in **UTC**: il frontend le converte nell'ora italiana.
- `amount` è una **stringa**, per non perdere precisione sui centesimi. Per mostrarlo usa ad esempio `Intl.NumberFormat("it-IT", { style: "currency", currency: "EUR" })`.
- Il numero di telefono del cliente non viene mai restituito.

### Ciclo di vita

```
new (Nuovo) ──► confirmed (Confermato) ──► pagamento: unpaid (Da pagare) ⇄ paid (Pagato)
     │
     └────────► cancelled (Annullato)
```

- Alla conferma l'amministratore inserisce sempre l'importo. Lo può correggere finché l'ordine non risulta pagato.
- Un pagamento segnato per errore può essere rimosso: `paid` può quindi tornare `unpaid`.
- Una richiesta annullata non ha importo né pagamento.

---

## Invio di una richiesta d'ordine

`POST /api/v1/orders`

Il cliente invia nome, attività, telefono e il testo libero dell'ordine. La richiesta viene salvata con stato `new`. L'invio **non** fa partire nessun messaggio WhatsApp, email o SMS.

### Payload

```json
{
  "first_name": "Mario",
  "last_name": "Rossi",
  "business_name": "Pizzeria Da Mario",
  "phone": "3331234567",
  "message": "Buongiorno, vorrei ordinare 10 kg di pomodori, 5 kg di patate, 3 cassette di insalata e 2 kg di limoni."
}
```

Tutti i campi sono **obbligatori**. Gli spazi a inizio e fine vengono rimossi prima della validazione: un campo con soli spazi è considerato vuoto.

| Campo | Tipo | Regole |
|---|---|---|
| `first_name` | string | massimo 100 caratteri |
| `last_name` | string | massimo 100 caratteri |
| `business_name` | string | massimo 150 caratteri |
| `phone` | string | massimo 25 caratteri, numero valido (vedi sotto) |
| `message` | string | da 5 a 3000 caratteri, a capo consentiti |

I campi `status`, `confirmed_at` e `cancelled_at` **non** possono essere inviati: se presenti la richiesta viene rifiutata con `422`. Gli altri campi non previsti, compresi `amount` e `paid_at`, vengono ignorati.

#### Formati di telefono accettati

Il numero si invia sempre come **stringa**. Spazi, punti, trattini, barre e parentesi sono ammessi.

| Inserito dal cliente | Salvato come |
|---|---|
| `3331234567` | `+393331234567` |
| `333 123 4567` | `+393331234567` |
| `+39 333 1234567` | `+393331234567` |
| `0039 333 1234567` | `+393331234567` |
| `06 1234 5678` | `+390612345678` |
| `+44 7911 123456` | `+447911123456` |

I numeri senza prefisso sono trattati come italiani (`+39`). Per i numeri esteri serve il prefisso con `+` o `00`.

### 201 Created

```json
{
  "data": {
    "id": 1,
    "tracking_token": "01a1a105-4c31-708e-961a-11cad464f992",
    "status": "new",
    "status_label": "Nuovo",
    "business_name": "Pizzeria Da Mario",
    "message": "Buongiorno, vorrei ordinare 10 kg di pomodori, 5 kg di patate, 3 cassette di insalata e 2 kg di limoni.",
    "amount": null,
    "payment_status": null,
    "payment_status_label": null,
    "created_at": "2026-11-03T09:30:00+00:00",
    "confirmed_at": null,
    "cancelled_at": null,
    "paid_at": null
  },
  "success": true,
  "message": "La tua richiesta è stata inviata con successo."
}
```

**Il frontend deve salvare `tracking_token`**, ad esempio in `localStorage`: senza il codice non è possibile consultare la richiesta in seguito.

---

## Consultazione di una richiesta

`GET /api/v1/orders/{tracking_token}`

Restituisce lo stato aggiornato della richiesta: se è stata confermata o annullata, l'importo e se è stata pagata.

### 200 OK

```json
{
  "data": { "...": "oggetto ordine, vedi sopra" },
  "success": true,
  "message": "Dettagli della richiesta."
}
```

### 404 Not Found

Il codice non esiste, oppure è stato usato l'`id` numerico al posto del codice:

```json
{ "success": false, "message": "La risorsa richiesta non esiste." }
```

Se un codice salvato restituisce `404`, il frontend può rimuoverlo dall'elenco locale.

---

## Errori

Tutte le risposte di errore sotto `/api` hanno la forma `{ "success": false, "message": "..." }`. Non contengono mai stack trace o dettagli interni.

### 422 Unprocessable Entity: dati non validi (solo `POST`)

```json
{
  "success": false,
  "message": "I dati inviati non sono validi. Controlla i campi evidenziati.",
  "errors": {
    "phone": ["Il campo telefono non contiene un numero di telefono valido."],
    "message": ["Il campo richiesta è obbligatorio."]
  }
}
```

`errors` contiene un elenco di messaggi in italiano per ogni campo, pronti da mostrare sotto l'input corrispondente.

### 429 Too Many Requests: troppe richieste

```json
{
  "success": false,
  "message": "Hai inviato troppe richieste. Riprova tra qualche istante."
}
```

L'header `Retry-After` indica dopo quanti secondi si può riprovare.

### 500 Internal Server Error: errore inatteso

```json
{
  "success": false,
  "message": "Si è verificato un errore inatteso. Riprova più tardi."
}
```

L'errore viene scritto nei log di Laravel.

Altri codici possibili: `404` per un URL o un codice inesistente, `405` per un metodo non consentito.

---

## Esempi con Windows PowerShell

Invio di una richiesta:

```powershell
$body = @{
    first_name    = "Mario"
    last_name     = "Rossi"
    business_name = "Pizzeria Da Mario"
    phone         = "333 123 4567"
    message       = "Buongiorno, vorrei ordinare 10 kg di pomodori e 5 kg di patate."
} | ConvertTo-Json

$response = Invoke-RestMethod `
    -Uri "http://127.0.0.1:8000/api/v1/orders" `
    -Method Post `
    -ContentType "application/json; charset=utf-8" `
    -Headers @{ Accept = "application/json" } `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($body))

$response.data.tracking_token
```

Il body viene convertito in byte UTF-8 per non perdere le lettere accentate su Windows PowerShell 5.1.

Consultazione della stessa richiesta:

```powershell
Invoke-RestMethod `
    -Uri "http://127.0.0.1:8000/api/v1/orders/$($response.data.tracking_token)" `
    -Headers @{ Accept = "application/json" }
```

---

## Integrazione con Next.js

Configura l'URL del backend in una variabile d'ambiente del frontend, ad esempio in `.env.local`:

```bash
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000
```

Esempio di client:

```ts
type OrderStatus = "new" | "confirmed" | "cancelled";
type PaymentStatus = "paid" | "unpaid";

export type Order = {
  id: number;
  tracking_token: string;
  status: OrderStatus;
  status_label: string;
  business_name: string;
  message: string;
  amount: string | null;
  payment_status: PaymentStatus | null;
  payment_status_label: string | null;
  created_at: string;
  confirmed_at: string | null;
  cancelled_at: string | null;
  paid_at: string | null;
};

export type OrderPayload = {
  first_name: string;
  last_name: string;
  business_name: string;
  phone: string;
  message: string;
};

const API_URL = process.env.NEXT_PUBLIC_API_URL;

export async function submitOrder(payload: OrderPayload) {
  const response = await fetch(`${API_URL}/api/v1/orders`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });

  const body = await response.json();

  if (response.status === 201) {
    return { ok: true as const, order: body.data as Order, message: body.message };
  }

  if (response.status === 422) {
    return { ok: false as const, fieldErrors: body.errors as Record<string, string[]>, message: body.message };
  }

  // 429, 500 e altri errori: mostra il messaggio generico ricevuto.
  return { ok: false as const, message: body.message ?? "Errore di rete, riprova." };
}

export async function fetchOrder(trackingToken: string) {
  const response = await fetch(`${API_URL}/api/v1/orders/${encodeURIComponent(trackingToken)}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  if (response.status === 404) {
    return { ok: false as const, notFound: true as const };
  }

  const body = await response.json();

  return response.ok
    ? { ok: true as const, order: body.data as Order }
    : { ok: false as const, message: body.message as string };
}
```

Consigli:

- disabilita il pulsante di invio mentre la richiesta è in corso, così il cliente non lo preme due volte;
- con `422` mostra `errors[campo][0]` sotto ogni input;
- con `429` invita il cliente ad aspettare qualche istante;
- non servono cookie né token: non usare `credentials: "include"`;
- non mettere in cache le risposte della `GET`: lo stato cambia quando l'amministratore conferma o registra il pagamento.

---

## Configurazione CORS

Le origini che possono chiamare l'API si impostano nel file `.env` del backend, **senza modificare il codice**:

```bash
# Sviluppo (valore predefinito se la variabile non è impostata)
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000

# Produzione
CORS_ALLOWED_ORIGINS=https://ordini.leonfrutta.it
```

- Indica più origini separandole con una virgola, senza `/` finale.
- Il CORS vale solo per i percorsi `api/*`. Sono ammessi i metodi `GET`, `POST` e `OPTIONS` e gli header `Accept`, `Content-Type` e `X-Requested-With`.
- Gli header `Retry-After` e `X-RateLimit-*` sono leggibili dal frontend.
- Dopo aver modificato `.env` in produzione esegui `php artisan config:clear`, oppure di nuovo `php artisan config:cache` se usi la cache della configurazione.

La configurazione si trova in `config/cors.php`.

---

## Altre impostazioni (`.env`)

| Variabile | Predefinito | Descrizione |
|---|---|---|
| `LEONFRUTTA_ORDERS_RATE_LIMIT` | `5` | Invii al minuto consentiti per IP |
| `LEONFRUTTA_ORDERS_TRACKING_RATE_LIMIT` | `60` | Consultazioni al minuto consentite per IP |
| `LEONFRUTTA_WHATSAPP_COUNTRY_CODE` | `39` | Prefisso aggiunto ai numeri inseriti senza prefisso |
| `LEONFRUTTA_WHATSAPP_MAX_SUMMARY_LENGTH` | `1000` | Lunghezza massima del testo dell'ordine dentro il messaggio WhatsApp precompilato |
| `LEONFRUTTA_DISPLAY_TIMEZONE` | `Europe/Rome` | Fuso orario delle date nel pannello admin e dei calcoli degli incassi. Nel database le date sono salvate in UTC |
| `LEONFRUTTA_COMPANY_NAME` | `LeonFrutta` | Nome mostrato nel pannello e nel messaggio WhatsApp |
