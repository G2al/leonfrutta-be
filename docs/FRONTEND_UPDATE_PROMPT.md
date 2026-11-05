# PROMPT — AGGIORNAMENTO FRONTEND LEONFRUTTA: STATO, IMPORTO E PAGAMENTO

## 1. CONTESTO

Stai lavorando sulla PWA Next.js di **LeonFrutta**, già sviluppata seguendo il prompt iniziale (`FRONTEND_PROMPT.md`).

Il backend Laravel è stato aggiornato. Ora l'amministratore:

1. **conferma** una richiesta indicando sempre l'**importo** dell'ordine in euro;
2. può **correggere l'importo** finché l'ordine non risulta pagato;
3. segna l'ordine come **pagato** (o lo riporta a "da pagare" se ha sbagliato).

Il cliente deve poter vedere queste informazioni dalla PWA: stato della richiesta, importo e se risulta pagata.

**ATTENZIONE: devi aggiornare soltanto il frontend.** Il backend è già pronto e testato: non modificarlo e non inventare campi o endpoint diversi da quelli descritti qui.

Prima di modificare il progetto, ispezionalo e riusa la struttura esistente (client API, componenti, stili).

Tutta l'interfaccia resta in **italiano**.

---

## 2. COSA CAMBIA RISPETTO AL PROMPT INIZIALE

Queste indicazioni del prompt iniziale **non valgono più**:

- ~~"`POST /api/v1/orders` è l'unico endpoint disponibile"~~: ora esiste anche un endpoint di **consultazione** (`GET`).
- ~~"Non sviluppare lo storico ordini o il tracking dello stato"~~: ora va sviluppata la consultazione delle richieste inviate.

Tutto il resto resta valido: il modulo d'invio, la validazione, la gestione degli errori, la PWA, nessun login.

L'invio (`POST`) funziona esattamente come prima. La risposta contiene però **più campi**, tra cui il `tracking_token`.

---

## 3. IL CODICE SEGRETO DELLA RICHIESTA (`tracking_token`)

Ogni richiesta ha un codice segreto (UUID), restituito nella risposta `201` della `POST`:

```json
{
  "data": {
    "id": 12,
    "tracking_token": "01a1a105-4c31-708e-961a-11cad464f992",
    "status": "new",
    "...": "..."
  },
  "success": true,
  "message": "La tua richiesta è stata inviata con successo."
}
```

- È l'**unico** modo per consultare una richiesta. L'`id` numerico serve solo da mostrare al cliente ("Richiesta n. 12"): con l'`id` la `GET` risponde `404`.
- Dopo ogni invio riuscito **salva il codice in `localStorage`**, in un elenco "Le mie richieste". Per ogni voce bastano: `tracking_token`, `id`, `business_name`, `created_at`.
- **Non salvare** il testo dell'ordine né il telefono in `localStorage`: il testo si rilegge dall'API.
- Il codice va trattato come una chiave: non inviarlo a strumenti di analytics, log o servizi di terze parti.
- Il cliente non ha un account: le richieste sono visibili **solo sul dispositivo e sul browser** da cui le ha inviate. Spiegalo con una nota breve e chiara nella pagina "Le mie richieste".
- Gestisci `localStorage` con `try/catch`: in navigazione privata può non essere disponibile. In quel caso la PWA deve continuare a funzionare e mostrare il numero della richiesta nella schermata di conferma.

---

## 4. NUOVO ENDPOINT DI CONSULTAZIONE

```
GET {NEXT_PUBLIC_API_URL}/api/v1/orders/{tracking_token}
Accept: application/json
```

Nessuna autenticazione e nessun cookie. Limite: 60 richieste al minuto per indirizzo IP.

### 200 OK

```json
{
  "data": {
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
  },
  "success": true,
  "message": "Dettagli della richiesta."
}
```

### 404 Not Found

```json
{ "success": false, "message": "La risorsa richiesta non esiste." }
```

