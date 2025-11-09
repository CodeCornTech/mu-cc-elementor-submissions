# 🧩 MU-CC Elementor Submissions

> **CodeCorn™ MU Plugin** per estendere e migliorare la gestione delle **Submissions Elementor Pro** nel backend WordPress.

---

### 🚀 Overview

Questo modulo *must-use* (`mu-plugin`) nasce per:
- 🧹 **Pulire i campi HTML** dalle submissions Elementor (validation/process hook)
- 🧭 Aggiungere un **menu “Preventivi”** laterale + shortcut toolbar
- 📨 Mostrare lo **stato di lettura ("Letta")** direttamente nella tabella di elenco
- 🖼️ Visualizzare **thumbnail e anteprime video** all’interno della scheda Submission
- 🔒 Garantire compatibilità completa con REST Elementor Submissions (nessuna interferenza)

---

### 🧱 Struttura

```
wp-content/
└── mu-plugins/
    ├── mu-cc-elementor-submissions.php        ← loader MU (require del main)
    └── codecorn/
        └── elementor-submissions/
            ├── mu-cc-elementor-submissions.php
            ├── assets/
            │   ├── css/
            │   │   └── admin.css
            │   └── js/
            │       ├── submissions-detail-media.js
            │       └── submissions-list-read-column.js
            └── README.md
````

Se usi un loader MU centralizzato (`mu-plugins/codecorn-loader.php`) ricordati di includerlo:
```php
require_once __DIR__ . '/codecorn/mu-cc-elementor-submissions/mu-cc-elementor-submissions.php';
````

---

### ⚙️ Features principali

| Funzione               | Descrizione                                                       |
| ---------------------- | ----------------------------------------------------------------- |
| **HTML Field Cleanup** | Rimuove i campi `type=html` durante validation e process dei form |
| **Preventivi Menu**    | Aggiunge voce principale in admin e nodo toolbar con icona 📋     |
| **Colonna “Letta”**    | Recupera via AJAX lo stato `is_read` da `wp_e_submissions`        |
| **Preview Media**      | Mostra thumbnail per immagini / video con lightbox e download     |
| **Safe Hooks**         | Non interferisce con REST `/elementor/v1/forms/submissions`       |

---

### 💡 Debug

Puoi abilitare il log PHP in `wp-content/debug.log` settando:

```php
define('MU_CC_ES_DEBUG', true);
```

Oppure temporaneamente via WP-CLI:

```bash
wp config set MU_CC_ES_DEBUG true --raw
```

---

### 🧩 Requirements

* WordPress 6.0+
* Elementor Pro 3.10+
* Accesso admin (`manage_options`)
* PHP 8.0+

---

### 🧠 Namespace

Tutto il codice è namespaced:

```php
namespace MU_CC\ElementorSubmissions;
```

---

### 🪄 Screenshot (Admin UX)

| View                     | Descrizione                                        |
| ------------------------ | -------------------------------------------------- |
| 📋 **Lista Submissions** | nuova colonna “Letta” + colori stato               |
| 🖼️ **Scheda Dettaglio** | anteprima immagini e video con lightbox & download |
| 🧭 **Admin Bar**         | shortcut “Preventivi” direttamente in toolbar      |

---

### 🏗️ Future roadmap

* [ ] Badge “Letta / Non letta” anche nella lista filtri laterale
* [ ] Azioni bulk AJAX per marcare submissions
* [ ] Colonna “Note interne” salvata in meta
* [ ] Supporto a WP ListTable custom filtering
* [ ] Micro-analytics visualizzazioni

---

### 🧾 License

GPL-2.0-or-later
© CodeCorn™ Technology SRLS – All rights reserved.

---

### 🪙 Brand

<a href="https://codecorn.it">
  <img src="https://avatars.githubusercontent.com/u/224283528?s=200&v=4" width="180" alt="CodeCorn™">
</a>

> Crafted with 💛 by **Federico Girolami** · Full-Stack Dev & System Architect
> [https://github.com/CodeCornTech](https://github.com/CodeCornTech)
