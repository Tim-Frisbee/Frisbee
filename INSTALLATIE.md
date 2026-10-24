# Frisbee-website installeren

De website staat volledig los van WordPress en heeft geen database nodig. Teksten staan in één beveiligd bestand op de server, beelden in `site/uploads/`.

- **Code:** staat in GitHub. Elke wijziging in `site/` op de branch `main` wordt automatisch op frisbee.agency gezet.
- **Inhoud:** teksten, cases en beelden beheer je op **https://frisbee.agency/admin/**. Die blijven op de server en worden door een update nooit overschreven.

**Vereisten:** PHP 8.1 of nieuwer. De server draait PHP 8.4.

---

## Eenmalig: automatische uitrol instellen

### 1. Maak een back-up van de huidige site

Download in Plesk (Bestanden) of via FTP de volledige map `httpdocs` naar je computer.

### 2. Verwijder de oude startpagina

In `httpdocs` staat een oude, losse startpagina. Verwijder `index.html` of hernoem die naar `index-oud.html`, anders kan de server die blijven tonen. Dat kan in Plesk via **Bestanden**.

Laat de map `wp/` voorlopig staan (zie "Later" onderaan).

### 3. Vul de FTP-gegevens in op GitHub

Ga in de repository naar **Settings → Environments → Frisbee** (of **Settings → Secrets and variables → Actions**) en maak deze secrets aan:

| Naam | Waarde |
|---|---|
| `FTP_SERVER` | `noax003.noax-webdesign.be` |
| `FTP_GEBRUIKER` | je FTP-gebruikersnaam (Plesk → Websites & Domeinen → FTP-toegang) |
| `FTP_WACHTWOORD` | je FTP-wachtwoord |
| `INSTALLATIECODE` | een zelfgekozen code, bv. drie woorden aan elkaar. Alleen nodig voor de eerste installatie |

Waarom deze servernaam en niet frisbee.agency? Het beveiligingscertificaat van de FTP-server staat op `noax003.noax-webdesign.be`. Met die naam loopt de upload volledig versleuteld en gecontroleerd.

**Optioneel:** komt de site na de uitrol niet in de juiste map terecht, maak dan onder het tabblad **Variables** een variabele `FTP_MAP` aan met het pad naar de webmap. Standaard is dat `httpdocs/`.

### 4. Start de eerste uitrol

Ga naar het tabblad **Actions → Uitrol naar frisbee.agency → Run workflow**. Na een minuut of twee staat de site online. Vanaf nu gebeurt dit automatisch bij elke wijziging.

### 5. Activeer het beheer

1. Ga naar **https://frisbee.agency/admin/**
2. Vul je `INSTALLATIECODE` in.
3. Kies een sterk wachtwoord van minstens 10 tekens en bewaar het in je wachtwoordbeheerder.
4. **Verwijder daarna het secret `INSTALLATIECODE` op GitHub.** Het is niet meer nodig.

### 6. Controleer

- Open https://frisbee.agency: je ziet de nieuwe site.
- **Foutmelding "Kan niet schrijven…" bij het opslaan:** geef de mappen `data/` en `uploads/` schrijfrechten. Doe dat in Plesk via Bestanden → map aanklikken → Rechten wijzigen.
- **Uitrol mislukt:** open de mislukte run onder **Actions**. De foutmelding staat bij de stap met het rode kruisje. Meestal klopt een FTP-gegeven niet.

---

## Dagelijks gebruik

**Inhoud aanpassen** doe je op **https://frisbee.agency/admin/**:

| Onderdeel | Wat je er aanpast |
|---|---|
| **Algemeen** | Naam, beschrijving voor Google, e-mail, sociale media, footer |
| **Homepage** | Hero, labels rond de schijf, lichtkrant, uitgelicht project, titels, werkwijze, contactblok |
| **Berichten** | Alle berichten uit het contactformulier, ook als de mail niet aankwam |
| **Diensten** | De diensten en hun eigen pagina: intro, inhoudsblokken, "wat je krijgt", beelden en realisaties |
| **Realisaties** | Cases toevoegen, ordenen, verbergen, verwijderen en volledig invullen |
| **Formulier** | Ontvangers, teksten, velden, mailserver en captcha van het contactformulier |
| **Beelden** | Beelden bekijken, uploaden en ongebruikte verwijderen |
| **Back-ups** | Elke opslag maakt een back-up. Hier zet je een oudere versie terug |
| **Wachtwoord** | Je wachtwoord wijzigen |

**Ontwerp of code aanpassen** gebeurt in GitHub. Elke wijziging in `site/` op `main` gaat automatisch live. Ging er iets mis, zet de vorige versie dan terug in GitHub ("Revert"), en die wordt ook automatisch uitgerold.

