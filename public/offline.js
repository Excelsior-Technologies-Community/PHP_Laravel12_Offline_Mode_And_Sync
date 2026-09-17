let syncInProgress = false;
let retryTimer = null;

const STORAGE_KEY = "offline_notes";
const LAST_SYNC_KEY = "offline_notes_last_sync";

/*
|--------------------------------------------------------------------------
| Local Storage Helpers
|--------------------------------------------------------------------------
*/

function getLocalNotes() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
    } catch (error) {
        console.error("Unable to read local notes:", error);
        return [];
    }
}

function saveLocalNotes(notes) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(notes));
}

function getLastSync() {
    return localStorage.getItem(LAST_SYNC_KEY);
}

function setLastSync() {
    localStorage.setItem(
        LAST_SYNC_KEY,
        new Date().toISOString()
    );
}

/*
|--------------------------------------------------------------------------
| Generate Unique Client ID
|--------------------------------------------------------------------------
*/

function generateClientId() {
    if (window.crypto && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return "note-" +
        Date.now() +
        "-" +
        Math.random().toString(36).substring(2, 10);
}

/*
|--------------------------------------------------------------------------
| Save Note
|--------------------------------------------------------------------------
*/

function saveNote() {
    const titleElement = document.getElementById("title");
    const contentElement = document.getElementById("content");

    const title = titleElement.value.trim();
    const content = contentElement.value.trim();

    if (!title || !content) {
        setStatus("Title and content required!", "red");
        return;
    }

    const note = {
        client_id: generateClientId(),
        title: title,
        content: content,
        updated_at: new Date().toISOString(),

        sync_status: navigator.onLine ? "pending" : "offline",
        sync_attempts: 0,
        last_error: null
    };

    const notes = getLocalNotes();

    notes.push(note);

    saveLocalNotes(notes);

    titleElement.value = "";
    contentElement.value = "";

    setStatus(
        navigator.onLine
            ? "Note saved. Syncing..."
            : "Saved offline!",
        navigator.onLine ? "blue" : "green"
    );

    updateDashboard();

    if (navigator.onLine) {
        syncNotes();
    }
}

/*
|--------------------------------------------------------------------------
| Status Message
|--------------------------------------------------------------------------
*/

function setStatus(message, color = "green") {
    const element = document.getElementById("status");

    if (!element) {
        return;
    }

    element.innerText = message;
    element.style.color = color;
}

/*
|--------------------------------------------------------------------------
| Online / Offline Status
|--------------------------------------------------------------------------
*/

function updateOnlineStatus() {
    const badge = document.getElementById("offlineBadge");
    const connectionStatus = document.getElementById("connectionStatus");

    if (!navigator.onLine) {
        if (badge) {
            badge.style.display = "inline-block";
        }

        if (connectionStatus) {
            connectionStatus.innerText = "🔴 Offline";
        }
    } else {
        if (badge) {
            badge.style.display = "none";
        }

        if (connectionStatus) {
            connectionStatus.innerText = "🟢 Online";
        }
    }

    updateDashboard();
}

/*
|--------------------------------------------------------------------------
| Sync Notes
|--------------------------------------------------------------------------
*/

async function syncNotes() {
    if (!navigator.onLine) {
        updateDashboard();
        return;
    }

    if (syncInProgress) {
        return;
    }

    const localNotes = getLocalNotes();

    if (localNotes.length === 0) {
        updateDashboard();
        return;
    }

    syncInProgress = true;

    clearTimeout(retryTimer);

    setStatus(
        "Syncing " + localNotes.length + " pending note(s)...",
        "blue"
    );

    /*
     * Increase attempt count before sending.
     */
    localNotes.forEach(note => {
        note.sync_attempts =
            Number(note.sync_attempts || 0) + 1;

        note.sync_status = "syncing";
        note.last_error = null;
    });

    saveLocalNotes(localNotes);
    updateDashboard();

    try {
        const response = await fetch("/api/notes/sync", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                notes: localNotes
            })
        });

        if (!response.ok) {
            throw new Error(
                "Server returned HTTP " + response.status
            );
        }

        const result = await response.json();

        /*
         * Client IDs successfully synchronized.
         */
        const syncedIds = (result.synced || [])
            .map(item => item.client_id);

        /*
         * Conflict IDs must remain in LocalStorage.
         */
        const conflictIds = (result.conflicts || [])
            .map(item => item.client_id);

        /*
         * Failed IDs must remain in LocalStorage.
         */
        const failedIds = (result.failed || [])
            .map(item => item.client_id);

        let remainingNotes = getLocalNotes().filter(note => {
            return (
                conflictIds.includes(note.client_id) ||
                failedIds.includes(note.client_id) ||
                !syncedIds.includes(note.client_id)
            );
        });

        /*
         * Mark conflicts.
         */
        remainingNotes = remainingNotes.map(note => {
            if (conflictIds.includes(note.client_id)) {
                return {
                    ...note,
                    sync_status: "conflict",
                    last_error: "Sync conflict detected."
                };
            }

            if (failedIds.includes(note.client_id)) {
                const failedItem = (result.failed || [])
                    .find(item => item.client_id === note.client_id);

                return {
                    ...note,
                    sync_status: "failed",
                    last_error: failedItem
                        ? failedItem.error
                        : "Synchronization failed."
                };
            }

            return note;
        });

        saveLocalNotes(remainingNotes);

        setLastSync();

        if ((result.conflicts || []).length > 0) {
            setStatus(
                "Sync completed with conflicts. Please resolve them.",
                "orange"
            );
        } else if ((result.failed || []).length > 0) {
            setStatus(
                "Some notes failed to sync. Retry available.",
                "orange"
            );
        } else {
            setStatus(
                "All notes synced successfully!",
                "green"
            );
        }

        displayConflicts(result.conflicts || []);

        await loadServerNotes();

    } catch (error) {
        console.error("Sync failed:", error);

        const notes = getLocalNotes().map(note => ({
            ...note,
            sync_status: "failed",
            last_error: error.message
        }));

        saveLocalNotes(notes);

        setStatus(
            "Sync failed. Automatic retry will continue.",
            "orange"
        );

        scheduleRetry();
    } finally {
        syncInProgress = false;
        updateDashboard();
    }
}