Il codice non esiste più o non è valido: **rimuovi la voce** dall'elenco locale e informa il cliente con un messaggio discreto.

### 429 e 500

Stessa forma e stessa gestione già usate per la `POST`.

### Tipi TypeScript

Aggiorna i tipi esistenti con l'oggetto ordine completo. Questo stesso oggetto arriva sia nella `POST` sia nella `GET`:

```ts
type OrderStatus = "new" | "confirmed" | "cancelled";
type PaymentStatus = "paid" | "unpaid";

export type Order = {
  id: number;
  tracking_token: string;
  status: OrderStatus;
  status_label: string;          // "Nuovo" | "Confermato" | "Annullato"
  business_name: string;
  message: string;
  amount: string | null;         // "45.50", null finché non è confermata
  payment_status: PaymentStatus | null; // null se non è confermata
  payment_status_label: string | null;  // "Pagato" | "Da pagare" | null
  created_at: string;            // ISO 8601, UTC
  confirmed_at: string | null;
  cancelled_at: string | null;
  paid_at: string | null;
};
```

Aggiungi al client API esistente una funzione `fetchOrder(trackingToken)`. Come `submitOrder`, deve restituire un risultato discriminato senza lanciare eccezioni verso la UI: trovato, non trovato, troppe richieste, errore generico, errore di rete.

Usa `encodeURIComponent` sul codice e `cache: "no-store"`: lo stato cambia quando l'amministratore agisce.

---

## 5. REGOLE DI VISUALIZZAZIONE

### Stato della richiesta

| `status` | Etichetta | Colore suggerito | Testo per il cliente |
|---|---|---|---|
| `new` | Nuovo | giallo/arancio | "In attesa di conferma da parte di LeonFrutta." |
| `confirmed` | Confermato | verde | "Richiesta confermata. LeonFrutta ti contatterà su WhatsApp." |
| `cancelled` | Annullato | rosso | "Questa richiesta è stata annullata. Per informazioni contatta LeonFrutta." |

Usa le etichette `status_label` e `payment_status_label` restituite dall'API, così restano allineate al pannello amministrativo.

### Importo

- `amount` è una **stringa** con il punto come separatore (`"45.50"`). Non fare calcoli: formattala solo per la visualizzazione con `Intl.NumberFormat("it-IT", { style: "currency", currency: "EUR" })` → `45,50 €`.
- Mostra l'importo **solo** se lo stato è `confirmed`.
- Indica che l'importo è definito da LeonFrutta e può essere aggiornato fino al pagamento, ad esempio: "Importo comunicato da LeonFrutta".

### Pagamento

- Mostralo **solo** se lo stato è `confirmed` (negli altri casi `payment_status` è `null`).
- `unpaid` → badge "Da pagare"; `paid` → badge "Pagato" con la data `paid_at`.
- Un ordine può tornare da `paid` a `unpaid` se l'amministratore aveva sbagliato: la UI deve mostrare semplicemente lo stato attuale.

### Date

Tutte le date arrivano in **UTC**: mostrale nell'ora italiana (`Europe/Rome`), in un formato leggibile come "3 novembre 2026, 11:05".

---

## 6. NUOVE SCHERMATE E MODIFICHE

### Schermata di conferma dopo l'invio (modifica)

- Continua a mostrare il messaggio di successo e il numero della richiesta (`data.id`).
- Aggiungi un pulsante "Segui la tua richiesta" che porta alla pagina di dettaglio.
- Ricorda al cliente che può ritrovare la richiesta in "Le mie richieste" da questo dispositivo.

### Pagina "Le mie richieste" (nuova)

