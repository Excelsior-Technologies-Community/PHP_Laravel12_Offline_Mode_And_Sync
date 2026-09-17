<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Offline Notes & Sync</title>

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
        content="#0d6efd"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #222;
        }

        .header {
            background: linear-gradient(
                135deg,
                #4facfe,
                #667eea
            );

            color: white;
            padding: 25px;
            text-align: center;
        }

        .header h1 {
            margin: 0 0 8px;
        }

        .header p {
            margin: 0;
            opacity: 0.9;
        }

        .main-container {
            width: 95%;
            max-width: 1100px;
            margin: 25px auto;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .connection {
            background: white;
            padding: 10px 15px;
            border-radius: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            font-weight: bold;
        }

        .offline-badge {
            display: none;
            background: #ff4d4f;
            color: white;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 13px;
        }

        .dashboard {
            display: grid;
            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 18px rgba(0,0,0,0.08);
        }

        .stat-card h3 {
            margin: 0 0 8px;
            font-size: 14px;
            color: #666;
        }

        .stat-card .number {
            font-size: 30px;
            font-weight: bold;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .card h2 {
            margin-top: 0;
        }

        input,
        textarea {
            width: 100%;
            padding: 13px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 14px;
            font-family: inherit;
        }

        input:focus,
        textarea:focus {
            border-color: #4facfe;
            outline: none;
        }

        .button-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            padding: 11px 17px;
            border-radius: 8px;
            background: linear-gradient(
                135deg,
                #667eea,
                #764ba2
            );
            color: white;
            cursor: pointer;
            font-size: 14px;
        }

        button:hover {
            opacity: 0.9;
        }

        .secondary-button {
            background: #198754;
        }

        #status {
            min-height: 22px;
            margin: 15px 0 0;
            font-weight: bold;
        }

        .queue-item,
        .server-note {
            border: 1px solid #e5e7eb;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .queue-item small {
            display: block;
            color: #777;
            margin-top: 5px;
        }

        .queue-badge {
            padding: 6px 10px;
            border-radius: 15px;
            font-size: 12px;
            white-space: nowrap;
        }

        .pending {
            background: #fff3cd;
            color: #856404;
        }

        .failed {
            background: #f8d7da;
            color: #842029;
        }

        .conflict {
            background: #ffe5d0;
            color: #9a3412;
        }

        .syncing {
            background: #cff4fc;
            color: #055160;
        }

        .error-text {
            color: #dc3545 !important;
        }

        .empty-state {
            padding: 20px;
            text-align: center;
            color: #777;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .server-note {
            display: block;
        }

        .server-note h4 {
            margin: 0 0 8px;
        }

        .server-note p {
            color: #555;
            margin: 8px 0;
        }

        .server-note small {
            color: #777;
        }

        .conflict-card {
            border: 2px solid #ff9800;
            background: #fffaf2;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .conflict-card h3 {
            margin-top: 0;
        }

        .conflict-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .version-box {
            background: white;
            border: 1px solid #ddd;
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
            background: #198754;
        }

        .resolve-server {
            background: #0d6efd;
        }

        @media (max-width: 800px) {

            .dashboard {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .conflict-columns {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 500px) {

            .dashboard {
                grid-template-columns: 1fr;
            }

            .main-container {
                width: 92%;
            }

            .card {
                padding: 18px;
            }
        }

    </style>

</head>

<body>

    <div class="header">

        <h1>📝 Offline Notes & Sync</h1>

        <p>
            Offline-first Laravel application
            with automatic synchronization
        </p>

    </div>

    <div class="main-container">

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


        <!-- Dashboard -->

        <div class="dashboard">

            <div class="stat-card">

                <h3>📱 Local Queue</h3>

                <div
                    class="number"
                    id="localNotesCount"
                >
                    0
                </div>

            </div>

            <div class="stat-card">

                <h3>⏳ Pending Sync</h3>

                <div
                    class="number"
                    id="pendingNotesCount"
                >
                    0
                </div>

            </div>

            <div class="stat-card">

                <h3>❌ Failed Sync</h3>

                <div
                    class="number"
                    id="failedNotesCount"
                >
                    0
                </div>

            </div>

            <div class="stat-card">

                <h3>⚔️ Conflicts</h3>

                <div
                    class="number"
                    id="conflictNotesCount"
                >
                    0
                </div>

            </div>

        </div>


        <!-- Create Note -->

        <div class="card">

            <h2>📝 Create Note</h2>

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

            <div class="button-row">

                <button onclick="saveNote()">
                    Save Note
                </button>

                <button
                    class="secondary-button"
                    onclick="manualSync()"
                >
                    🔄 Sync Now
                </button>

                <button
                    onclick="retryFailedSync()"
                >
                    🔁 Retry Failed
                </button>

            </div>

            <p id="status"></p>

        </div>


        <!-- Sync Information -->

        <div class="card">

            <h2>📊 Synchronization Information</h2>

            <p>
                <strong>Last Successful Sync:</strong>
                <span id="lastSyncTime">
                    Never
                </span>
            </p>

            <p>
                Notes saved offline remain in the
                browser queue until synchronization
                succeeds.
            </p>

        </div>


        <!-- Pending Queue -->

        <div class="card">

            <h2>⏳ Offline / Pending Queue</h2>

            <div id="pendingQueue">

                <div class="empty-state">
                    No pending offline notes.
                </div>

            </div>

        </div>


        <!-- Conflict Resolution -->

        <div class="card">

            <h2>⚔️ Conflict Resolution</h2>

            <p>
                When a local note and server note
                contain different newer versions,
                the application will ask which
                version should be kept.
            </p>

            <div id="conflictContainer"></div>

        </div>


        <!-- Server Notes -->

        <div class="card">

            <h2>☁️ Server Notes</h2>

            <div id="serverNotes">

                <div class="empty-state">
                    Loading server notes...
                </div>

            </div>

        </div>

    </div>


    <script src="/offline.js"></script>

    <script>

        /*
        |--------------------------------------------------------------------------
        | Service Worker Registration
        |--------------------------------------------------------------------------
        */

        if ("serviceWorker" in navigator) {

            navigator.serviceWorker
                .register("/sw.js")
                .then(() => {
                    console.log(
                        "Service Worker registered."
                    );
                })
                .catch(error => {
                    console.error(
                        "Service Worker registration failed:",
                        error
                    );
                });

        }


        /*
        |--------------------------------------------------------------------------
        | Online / Offline UI
        |--------------------------------------------------------------------------
        */

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

                badge.style.display =
                    "inline-block";

                connectionStatus.innerText =
                    "🔴 Offline";

            } else {

                badge.style.display =
                    "none";

                connectionStatus.innerText =
                    "🟢 Online";

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