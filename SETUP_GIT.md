# Collegare il progetto a Git (per allineare due PC)

Git non è installato su questo PC. `config.php` contiene la vera API key OpenAI e va
**sempre escluso** dal repository (resta locale su ogni macchina).

## 1. Cosa serve

- Git installato su entrambi i PC
- Un repository remoto **privato** (consigliato: GitHub, gratuito per account privati)

## 2. Setup su QUESTO PC (`F:\xampp\htdocs\progetti`)

Installa Git:

```
winget install --id Git.Git -e --source winget
```

Riapri il terminale, poi:

```
cd F:\xampp\htdocs\progetti
git init
git config user.name "Il tuo nome"
git config user.email "giancarlo.francesconi@gmail.com"
```

Crea un file `.gitignore` (nella cartella del progetto) con questo contenuto:

```
config.php
uploads/
allegati/
```

(`config.example.php` resta versionato come modello — è già pensato per questo)

```
git add .
git commit -m "Import iniziale progetto"
```

Poi su github.com crea un repository **privato** vuoto (senza README/licenza, per
evitare conflitti), e collegalo:

```
git remote add origin https://github.com/TUO-USERNAME/NOME-REPO.git
git branch -M main
git push -u origin main
```

## 3. Setup sull'ALTRO PC

```
winget install --id Git.Git -e --source winget
cd C:\xampp\htdocs
git clone https://github.com/TUO-USERNAME/NOME-REPO.git progetti
```

Poi lì, **a mano**:

- copia `config.example.php` in `config.php` e compila DB / API key / `FFMPEG_PATH`
  specifici di quel PC (questo file non è mai versionato, resta locale)
- `uploads/` e `allegati/` restano separate per macchina (si ricreano da sole al
  primo utilizzo); se in futuro vuoi condividere anche gli allegati reali serve un
  discorso a parte, non coperto da git

## 4. Flusso di lavoro tra le due macchine

- **Prima di iniziare a lavorare**: `git pull`
- **Quando finisci**: `git add -A` poi `git commit -m "..."` poi `git push`
- Le **migrazioni SQL** (`migrazione_*.sql`) restano da eseguire a mano su
  phpMyAdmin di ciascuna macchina dopo il pull — git allinea il codice, non il
  database

## 5. Attenzione

Se per errore committi `config.php` con la API key reale, quella chiave va
considerata compromessa e va rigenerata su platform.openai.com.