- Elenco delle richieste salvate in `localStorage`, dalla più recente.
- Per ogni richiesta carica lo stato aggiornato con `fetchOrder`. Mostra numero, attività, data di invio, badge di stato e, se confermata, importo e badge di pagamento.
- Stato vuoto chiaro, con un pulsante verso il modulo d'ordine.
- Pulsante "Aggiorna" e aggiornamento automatico quando la pagina torna in primo piano (`visibilitychange`).
- **Nessun polling aggressivo**: al massimo un aggiornamento ogni 60 secondi mentre la pagina è visibile, rispettando il limite di 60 richieste al minuto.
- Possibilità di **rimuovere** una richiesta dall'elenco locale (rimuove solo il codice dal dispositivo, non annulla nulla). Spiegalo nel testo di conferma.

### Pagina di dettaglio della richiesta (nuova)

Ad esempio `/richieste/[token]`:

- numero richiesta, attività, data di invio;
- stato, con il testo esplicativo della tabella al paragrafo 5;
- se confermata: importo in evidenza, stato del pagamento, data di conferma ed eventuale data di pagamento;
- se annullata: data di annullamento;
- testo dell'ordine inviato, mantenendo gli a capo (`white-space: pre-line`) e **senza** interpretarlo come HTML;
- stati di caricamento, errore e "richiesta non trovata".

Il pannello di navigazione della PWA deve permettere di passare facilmente tra "Nuovo ordine" e "Le mie richieste".

---

## 7. PWA E CACHE

- Le chiamate `GET /api/v1/orders/...` **non** vanno messe in cache dal service worker, come già vale per la `POST`.
- Se il dispositivo è offline, la pagina "Le mie richieste" mostra l'elenco locale con un avviso ("Sei offline: lo stato potrebbe non essere aggiornato"). Non mostrare dati inventati.

---

## 8. COSA NON DEVI SVILUPPARE

- Pagamenti online o pulsanti "Paga ora": il pagamento viene solo registrato dall'amministratore.
- Modifica o annullamento della richiesta da parte del cliente: non esistono endpoint per farlo.
- Calcoli di prezzi, totali o sconti lato frontend.
- Visualizzazione del numero di telefono: l'API non lo restituisce.
- Login, account o sincronizzazione delle richieste tra dispositivi.
- Notifiche push, email o WhatsApp.
- Qualsiasi modifica al backend Laravel.

---

## 9. TEST

Aggiungi o aggiorna i test automatici almeno per:

- `fetchOrder`: risposte 200, 404, 429, 500 ed errore di rete;
- salvataggio del `tracking_token` dopo un invio riuscito e rimozione dopo un `404`;
- visualizzazione corretta di importo e pagamento per ciascuno stato (`new`, `confirmed` non pagato, `confirmed` pagato, `cancelled`);
- formattazione dell'importo (`"1234.50"` → `1.234,50 €`) e delle date in ora italiana;
- funzionamento della PWA quando `localStorage` non è disponibile.

### Test manuale con il backend locale

Avvia il backend (`php artisan serve`, su `http://127.0.0.1:8000`) e il frontend sulla porta **3000**, poi:

1. invia una richiesta dalla PWA → compare in "Le mie richieste" come **Nuovo**;
2. nel pannello `http://127.0.0.1:8000/admin` confermala con un importo → nella PWA risulta **Confermato**, con l'importo e "Da pagare";
3. nel pannello correggi l'importo → la PWA mostra il nuovo importo;
4. nel pannello segnala come pagata → la PWA mostra "Pagato" e la data;
5. annulla un'altra richiesta nuova → la PWA mostra **Annullato**, senza importo;
6. apri un codice inesistente → messaggio "richiesta non trovata" e rimozione dall'elenco.

---

## 10. REPORT FINALE

Al termine indica:

- le modifiche e le nuove schermate realizzate;
- i file principali toccati;
- l'esito dei test automatici e manuali;
- eventuali limiti rimasti (ad esempio la visibilità delle richieste solo sul dispositivo d'invio).

Per qualsiasi dubbio sul contratto API, fai riferimento al file `API.md` del backend: **non inventare campi, endpoint o comportamenti**.
