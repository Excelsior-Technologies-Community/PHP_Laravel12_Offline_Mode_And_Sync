<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Offline Notes & Sync Studio</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <link
        rel="manifest"
        href="/manifest.json"
    >

    <meta
        name="theme-color"
        content="#667eea"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #eef2ff,
                    #f8fafc
                );

            color: #222;
        }

        .header {

            background:
                linear-gradient(
                    135deg,
                    #4facfe,
                    #667eea
                );

            color: white;

            padding: 30px 20px;

            text-align: center;
        }

        .header h1 {

            margin:
                0 0 8px;

            font-size: 32px;
        }

        .header p {

            margin: 0;

            opacity: .9;
        }

        .main-container {

            width: 95%;

            max-width: 1200px;

            margin:
                25px auto;
        }

        .status-row {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;

            margin-bottom: 20px;
        }

        .connection {

            background: white;

            padding:
                10px 16px;

            border-radius: 20px;

            box-shadow:
                0 3px 12px
                rgba(
                    0,
                    0,
                    0,
                    .08
                );

            font-weight: bold;
        }

        .offline-badge {

            display: none;

            background:
                #dc3545;

            color: white;

            padding:
                8px 14px;

            border-radius: 20px;

            font-size: 13px;
        }

        .dashboard {

            display: grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap: 15px;

            margin-bottom: 25px;
        }

        .stat-card {

            background: white;

            padding: 20px;

            border-radius: 14px;

            box-shadow:
                0 5px 18px
                rgba(
                    0,
                    0,
                    0,
                    .08
                );
        }

        .stat-card h3 {

            margin:
                0 0 8px;

            font-size: 14px;

            color: #666;
        }

        .number {

            font-size: 30px;

            font-weight: bold;
        }

        .card {

            background: white;

            padding: 25px;

            border-radius: 15px;

            box-shadow:
                0 5px 20px
                rgba(
                    0,
                    0,
                    0,
                    .08
                );

            margin-bottom: 25px;
        }

        .card h2 {

            margin-top: 0;
        }

        input,
        textarea,
        select {

            width: 100%;

            padding: 13px;

            margin-bottom: 15px;

            border-radius: 8px;

            border:
                1px solid #ddd;

            font-size: 14px;

            font-family: inherit;
        }

        input:focus,
        textarea:focus,
        select:focus {

            border-color:
                #667eea;

            outline: none;
        }

        .button-row {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;
        }

        button {

            border: none;

            padding:
                11px 17px;

            border-radius: 8px;

            background:
                linear-gradient(
                    135deg,
                    #667eea,
                    #764ba2
                );

            color: white;

            cursor: pointer;

            font-size: 14px;
        }

        button:hover {

            opacity: .9;
        }

        .secondary-button {

            background:
                #198754;
        }

        .danger-button {

            background:
                #dc3545;
        }

        .warning-button {

            background:
                #f59e0b;
        }

        .dark-button {

            background:
                #343a40;
        }

        #status {

            min-height: 22px;

            margin:
                15px 0 0;

            font-weight: bold;
        }

        .search-row {

            display: grid;

            grid-template-columns:
                1fr 180px 130px;

            gap: 10px;

            margin-bottom: 20px;
        }

        .search-row input,
        .search-row select {

            margin-bottom: 0;
        }

        .note-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    1fr
                );

            gap: 15px;
        }

        .note-card {

            border:
                1px solid #e5e7eb;

            padding: 18px;

            border-radius: 12px;

            background:
                #ffffff;

            transition:
                transform .2s,
                box-shadow .2s;
        }

        .note-card:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 8px 20px
                rgba(
                    0,
                    0,
                    0,
                    .08
                );
        }

        .note-card-header {

            display: flex;

            justify-content:
                space-between;

            align-items:
                flex-start;

            gap: 10px;
        }

        .note-card h3 {

            margin:
                8px 0;
        }

        .note-card p {

            color: #555;

            line-height: 1.6;
        }

        .note-actions {

            display: flex;

            gap: 8px;

            flex-wrap: wrap;

            margin-top: 15px;
        }

        .note-actions button {

            font-size: 12px;

            padding:
                8px 11px;
        }

        .note-date {

            display: block;

            color: #777;

            margin-top: 12px;
        }

        .tags {

            display: flex;

            flex-wrap: wrap;

            gap: 6px;

            margin:
                10px 0;
        }

        .tag {

            background:
                #eef2ff;

            color:
                #4338ca;

            padding:
                5px 9px;

            border-radius:
                15px;

            font-size: 12px;
        }

        .mini-badge {

            display: inline-block;

            padding:
                4px 8px;

            border-radius:
                12px;

            font-size: 11px;

            margin-right: 5px;
        }

        .pin-badge {

            background:
                #fff3cd;

            color:
                #856404;
        }

        .favorite-badge {

            background:
                #ffe4e6;

            color:
                #be123c;
        }

        .queue-item,
        .history-item {

            border:
                1px solid #e5e7eb;

            padding: 15px;

            border-radius: 10px;

            margin-bottom: 10px;

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 15px;
        }

        .queue-item small,
        .history-item small {

            display: block;

            color: #777;

            margin-top: 5px;
        }

        .queue-badge {

            padding:
                6px 10px;

            border-radius: 15px;

            font-size: 12px;

            white-space: nowrap;
        }

        .pending {

            background:
                #fff3cd;

            color:
                #856404;
        }

        .failed {

            background:
                #f8d7da;

            color:
                #842029;
        }

        .conflict {

            background:
                #ffe5d0;

            color:
                #9a3412;
        }

        .syncing {

            background:
                #cff4fc;

            color:
                #055160;
        }

        .deleted {

            background:
                #e2e3e5;

            color:
                #41464b;
        }

        .error-text {

            color:
                #dc3545 !important;
        }

        .empty-state {

            padding: 25px;

            text-align: center;

            color: #777;

            background:
                #f8f9fa;

            border-radius: 8px;
        }

        .server-note {

            border:
                1px solid #e5e7eb;

            padding: 18px;

            border-radius: 12px;

            margin-bottom: 12px;

            background: white;
        }

        .server-note h3 {

            margin:
                8px 0;
        }

        .server-note p {

            color: #555;

            line-height: 1.6;
        }

        .conflict-card {

            border:
                2px solid #ff9800;

            background:
                #fffaf2;

            padding: 20px;

            border-radius: 12px;

            margin-bottom: 15px;
        }

        .conflict-card h3 {

            margin-top: 0;
        }

        .conflict-columns {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 15px;
        }

        .version-box {

            background: white;

            border:
                1px solid #ddd;

            border-radius: 10px;

            padding: 18px;
        }

        .version-box h4 {

            margin-top: 0;
        }

        .version-box small {

            display: block;

            color: #777;

            margin-bottom: 12px;
        }

        .resolve-local {

            background:
                #198754;
        }

        .resolve-server {

            background:
                #0d6efd;
        }

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap: 12px;
        }

        .small-stat {

            background:
                #f8f9fa;

            border-radius: 10px;

            padding: 15px;

            text-align: center;
        }

        .small-stat strong {

            display: block;

            font-size: 24px;

            margin-top: 5px;
        }

        @media (
            max-width: 900px
        ) {

            .dashboard {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );
            }

            .stats-grid {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );
            }

            .note-grid {

                grid-template-columns:
                    1fr;
            }

            .search-row {

                grid-template-columns:
                    1fr;
            }
        }

        @media (
            max-width: 600px
        ) {

            .dashboard {

                grid-template-columns:
                    1fr;
            }

            .stats-grid {

                grid-template-columns:
                    1fr;
            }

            .conflict-columns {

                grid-template-columns:
                    1fr;
            }

            .main-container {

                width: 92%;
            }

            .card {

                padding: 18px;
            }

            .note-card-header {

                flex-direction:
                    column;
            }
        }

    </style>