/*
|--------------------------------------------------------------------------
| Automatic Retry
|--------------------------------------------------------------------------
*/

function scheduleRetry() {
    clearTimeout(retryTimer);

    retryTimer = setTimeout(() => {
        if (navigator.onLine) {
            syncNotes();
        }
    }, 5000);
}

/*
|--------------------------------------------------------------------------
| Manual Retry
|--------------------------------------------------------------------------
*/

function retryFailedSync() {
    const notes = getLocalNotes();

    notes.forEach(note => {
        if (
            note.sync_status === "failed" ||
            note.sync_status === "offline"
        ) {
            note.sync_status = "pending";
            note.last_error = null;
        }
    });

    saveLocalNotes(notes);

    if (navigator.onLine) {
        setStatus("Retrying synchronization...", "blue");
        syncNotes();
    } else {
        setStatus(
            "You are offline. Retry will happen when online.",
            "orange"
        );
    }

    updateDashboard();
}

/*
|--------------------------------------------------------------------------
| Manual Sync
|--------------------------------------------------------------------------
*/

function manualSync() {
    if (!navigator.onLine) {
        setStatus(
            "Cannot sync while offline.",
            "red"
        );
        return;
    }

    setStatus("Manual synchronization started...", "blue");

    syncNotes();
}

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

function updateDashboard() {
    const notes = getLocalNotes();

    const pending = notes.filter(note =>
        note.sync_status === "pending" ||
        note.sync_status === "offline" ||
        note.sync_status === "syncing"
    ).length;

    const failed = notes.filter(note =>
        note.sync_status === "failed"
    ).length;

    const conflicts = notes.filter(note =>
        note.sync_status === "conflict"
    ).length;

    const localCountElement =
        document.getElementById("localNotesCount");

    const pendingElement =
        document.getElementById("pendingNotesCount");

    const failedElement =
        document.getElementById("failedNotesCount");

    const conflictElement =
        document.getElementById("conflictNotesCount");

    if (localCountElement) {
        localCountElement.innerText = notes.length;
    }

    if (pendingElement) {
        pendingElement.innerText = pending;
    }

    if (failedElement) {
        failedElement.innerText = failed;
    }

    if (conflictElement) {
        conflictElement.innerText = conflicts;
    }

    const lastSyncElement =
        document.getElementById("lastSyncTime");

    if (lastSyncElement) {
        const lastSync = getLastSync();

        lastSyncElement.innerText = lastSync
            ? new Date(lastSync).toLocaleString()
            : "Never";
    }

    renderPendingQueue();
}

/*
|--------------------------------------------------------------------------
| Pending Queue
|--------------------------------------------------------------------------
*/

function renderPendingQueue() {
    const container =
        document.getElementById("pendingQueue");

    if (!container) {
        return;
    }

    const notes = getLocalNotes();

    if (notes.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                No pending offline notes.
            </div>
        `;

        return;
    }

    container.innerHTML = notes.map(note => {

        let badgeClass = "pending";
        let statusText = "Pending";

        if (note.sync_status === "failed") {
            badgeClass = "failed";
            statusText = "Failed";
        }

        if (note.sync_status === "conflict") {
            badgeClass = "conflict";
            statusText = "Conflict";
        }

        if (note.sync_status === "syncing") {
            badgeClass = "syncing";
            statusText = "Syncing";
        }

        return `
            <div class="queue-item">
                <div>
                    <strong>${escapeHtml(note.title)}</strong>

                    <small>
                        Attempts:
                        ${Number(note.sync_attempts || 0)}
                    </small>

                    ${
                        note.last_error
                            ? `<small class="error-text">
                                ${escapeHtml(note.last_error)}
                               </small>`
                            : ""
                    }
                </div>

                <span class="queue-badge ${badgeClass}">
                    ${statusText}
                </span>
            </div>
        `;
    }).join("");
}

/*
|--------------------------------------------------------------------------
| Load Server Notes
|--------------------------------------------------------------------------
*/

async function loadServerNotes() {
    if (!navigator.onLine) {
        return;
    }

    const container =
        document.getElementById("serverNotes");

    if (!container) {
        return;
    }

    try {
        const response = await fetch("/api/notes");

        if (!response.ok) {
            throw new Error("Unable to load server notes.");
        }

        const result = await response.json();

        const notes = result.notes || [];

        if (notes.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    No notes found on server.
                </div>
            `;

            return;
        }

        container.innerHTML = notes.map(note => `
            <div class="server-note">
                <h4>${escapeHtml(note.title)}</h4>

                <p>
                    ${escapeHtml(note.content)}
                </p>

                <small>
                    Last updated:
                    ${new Date(note.updated_at).toLocaleString()}
                </small>
            </div>
        `).join("");

    } catch (error) {
        console.error(error);

        container.innerHTML = `
            <div class="empty-state">
                Unable to load server notes.
            </div>
        `;
    }
}

