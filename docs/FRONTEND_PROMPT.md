# PROMPT — LEONFRUTTA FRONTEND (PWA NEXT.JS)

## 1. CONTESTO E OBIETTIVO

Sei un Senior Frontend Developer esperto di Next.js, React, TypeScript e Progressive Web App.

Devi sviluppare il **frontend** di **LeonFrutta**, un'azienda ortofrutticola che oggi riceve gli ordini dei clienti (pizzerie, ristoranti, bar, negozi) tramite messaggi WhatsApp.

Vogliamo che i clienti inviino le richieste d'ordine da una PWA semplice e veloce, pensata soprattutto per smartphone.

**ATTENZIONE: devi sviluppare soltanto il frontend.** Il backend Laravel è già pronto, testato e funzionante. Non modificarlo e non replicarne la logica.

Se il progetto Next.js esiste già, ispezionalo prima di modificarlo e non reinstallare ciò che è presente. Se non esiste, crealo con Next.js (App Router) e TypeScript.

Tutta l'interfaccia deve essere in **italiano**.

---

## 2. COME FUNZIONA IL SISTEMA

1. Il cliente apre la PWA, **senza registrarsi e senza login**.
2. Compila un modulo con i suoi dati e il testo libero dell'ordine.
3. Il frontend invia i dati al backend con una chiamata `POST`.
4. Il backend salva la richiesta con stato "Nuovo".
5. L'amministratore di LeonFrutta la vede nel pannello amministrativo, la conferma e contatta il cliente su WhatsApp **manualmente**.

Il frontend **non** invia messaggi WhatsApp, email o SMS e non apre WhatsApp. La comunicazione successiva la gestisce l'azienda.

---

## 3. IL MODULO D'ORDINE

Una sola schermata principale con questi campi, tutti obbligatori:

| Campo UI | Chiave API | Regole |
|---|---|---|
| Nome | `first_name` | testo, max 100 caratteri |
| Cognome | `last_name` | testo, max 100 caratteri |
| Nome dell'attività | `business_name` | testo, max 150 caratteri |
| Telefono | `phone` | stringa, max 25 caratteri |
| La tua richiesta | `message` | testo libero multiriga, da 5 a 3000 caratteri |

Indicazioni:

- `message` deve essere una `textarea` ampia, con un placeholder di esempio, ad esempio: "Es. 10 kg di pomodori, 5 kg di patate, 3 cassette di insalata…", e un contatore dei caratteri.
- Il telefono va inviato sempre come **stringa**, mai come numero. Usa `type="tel"` e `inputMode="tel"`.
- Il backend accetta questi formati, quindi non forzare una maschera rigida:
  - `3331234567`, `333 123 4567`, `333-123.45.67`
  - `+39 333 1234567`, `0039 333 1234567`
  - fissi: `06 1234 5678`
  - esteri con `+` o `00`: `+44 7911 123456`
  - I numeri senza prefisso vengono considerati italiani.
- Usa gli attributi `autocomplete` corretti (`given-name`, `family-name`, `organization`, `tel`).
- **Validazione lato client**: replica le regole della tabella per dare feedback immediato. La validazione definitiva però è sempre quella del backend: mostra comunque gli errori che restituisce.
- Rimuovi gli spazi iniziali e finali prima dell'invio: il backend considera vuoto un campo con soli spazi.
- **Non** inviare altri campi. In particolare `status`, `confirmed_at` e `cancelled_at` fanno rifiutare la richiesta.

Facoltativo, se semplice: ricordare nome, cognome, attività e telefono in `localStorage` per precompilarli al prossimo ordine, con la possibilità di cancellarli. Non salvare mai il testo dell'ordine.

---

## 4. COMUNICAZIONE CON IL BACKEND

### URL di base

Il backend non va mai scritto nel codice: usa una variabile d'ambiente.

```bash
# .env.local
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000
```

Fornisci anche un file `.env.example`.

### Endpoint

```
POST {NEXT_PUBLIC_API_URL}/api/v1/orders
```

È l'**unico** endpoint disponibile. Non esistono endpoint per leggere, modificare o annullare gli ordini: non crearne di immaginari.

### Header

```
Content-Type: application/json
Accept: application/json
```

Nessun token, nessun cookie, nessuna autenticazione. Non usare `credentials: "include"`.

### Body

```json
{
  "first_name": "Mario",
  "last_name": "Rossi",
  "business_name": "Pizzeria Da Mario",
  "phone": "333 123 4567",
  "message": "Buongiorno, vorrei ordinare 10 kg di pomodori e 5 kg di patate."
}
```

### Risposte

Tutte le risposte sono JSON e hanno sempre i campi `success` e `message`.

**201 Created: richiesta inviata**

```json
{
  "data": { "id": 1, "status": "new", "created_at": "2026-11-03T09:30:00+00:00" },
  "success": true,
  "message": "La tua richiesta è stata inviata con successo."
}
```

`created_at` è in UTC (ISO 8601): se lo mostri, convertilo nell'ora italiana.

**422 Unprocessable Entity: dati non validi**

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

