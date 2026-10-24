# Frisbee Agency

Website van [frisbee.agency](https://frisbee.agency): een lichte PHP-site met eigen beheerpaneel, zonder WordPress en zonder database.

- **`site/`**: alles wat op de server komt (pagina's, beheerpaneel, opmaak, beelden)
- **`.github/workflows/uitrol.yml`**: zet elke wijziging in `site/` op `main` automatisch live via FTPS
- **[INSTALLATIE.md](INSTALLATIE.md)**: eenmalige installatie, dagelijks gebruik en beveiliging

## Lokaal bekijken

```bash
php -S localhost:8000 -t site
```

Open daarna http://localhost:8000. Het beheer staat op http://localhost:8000/admin/. Lokaal heb je een eigen installatiecode nodig in `site/data/installatiecode.php` (zie INSTALLATIE.md).

## Inhoud

Teksten, cases en geüploade beelden staan alleen op de server (`site/data/`, `site/uploads/`) en worden bij een uitrol nooit overschreven. `site/inc/standaard-inhoud.json` is de startinhoud voor een verse installatie.