/*
|--------------------------------------------------------------------------
| Conflict Display
|--------------------------------------------------------------------------
*/

function displayConflicts(conflicts) {
    const container =
        document.getElementById("conflictContainer");

    if (!container) {
        return;
    }

    if (!conflicts.length) {
        container.innerHTML = "";
        return;
    }

    container.innerHTML = conflicts.map(conflict => `
        <div class="conflict-card">

            <h3>⚔️ Sync Conflict</h3>

            <div class="conflict-columns">

                <div class="version-box">
                    <h4>📱 Local Version</h4>

                    <strong>
                        ${escapeHtml(conflict.local.title)}
                    </strong>

                    <p>
                        ${escapeHtml(conflict.local.content)}
                    </p>

                    <small>
                        Updated:
                        ${new Date(
                            conflict.local.updated_at
                        ).toLocaleString()}
                    </small>

                    <button
                        class="resolve-local"
                        onclick="keepLocalVersion(
                            '${escapeAttribute(conflict.client_id)}'
                        )"
                    >
                        Keep Local
                    </button>
                </div>

                <div class="version-box">
                    <h4>☁️ Server Version</h4>

                    <strong>
                        ${escapeHtml(conflict.server.title)}
                    </strong>

                    <p>
                        ${escapeHtml(conflict.server.content)}
                    </p>

                    <small>
                        Updated:
                        ${new Date(
                            conflict.server.updated_at
                        ).toLocaleString()}
                    </small>

                    <button
                        class="resolve-server"
                        onclick="keepServerVersion(
                            '${escapeAttribute(conflict.client_id)}'
                        )"
                    >
                        Keep Server
                    </button>
                </div>

            </div>

        </div>
    `).join("");
}

/*
|--------------------------------------------------------------------------
| Keep Local Version
|--------------------------------------------------------------------------
*/

async function keepLocalVersion(clientId) {
    const notes = getLocalNotes();

    const note = notes.find(
        item => item.client_id === clientId
    );

    if (!note) {
        return;
    }

    try {
        const response = await fetch(
            "/api/notes/resolve/local",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    client_id: note.client_id,
                    title: note.title,
                    content: note.content
                })
            }
        );

        if (!response.ok) {
            throw new Error(
                "Unable to keep local version."
            );
        }

        const updatedNotes = getLocalNotes()
            .filter(item => item.client_id !== clientId);

        saveLocalNotes(updatedNotes);

        setStatus(
            "Local version kept successfully.",
            "green"
        );

        updateDashboard();

        await loadServerNotes();

    } catch (error) {
        setStatus(
            error.message,
            "red"
        );
    }
}

/*
|--------------------------------------------------------------------------
| Keep Server Version
|--------------------------------------------------------------------------
*/

async function keepServerVersion(clientId) {
    try {
        const response = await fetch(
            "/api/notes/resolve/server",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    client_id: clientId
                })
            }
        );

        if (!response.ok) {
            throw new Error(
                "Unable to keep server version."
            );
        }

        const updatedNotes = getLocalNotes()
            .filter(note => note.client_id !== clientId);

        saveLocalNotes(updatedNotes);

        setStatus(
            "Server version kept successfully.",
            "green"
        );

        updateDashboard();

        await loadServerNotes();

    } catch (error) {
        setStatus(
            error.message,
            "red"
        );
    }
}

/*
|--------------------------------------------------------------------------
| Utility: Escape HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function escapeAttribute(value) {
    return String(value)
        .replace(/\\/g, "\\\\")
        .replace(/'/g, "\\'");
}

/*
|--------------------------------------------------------------------------
| Events
|--------------------------------------------------------------------------
*/

window.addEventListener("online", () => {
    updateOnlineStatus();

    setStatus(
        "Back online. Syncing...",
        "blue"
    );

    syncNotes();
});

window.addEventListener("offline", () => {
    updateOnlineStatus();

    setStatus(
        "You are offline. Notes will be saved locally.",
        "orange"
    );
});

window.addEventListener("load", () => {
    updateOnlineStatus();
    updateDashboard();

    if (navigator.onLine) {
        loadServerNotes();
        syncNotes();
    }
});