**Tips:**
- **Accenten in titels:** zet woorden tussen `*sterretjes*`, dan krijgen ze het groene accent of worden ze vet.
- **Verborgen cases:** die zie je zelf wel op de site zolang je ingelogd bent.
- **Bewerken vanaf de site:** ben je ingelogd, dan zie je bovenaan de site een balk met **Bewerk deze pagina**.

## Contactformulier

Het formulier staat onderaan elke pagina. Op een dienstpagina staat die dienst al voorgeselecteerd.

- **Opslag:** elk bericht wordt bewaard onder **Beheer → Berichten** en per mail doorgestuurd naar de ontvangers die je onder **Formulier** instelt. Leeg = het e-mailadres onder Algemeen.
- **Spambeveiliging:**
  - een rekenvraag als captcha
  - een onzichtbaar lokveld voor bots
  - een minimale invultijd
  - maximaal 5 berichten per uur per bezoeker
- **Cloudflare Turnstile:** wil je liever een onzichtbare captcha, vul dan de sleutels in onder Formulier → Captcha.

### Zorg dat de mails aankomen

Standaard verstuurt de webserver de mails zelf. Twee dingen maken het betrouwbaarder:

1. **Mailserver invullen (aangeraden):** ga naar **Beheer → Formulier → Versturen via je eigen mailserver** en vul de gegevens van een mailbox in, bijvoorbeeld een `website@frisbee.agency`-mailbox in Plesk. Klik daarna op **Opslaan en testmail sturen**.
2. **SPF-record herstellen:** je domein heeft nu **twee** SPF-records. Dat is ongeldig, waardoor mails namens frisbee.agency sneller als spam worden gezien, ook je gewone mails. Voeg ze samen tot **één** TXT-record. Een voorbeeld, laat je hoster of mailprovider dit nakijken:

   ```
   v=spf1 a mx include:spf.protection.outlook.com include:spf.cloudemail.be include:_spf.relay.mailprotect.be -all
   ```

   Verwijder daarna het tweede record dat met `v=spf1` begint. De `MS=ms60745920`-waarde hoort in een apart TXT-record.

## Wat staat waar

| Map / bestand | Inhoud | In GitHub? |
|---|---|---|
| `site/index.php`, `site/case.php`, `site/dienst.php` | De pagina's | Ja |
| `site/contact.php`, `site/inc/contact.php` | Het contactformulier | Ja |
| `site/admin/` | Het beheerpaneel | Ja |
| `site/assets/` | Opmaak, effecten, logo | Ja |
| `site/inc/` | Kernlogica, illustraties, startinhoud | Ja |
| `site/uploads/` | Beelden. De startbeelden staan in GitHub, beelden die je via het beheer uploadt alleen op de server | Deels |
| `site/data/` | Je teksten, wachtwoord, back-ups, berichten en mailinstellingen | **Nee**, alleen op de server |

**Back-up van je inhoud:** die staat niet in GitHub. Download af en toe de mappen `data/` en `uploads/` via Plesk, of laat Plesk automatische back-ups maken.

## Beveiliging

- **Wachtwoord:** opgeslagen als veilige hash, nooit leesbaar.
- **Inlogpogingen:** na 5 foute pogingen in 15 minuten wordt inloggen tijdelijk geblokkeerd.
- **Sessie:** na 2 uur zonder activiteit word je automatisch uitgelogd.
- **Formulieren:** elk formulier is beschermd tegen vervalste verzoeken (CSRF).
- **Uploads:** alleen echte afbeeldingen (JPG, PNG, WebP, GIF) tot 8 MB, met een willekeurige bestandsnaam.
- **Inhoudsbestanden:** de bestanden in `data/` kunnen niet via de browser gedownload worden.
- **Geen geheimen in de code:** FTP-gegevens staan alleen als versleutelde secrets in GitHub. Zo kan de repository zonder risico openbaar zijn.

## Wachtwoord vergeten?

1. Verwijder in Plesk (Bestanden) het bestand `httpdocs/data/config.php`.
2. Zet op GitHub opnieuw een secret `INSTALLATIECODE` en start de uitrol (Actions → Run workflow).
3. Ga naar `/admin/` en stel met die code een nieuw wachtwoord in. Je inhoud blijft bewaard.
4. Verwijder het secret daarna weer.

## Later: WordPress uitschakelen

Werkt alles naar wens, verwijder dan de map `wp/` (na je back-up). Oude links naar `/wp/` stuur je door naar de homepage met deze regels onderaan `site/.htaccess` (in GitHub, dan gaat het vanzelf live):

```
RewriteEngine On
RewriteRule ^wp(/.*)?$ / [R=301,L]
```
