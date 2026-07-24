# Publi Tool — installatie op one.com

Deze app bestaat uit een **frontend** (één HTML-bestand) en een **PHP/MySQL backend**.

## 1. Database opzetten

1. Log in op je one.com control panel
2. Ga naar **Database** → maak een nieuwe MySQL database aan. Noteer:
   - hostname (meestal iets als `mysql.ditisjouwdomein.be` of `localhost`)
   - database naam
   - gebruikersnaam
   - paswoord
3. Open phpMyAdmin (vanuit one.com control panel)
4. Selecteer je nieuwe database in de linker zijbalk
5. Klik op tabblad **SQL**
6. Open `install.sql` uit deze map, kopieer **volledige inhoud**, plak in de SQL-textarea
7. Klik **Start** / **Go**. Je krijgt nu alle tabellen + voorbeeld-data

**Belangrijk**: stel het admin-paswoord onmiddellijk in via het meegeleverde script:

1. Open `https://jouwdomein.be/api/install_password.php` in de browser
2. Vul gebruikersnaam (`admin`) en een sterk paswoord in
3. Klik op "Paswoord instellen"
4. **Verwijder** `install_password.php` van de server!

(Het standaard-paswoord uit `install.sql` is een placeholder en werkt waarschijnlijk niet — dus deze stap is verplicht.)

## 2. Bestanden uploaden

Verbind met one.com via FTP/SFTP. Maak deze structuur in je `httpdocs` (of `htdocs`):

```
httpdocs/
├── index.html              <-- de Parcours-Editor frontend
├── api/
│   ├── _bootstrap.php
│   ├── config.php          <-- maak je zelf (zie stap 3)
│   ├── login.php
│   ├── logout.php
│   ├── whoami.php
│   ├── seasons.php
│   ├── koepels.php
│   ├── partners.php
│   ├── parcours.php
│   └── upload.php
└── uploads/
    ├── maps/               <-- chmod 755
    └── logos/              <-- chmod 755
```

## 3. Configuratie

1. Kopieer `api/config.example.php` naar `api/config.php`
2. Open `config.php` en vul je MySQL-gegevens in
3. **Belangrijk**: zet de `uploads_dir` correct als je een andere structuur gebruikt

## 4. Schrijfrechten

`uploads/maps/` en `uploads/logos/` moeten schrijfbaar zijn voor PHP. Via FTP-client meestal:
- chmod 755 op de mappen, of
- chmod 775 als 755 niet werkt

## 5. Eerste login

Open `https://jouwdomein.be/` in de browser. Log in met:
- gebruikersnaam: `admin`
- paswoord: het paswoord dat je in stap 1 hebt ingesteld via `install_password.php`

Vink "30 dagen onthouden" aan zodat je niet steeds hoeft in te loggen.

## 6. Veiligheid

- Plaats `.htaccess` in `httpdocs/api/` om PHP-bestanden te beschermen tegen directory listing:
  ```apache
  Options -Indexes
  <Files "config.php">
      Require all denied
  </Files>
  <Files "_bootstrap.php">
      Require all denied
  </Files>
  ```

- Plaats `.htaccess` in `httpdocs/uploads/` om uploads-map te beveiligen tegen PHP-uitvoering:
  ```apache
  <FilesMatch "\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$">
      Require all denied
  </FilesMatch>
  Options -Indexes
  ```

## 7. Backup

Maak regelmatig een backup van:
- De MySQL database (export uit phpMyAdmin als SQL of zip)
- De `uploads/` map (FTP-download)

## Bekende beperkingen

- Maximale upload: 10 MB per bestand (zie `max_upload_size` in `config.php`)
- KMZ-bestanden worden client-side verwerkt en als PNG-render geüpload
- Eén login = single-user. Voor meerdere users: voeg INSERT's toe in `pe_users` met andere usernames

## Probleemoplossing

**"Server niet geconfigureerd"** → `config.php` ontbreekt of bevat een typo

**"Kan niet met database verbinden"** → check db_host, db_name, db_user, db_password in `config.php`

**"Upload-map niet schrijfbaar"** → check chmod op `uploads/maps/` en `uploads/logos/`

**Login blijft niet bewaard** → check of je site `https://` heeft. one.com biedt gratis SSL. Cookies werken niet altijd over HTTP.

**500-error op een endpoint** → check de PHP error log van one.com (control panel → Logs)