</head>

<body>

    <!-- Header -->

    <div class="header">

        <h1>
            📝 Offline Notes & Sync Studio
        </h1>

        <p>
            Offline-first Laravel notes
            with synchronization management
        </p>

    </div>


    <div class="main-container">

        <!-- Connection -->

        <div class="status-row">

            <div
                id="connectionStatus"
                class="connection"
            >
                🟢 Online
            </div>

            <div
                id="offlineBadge"
                class="offline-badge"
            >
                You are offline
            </div>

        </div>


        <!-- Main Statistics -->

        <div class="dashboard">

            <div class="stat-card">

                <h3>
                    📱 Local Notes
                </h3>

                <div
                    class="number"
                    id="localNotesCount"
                >
                    0
                </div>

            </div>

            <div class="stat-card">

                <h3>
                    ⏳ Pending Sync
                </h3>

                <div
                    class="number"
                    id="pendingNotesCount"
                >
                    0
                </div>

            </div>

            <div class="stat-card">

                <h3>
                    ❌ Failed
                </h3>

                <div
                    class="number"
                    id="failedNotesCount"
                >
                    0
                </div>

            </div>

            <div class="stat-card">

                <h3>
                    ⚔️ Conflicts
                </h3>

                <div
                    class="number"
                    id="conflictNotesCount"
                >
                    0
                </div>

            </div>

        </div>


        <!-- Extra Statistics -->

        <div class="card">

            <h2>
                📊 Sync Statistics
            </h2>

            <div class="stats-grid">

                <div class="small-stat">

                    🗑️ Deleted

                    <strong
                        id="deletedNotesCount"
                    >
                        0
                    </strong>

                </div>

                <div class="small-stat">

                    ⭐ Favorites

                    <strong
                        id="favoriteNotesCount"
                    >
                        0
                    </strong>

                </div>

                <div class="small-stat">

                    📌 Pinned

                    <strong
                        id="pinnedNotesCount"
                    >
                        0
                    </strong>

                </div>

                <div class="small-stat">

                    📜 History

                    <strong
                        id="historyCount"
                    >
                        0
                    </strong>

                </div>

            </div>

        </div>


        <!-- Create Note -->

        <div class="card">

            <h2>
                📝 Create Note
            </h2>

            <input
                type="text"
                id="title"
                placeholder="Note Title"
            >

            <textarea
                id="content"
                rows="5"
                placeholder="Write your note..."
            ></textarea>

            <input
                type="text"
                id="tags"
                placeholder="Tags, e.g. Laravel, PHP, Offline"
            >

            <div class="button-row">

                <button
                    onclick="saveNote()"
                >
                    💾 Save Note
                </button>

                <button
                    class="secondary-button"
                    onclick="manualSync()"
                >
                    🔄 Sync Now
                </button>

                <button
                    class="warning-button"
                    onclick="retryFailedSync()"
                >
                    🔁 Retry Failed
                </button>

                <button
                    class="danger-button"
                    onclick="clearQueue()"
                >
                    🧹 Clear Queue
                </button>

                <button
                    class="dark-button"
                    onclick="exportNotes()"
                >
                    📥 Export CSV
                </button>

            </div>

            <p id="status"></p>

        </div>


        <!-- Search -->

        <div class="card">

            <h2>
                🔎 Note Search & Filters
            </h2>

            <div class="search-row">

                <input
                    type="search"
                    id="noteSearch"
                    placeholder="Search local notes by title, content or tags..."
                    oninput="renderLocalNotes()"
                >

                <select
                    id="noteFilter"
                    onchange="renderLocalNotes()"
                >

                    <option value="all">
                        All Notes
                    </option>

                    <option value="favorites">
                        ⭐ Favorites
                    </option>

                    <option value="pinned">
                        📌 Pinned
                    </option>

                    <option value="pending">
                        ⏳ Pending
                    </option>

                </select>

                <button
                    onclick="renderLocalNotes()"
                >
                    🔎 Search
                </button>

            </div>

        </div>


        <!-- Local Notes -->

        <div class="card">

            <h2>
                📱 Local Notes
            </h2>

            <div
                id="localNotes"
                class="note-grid"
            >

                <div class="empty-state">
                    No local notes found.
                </div>

            </div>

        </div>


        <!-- Synchronization -->

        <div class="card">

            <h2>
                📊 Synchronization Information
            </h2>

            <p>

                <strong>
                    Last Successful Sync:
                </strong>

                <span id="lastSyncTime">
                    Never
                </span>

            </p>

            <p>

                Notes created or modified
                while offline remain in the
                browser queue until they are
                synchronized successfully.

            </p>

        </div>


        <!-- Pending Queue -->

        <div class="card">

            <h2>
                ⏳ Offline / Pending Queue
            </h2>

            <div id="pendingQueue">

                <div class="empty-state">
                    No local queue items.
                </div>

            </div>

        </div>


        <!-- Conflict -->

        <div class="card">

            <h2>
                ⚔️ Conflict Resolution
            </h2>

            <p>

                If local and server versions
                conflict, select which version
                should be kept.

            </p>

            <div
                id="conflictContainer"
            ></div>

        </div>


        <!-- Server Search -->

        <div class="card">

            <h2>
                ☁️ Server Note Search
            </h2>

            <div class="search-row">

                <input
                    type="search"
                    id="serverSearch"
                    placeholder="Search server notes..."
                    onkeydown="
                        if(event.key === 'Enter') {
                            searchServerNotes();
                        }
                    "
                >

                <div></div>

                <button
                    onclick="searchServerNotes()"
                >
                    🔎 Search Server
                </button>

            </div>

        </div>


        <!-- Server Notes -->

        <div class="card">

            <h2>
                ☁️ Server Notes
            </h2>

            <div id="serverNotes">

                <div class="empty-state">

                    Loading server notes...

                </div>

            </div>

        </div>


        <!-- Sync History -->

        <div class="card">

            <h2>
                📜 Synchronization History
            </h2>

            <div id="syncHistory">

                <div class="empty-state">

                    No synchronization history yet.

                </div>

            </div>

        </div>

    </div>


    <!-- Offline JavaScript -->

    <script src="/offline.js"></script>


    <!-- Online / Offline UI -->

    <script>

        function updateOnlineStatus() {

            const badge =
                document.getElementById(
                    "offlineBadge"
                );

            const connectionStatus =
                document.getElementById(
                    "connectionStatus"
                );

            if (!navigator.onLine) {

                if (badge) {

                    badge.style.display =
                        "inline-block";
                }

                if (connectionStatus) {

                    connectionStatus.innerText =
                        "🔴 Offline";
                }

            } else {

                if (badge) {

                    badge.style.display =
                        "none";
                }

                if (connectionStatus) {

                    connectionStatus.innerText =
                        "🟢 Online";
                }
            }
        }

        window.addEventListener(
            "online",
            updateOnlineStatus
        );

        window.addEventListener(
            "offline",
            updateOnlineStatus
        );

        updateOnlineStatus();

    </script>

</body>

</html>