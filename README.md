# PHP_Laravel12_Offline_Mode_And_Sync

![Laravel](https://img.shields.io/badge/Laravel-12-red)
![PWA](https://img.shields.io/badge/PWA-Service%20Worker-blue)
![Offline Support](https://img.shields.io/badge/Mode-Offline--First-success)
![License](https://img.shields.io/badge/License-MIT-lightgrey)

---

##  Overview

This project demonstrates how to build an **offline‑first web application** using Laravel as a backend API and modern browser technologies to store data offline and automatically sync when the internet connection returns.

The app allows users to create notes even without internet access. These notes are saved locally in the browser and synced with the database once connectivity is restored.

---

##  Features

* Works fully **offline** using Service Workers
* Saves notes in **LocalStorage** when offline
* Automatically **syncs with Laravel backend** when online
* Simple **PWA (Progressive Web App)** setup
* Clean and responsive UI

---

##  Tech Stack

* **Laravel 12** — Backend API
* **MySQL** — Database
* **Service Workers** — Offline caching
* **LocalStorage** — Offline data storage
* **JavaScript Fetch API** — Sync mechanism

---

##  Folder Structure (Important Files)

```
offline-notes-app/
├── app/
│ ├── Http/
│ │ └── Controllers/
│ │ └── NoteController.php
│ └── Models/
│ └── Note.php
│
├── bootstrap/
│ └── app.php
│
├── database/
│ └── migrations/
│ └── xxxx_create_notes_table.php
│
├── public/
│ ├── manifest.json
│ ├── offline.js
│ └── sw.js
│
├── resources/
│ └── views/
│ ├── notes.blade.php
│ └── offline.blade.php
│
├── routes/
│ ├── api.php
│ └── web.php
│
└── .env
```

---

#  STEP‑BY‑STEP IMPLEMENTATION GUIDE

## STEP 1 — Install Laravel 12 Project

```bash
composer create-project laravel/laravel offline-notes-app

php artisan serve
```

Open in browser:

```
http://127.0.0.1:8000
```

---

## STEP 2 — Database Setup

Edit `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=offline_sync
DB_USERNAME=root
```

Run migrations:

```bash
php artisan migrate
```

---

## STEP 3 — Create Notes Feature

```bash
php artisan make:model Note -m

php artisan make:controller NoteController
```

### Migration File

**database/migrations/xxxx_create_notes_table.php**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
```

Run:

```bash
php artisan migrate
```

### Model

**app/Models/Note.php**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = ['title', 'content', 'updated_at'];
}
```

### Controller

**app/Http/Controllers/NoteController.php**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Note;

class NoteController extends Controller
{
    public function sync(Request $request)
    {
        $notes = $request->notes ?? [];

        foreach ($notes as $note) {
            if (empty($note['title']) || empty($note['content'])) {
                continue;
            }

            Note::create([
                'title' => $note['title'],
                'content' => $note['content'],
                'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => 'synced']);
    }
}
```

---

## STEP 4 — Routes Setup

### bootstrap/app.php

Add API routing:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

### routes/web.php

```php
<?php
use Illuminate\Support\Facades\Route;

Route::view('/', 'notes');
Route::view('/offline', 'offline');
```

### routes/api.php

```php
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NoteController;

Route::post('/notes/sync', [NoteController::class, 'sync']);
```

---

## STEP 5 — Frontend Pages

### resources/views/notes.blade.php

```html
<!DOCTYPE html>
<html>
<head>
    <title>Offline Notes App</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <style>
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: linear-gradient(135deg, #4facfe, #00f2fe); display: flex; justify-content: center; align-items: center; height: 100vh; }
        .container { background: white; width: 90%; max-width: 450px; padding: 30px; border-radius: 15px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); text-align: center; }
        h2 { margin-bottom: 20px; color: #333; }
        input, textarea { width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 8px; border: 1px solid #ddd; font-size: 14px; transition: 0.3s; }
        input:focus, textarea:focus { border-color: #4facfe; outline: none; box-shadow: 0 0 0 2px rgba(79,172,254,0.2); }
        button { width: 100%; padding: 12px; border: none; border-radius: 8px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; font-size: 15px; cursor: pointer; transition: 0.3s; }
        button:hover { opacity: 0.9; transform: translateY(-1px); }
        #status { margin-top: 15px; font-size: 14px; color: green; min-height: 20px; }
        .offline-badge { display: none; background: #ff4d4f; color: white; padding: 6px 10px; border-radius: 20px; font-size: 12px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div id="offlineBadge" class="offline-badge">You are offline</div>
        <h2>📝 Offline Notes</h2>
        <input type="text" id="title" placeholder="Note Title">
        <textarea id="content" rows="4" placeholder="Write your note..."></textarea>
        <button onclick="saveNote()">Save Note</button>
        <p id="status"></p>
    </div>
<script src="/offline.js"></script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js');
}
function updateOnlineStatus() {
    const badge = document.getElementById('offlineBadge');
    badge.style.display = navigator.onLine ? 'none' : 'inline-block';
}
window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);
updateOnlineStatus();
</script>
</body>
</html>
```

### resources/views/offline.blade.php

```html
<!DOCTYPE html>
<html>
<head>
    <title>Offline</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; justify-content: center; align-items: center; height: 100vh; color: white; text-align: center; }
        .card { background: rgba(255, 255, 255, 0.1); padding: 40px; border-radius: 15px; backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-width: 400px; }
        h2 { margin-bottom: 10px; font-size: 28px; }
        p { font-size: 16px; opacity: 0.9; }
        .emoji { font-size: 50px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="emoji">📡</div>
        <h2>You are offline</h2>
        <p>Your notes are safe and will sync automatically when internet returns.</p>
    </div>
</body>
</html>
```

---

## STEP 6 — PWA Files

### public/manifest.json

```json
{
  "name": "Offline Notes",
  "short_name": "Notes",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#0d6efd"
}
```

### public/sw.js

```javascript
const CACHE_NAME = "notes-app-v1";
const urlsToCache = ["/", "/offline"];

self.addEventListener("install", event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener("fetch", event => {
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
```

---

## STEP 7 — Offline Storage & Sync

### public/offline.js

```javascript
function saveNote() {
    const title = document.getElementById('title').value.trim();
    const content = document.getElementById('content').value.trim();

    if (!title || !content) {
        setStatus("Title and content required!", "red");
        return;
    }

    const note = {
        temp_id: Date.now(),
        title: title,
        content: content,
        updated_at: new Date().toISOString()
    };

    let notes = JSON.parse(localStorage.getItem('offline_notes') || "[]");
    notes.push(note);
    localStorage.setItem('offline_notes', JSON.stringify(notes));

    setStatus("Saved offline!", "green");

    if (navigator.onLine) syncNotes();
}

function setStatus(message, color) {
    const el = document.getElementById('status');
    el.innerText = message;
    el.style.color = color;
}

window.addEventListener("online", () => {
    setStatus("Back online. Syncing...", "blue");
    syncNotes();
});

window.addEventListener("load", () => {
    if (navigator.onLine) syncNotes();
});

async function syncNotes() {
    if (!navigator.onLine) return;

    let notes = JSON.parse(localStorage.getItem('offline_notes') || "[]");
    if (notes.length === 0) return;

    try {
        const response = await fetch('/api/notes/sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notes: notes })
        });

        if (!response.ok) throw new Error("Server error");

        localStorage.removeItem('offline_notes');
        setStatus("All notes synced!", "green");

    } catch (error) {
        setStatus("Sync failed, will retry...", "orange");
        setTimeout(syncNotes, 5000);
    }
}
```

---

#  OFFLINE NOTES APP — OUTPUT GUIDE

This app has 3 main states:
* Online Normal Mode
* Offline Save Mode
* Auto Sync Mode (when internet returns)

---

## 1. NORMAL ONLINE MODE (Internet ON)

**What you do**

* Open: [http://127.0.0.1:8000](http://127.0.0.1:8000)
* Internet is ON

**What you see on screen**

* Notes form loads
* No red “You are offline” badge
* Empty status message

Screen looks like:

```
Offline Notes
[ Note Title input ]
[ Note content textarea ]
[ Save Note button ]
```
<img width="690" height="496" alt="Screenshot 2026-01-29 173445" src="https://github.com/user-attachments/assets/42454c1c-0425-412f-ae86-0a8ab90df597" />

---

## 2. OFFLINE MODE (Internet OFF)

**What you do**

1. Open DevTools → Network tab
2. Change Online → Offline
3. Refresh page

**What you see**

* App still opens (from cache)
* A red badge appears:
  **You are offline**

<img width="675" height="504" alt="Screenshot 2026-01-29 165953" src="https://github.com/user-attachments/assets/76228b6b-fc6e-4e55-b043-77c20702dcc3" />

This means:
* ✔ Service Worker is working
* ✔ App can run without internet

---

## 3. SAVE NOTE WHILE OFFLINE

**What you do** (while still offline)

1. Enter Title: Test Sync
2. Enter Content: This should go to database
3. Click Save Note

**Expected output on screen**

```
Saved offline!
```
<img width="685" height="506" alt="Screenshot 2026-01-29 170112" src="https://github.com/user-attachments/assets/0d5822ba-202f-45e1-a6cc-2453d3fa661d" />



**What happened internally**

| Action         | Result                         |
| -------------- | ------------------------------ |
| Note saved     | Stored in browser localStorage |
| Server request | ❌ Not sent (no internet)       |
| Data safety    | ✅ Stored locally               |

---

## 4. INTERNET RETURNS (SYNC STARTS)

**What you do**
Change DevTools Network: Offline → Online

**What you see (status changes)**
First:

```
Back online. Syncing...
```

Then after 1–2 seconds:

```
All notes synced!
```
<img width="685" height="480" alt="Screenshot 2026-01-29 170127" src="https://github.com/user-attachments/assets/4bd991fe-9ef2-448e-a308-3caee605102d" />

---

## 5. DATABASE OUTPUT

**What you check**
Open phpMyAdmin → Database → notes table

**What you see**
Your offline note now exists in the database.

<img width="779" height="74" alt="Screenshot 2026-01-29 171157" src="https://github.com/user-attachments/assets/65b253df-2faa-452b-b32d-1acc6d3a9a39" />


---

# 🧪 TESTING & EXPECTED OUTPUT

| Scenario     | Action                            | Expected Output                                  |
| ------------ | --------------------------------- | ------------------------------------------------ |
| Offline open | Turn DevTools → Offline → Refresh | App still loads                                  |
| Save offline | Add note while offline            | “Saved offline!”                                 |
| Go online    | Turn network Online               | “Back online. Syncing…” then “All notes synced!” |
| Database     | Check notes table                 | Note appears                                     |

---