Mostra `errors[campo][0]` sotto il relativo input e porta il focus sul primo campo con errore. I messaggi sono già in italiano.

**429 Too Many Requests: troppe richieste**

```json
{ "success": false, "message": "Hai inviato troppe richieste. Riprova tra qualche istante." }
```

Il limite è di 5 invii al minuto per indirizzo IP. L'header `Retry-After` indica dopo quanti secondi si può riprovare ed è leggibile dal frontend.

**500 o altri errori**

```json
{ "success": false, "message": "Si è verificato un errore inatteso. Riprova più tardi." }
```

**Errore di rete o backend irraggiungibile**: non c'è una risposta JSON. Mostra un messaggio tipo "Connessione assente o servizio non raggiungibile. Riprova." e **non perdere** i dati già inseriti nel modulo.

### CORS

Il backend accetta già chiamate da `http://localhost:3000` e `http://127.0.0.1:3000`. Esegui quindi il frontend in sviluppo sulla porta **3000**.

Per la produzione bisognerà comunicare al backend il dominio definitivo della PWA (es. `https://ordini.leonfrutta.it`), che verrà aggiunto alla sua configurazione. Non servono modifiche al frontend.

Non usare proxy o rewrite Next.js per aggirare il CORS: chiama direttamente l'API.

---

## 5. ARCHITETTURA

- Isola la chiamata API in un modulo dedicato (es. `lib/api/orders.ts`) con una funzione tipizzata `submitOrder(payload)`. Deve restituire un risultato discriminato (successo, errori di validazione, troppe richieste, errore generico, errore di rete) senza lanciare eccezioni verso la UI.
- Definisci i tipi TypeScript di payload e risposte.
- Componenti piccoli e leggibili: modulo, campo con errore, schermata di conferma.
- Il modulo è un componente client. La chiamata parte dal browser verso l'API pubblica.
- Niente librerie pesanti non necessarie. Una libreria per i form o per la validazione va bene solo se porta un beneficio reale.

---

## 6. ESPERIENZA UTENTE

- **Mobile-first**: la maggior parte dei clienti userà lo smartphone. Pulsanti grandi, testo leggibile, nessuno scroll orizzontale.
- Durante l'invio disabilita il pulsante e mostra uno stato di caricamento, per evitare invii doppi.
- **Dopo il 201** mostra una schermata di conferma chiara, ad esempio: "Richiesta inviata! LeonFrutta la verificherà e ti contatterà su WhatsApp al numero indicato." Riporta il numero della richiesta (`data.id`) e un pulsante "Invia un'altra richiesta" che svuota il modulo.
- Non promettere tempi di consegna, prezzi o disponibilità: la richiesta deve ancora essere confermata dall'azienda.
- **Accessibilità**: label associate agli input, errori collegati con `aria-describedby`, contrasto adeguato, navigazione da tastiera.
- Grafica pulita e professionale, con un tema adatto a un'azienda ortofrutticola. Non inventare un logo: prevedi uno spazio facilmente sostituibile.

---

## 7. PWA

- Web App Manifest con nome "LeonFrutta", icone (anche segnaposto da sostituire), `display: standalone`, colori del tema.
- Service worker per rendere l'app installabile e caricare velocemente la shell.
- **Non mettere mai in cache le chiamate `POST` all'API** e non creare code di invio offline. Se il dispositivo è offline, mostra un avviso e lascia i dati nel modulo.
- Metadati e viewport corretti per iOS e Android.

---

## 8. COSA NON DEVI SVILUPPARE

- Backend o modifiche al progetto Laravel
- Registrazione o login dei clienti
- Catalogo prodotti, categorie, prezzi, carrello
- Pagamenti, fatturazione, consegne
- Storico ordini o tracking dello stato (non esiste un'API per farlo)
- Invio o apertura automatica di WhatsApp, email o SMS
- Pannello amministrativo (esiste già lato backend, su `/admin`)
- Funzionalità non richieste

---

## 9. COME TESTARE CON IL BACKEND LOCALE

Il backend si avvia dalla sua cartella con:

```powershell
php artisan serve
```

Risponde su `http://127.0.0.1:8000`. Avvia poi il frontend sulla porta 3000 e verifica:

1. invio valido → schermata di conferma (e la richiesta compare nel pannello `/admin` del backend);
2. campi vuoti o telefono non valido → errori sotto i campi corretti;
3. sesto invio entro un minuto → messaggio di troppe richieste;
4. backend spento → messaggio di errore di rete, dati del modulo conservati;
5. layout su schermo da smartphone.

Aggiungi test automatici almeno per il modulo `submitOrder` (le diverse risposte) e per la validazione del form.

---

## 10. REPORT FINALE

Al termine indica:

- le funzionalità realizzate;
- la struttura delle cartelle principali;
- le variabili d'ambiente necessarie;
- come avviare il frontend in sviluppo e come fare la build di produzione;
- l'esito dei test;
- il dominio di produzione previsto, da comunicare al backend per il CORS;
- eventuali limiti o punti da verificare manualmente.

Per qualsiasi dubbio sul contratto API, fai riferimento a questo documento: **non inventare campi, endpoint o comportamenti del backend**.
