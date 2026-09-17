const STORAGE_KEY = "offline_notes";
const LAST_SYNC_KEY = "offline_notes_last_sync";

let serverNotes = [];
let currentFilter = "all";
let currentSearch = "";

// =====================================================
// LOCAL STORAGE
// =====================================================

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

function setLastSync(value) {
    localStorage.setItem(LAST_SYNC_KEY, value);
}

// =====================================================
// HELPERS
// =====================================================

function generateClientId() {
    if (window.crypto && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return "client-" + Date.now() + "-" + Math.random().toString(36).substring(2);
}

function escapeHtml(value) {
    const div = document.createElement("div");
    div.textContent = value ?? "";
    return div.innerHTML;
}

function normalizeTags(tags) {
    if (!tags) {
        return [];
    }

    if (Array.isArray(tags)) {
        return tags.filter(tag => String(tag).trim() !== "");
    }

    if (typeof tags === "string") {
        try {
            const parsed = JSON.parse(tags);

            if (Array.isArray(parsed)) {
                return parsed.filter(tag => String(tag).trim() !== "");
            }
        } catch (error) {
            // Continue with comma-separated handling.
        }

        return tags
            .split(",")
            .map(tag => tag.trim())
            .filter(tag => tag !== "");
    }

    return [];
}

function formatTags(tags) {
    const normalized = normalizeTags(tags);

    if (!normalized.length) {
        return "";
    }

    return `
        <div class="note-tags">
            🏷️ ${normalized.map(tag => escapeHtml(tag)).join(", ")}
        </div>
    `;
}

function formatDate(value) {
    if (!value) {
        return "Never";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return escapeHtml(value);
    }

    return date.toLocaleString();
}

function noteIsDeleted(note) {
    return Boolean(
        note.deleted === true ||
        note.is_deleted === true ||
        note.action === "delete" ||
        note.sync_status === "deleted"
    );
}

function noteIsPending(note) {
    return (
        note.sync_status === "pending" ||
        note.sync_status === "failed" ||
        note.sync_status === "conflict"
    );
}

function noteIsFailed(note) {
    return note.sync_status === "failed";
}

function noteIsConflict(note) {
    return note.sync_status === "conflict";
}

function noteIsFavorite(note) {
    return note.is_favorite === true || note.is_favorite === 1;
}

function noteIsPinned(note) {
    return note.is_pinned === true || note.is_pinned === 1;
}

// =====================================================
// ONLINE / OFFLINE STATUS
// =====================================================

function updateOnlineStatus() {
    const statusElement = document.getElementById("onlineStatus");

    if (!statusElement) {
        return;
    }

    if (navigator.onLine) {
        statusElement.textContent = "🟢 Online";
        statusElement.className = "status online";
    } else {
        statusElement.textContent = "🔴 Offline";
        statusElement.className = "status offline";
    }
}

// =====================================================
// SAVE NOTE
// =====================================================

function saveNote() {
    const titleInput = document.getElementById("noteTitle");
    const contentInput = document.getElementById("noteContent");
    const tagsInput = document.getElementById("noteTags");

    if (!titleInput || !contentInput) {
        return;
    }

    const title = titleInput.value.trim();
    const content = contentInput.value.trim();

    if (!title) {
        alert("Please enter a note title.");
        titleInput.focus();
        return;
    }

    if (!content) {
        alert("Please enter note content.");
        contentInput.focus();
        return;
    }

    const tags = tagsInput
        ? tagsInput.value
            .split(",")
            .map(tag => tag.trim())
            .filter(tag => tag !== "")
        : [];

    const notes = getLocalNotes();

    notes.push({
        client_id: generateClientId(),
        title: title,
        content: content,
        tags: tags,
        is_favorite: false,
        is_pinned: false,
        updated_at: new Date().toISOString(),
        sync_status: "pending",
        action: "upsert"
    });

    saveLocalNotes(notes);

    titleInput.value = "";
    contentInput.value = "";

    if (tagsInput) {
        tagsInput.value = "";
    }

    updateDashboard();
    renderLocalNotes();
    renderPendingQueue();

    if (navigator.onLine) {
        syncNotes();
    }
}

// =====================================================
// DELETE LOCAL NOTE
// =====================================================

function deleteLocalNote(clientId) {
    if (!confirm("Delete this note?")) {
        return;
    }

    const notes = getLocalNotes();

    const note = notes.find(item => item.client_id === clientId);

    if (!note) {
        return;
    }

    note.deleted = true;
    note.action = "delete";
    note.sync_status = "pending";
    note.updated_at = new Date().toISOString();

    saveLocalNotes(notes);

    updateDashboard();
    renderLocalNotes();
    renderPendingQueue();

    if (navigator.onLine) {
        syncNotes();
    }
}

// =====================================================
// TOGGLE FAVORITE
// =====================================================

async function toggleFavorite(clientId, isFavorite) {
    const notes = getLocalNotes();

    const localNote = notes.find(note => note.client_id === clientId);

    if (localNote) {
        localNote.is_favorite = isFavorite;
        localNote.sync_status = "pending";
        localNote.action = "upsert";
        localNote.updated_at = new Date().toISOString();

        saveLocalNotes(notes);
    }

    try {
        const response = await fetch(
            `/api/notes/${encodeURIComponent(clientId)}/favorite`,
            {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    is_favorite: isFavorite
                })
            }
        );

        if (!response.ok && !localNote) {
            throw new Error("Unable to update favorite.");
        }
    } catch (error) {
        console.warn("Favorite update queued:", error);
    }

    await loadServerNotes();
    updateDashboard();
    renderLocalNotes();
}

// =====================================================
// TOGGLE PIN
// =====================================================

async function togglePin(clientId, isPinned) {
    const notes = getLocalNotes();

    const localNote = notes.find(note => note.client_id === clientId);

    if (localNote) {
        localNote.is_pinned = isPinned;
        localNote.sync_status = "pending";
        localNote.action = "upsert";
        localNote.updated_at = new Date().toISOString();

        saveLocalNotes(notes);
    }

    try {
        const response = await fetch(
            `/api/notes/${encodeURIComponent(clientId)}/pin`,
            {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    is_pinned: isPinned
                })
            }
        );

        if (!response.ok && !localNote) {
            throw new Error("Unable to update pin.");
        }
    } catch (error) {
        console.warn("Pin update queued:", error);
    }

    await loadServerNotes();
    updateDashboard();
    renderLocalNotes();
}

// =====================================================
// SYNC NOTES
// =====================================================

async function syncNotes() {
    if (!navigator.onLine) {
        updateDashboard();
        return;
    }

    const notes = getLocalNotes();

    if (!notes.length) {
        updateDashboard();
        return;
    }

    const pendingNotes = notes.filter(note =>
        note.sync_status === "pending" ||
        note.sync_status === "failed" ||
        note.sync_status === "conflict"
    );

    if (!pendingNotes.length) {
        updateDashboard();
        return;
    }

    const syncButton = document.getElementById("syncButton");

    if (syncButton) {
        syncButton.disabled = true;
        syncButton.textContent = "⏳ Syncing...";
    }

    try {
        const payloadNotes = pendingNotes.map(note => ({
            client_id: note.client_id,
            title: note.title || "",
            content: note.content || "",
            tags: normalizeTags(note.tags),
            is_favorite: Boolean(note.is_favorite),
            is_pinned: Boolean(note.is_pinned),
            updated_at: note.updated_at || new Date().toISOString(),
            action: note.action || "upsert",
            deleted: noteIsDeleted(note)
        }));

        const response = await fetch("/api/notes/sync", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                notes: payloadNotes
            })
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(
                result.message || "Synchronization failed."
            );
        }

        const updatedLocalNotes = getLocalNotes();

        const syncedIds = new Set(
            (result.synced || [])
                .map(item => item.client_id)
        );

        const deletedIds = new Set(
            (result.deleted || [])
                .map(item => item.client_id)
        );

        const conflictIds = new Set(
            (result.conflicts || [])
                .map(item => item.client_id)
        );

        const failedIds = new Set(
            (result.failed || [])
                .map(item => item.client_id)
        );

        const finalNotes = [];

        for (const note of updatedLocalNotes) {
            if (syncedIds.has(note.client_id)) {
                continue;
            }

            if (deletedIds.has(note.client_id)) {
                continue;
            }

            if (conflictIds.has(note.client_id)) {
                note.sync_status = "conflict";
                finalNotes.push(note);
                continue;
            }

            if (failedIds.has(note.client_id)) {
                note.sync_status = "failed";
                finalNotes.push(note);
                continue;
            }

            finalNotes.push(note);
        }

        saveLocalNotes(finalNotes);

        setLastSync(new Date().toISOString());

        await loadServerNotes();

        updateDashboard();
        renderLocalNotes();
        renderPendingQueue();
        renderConflicts();

        if (typeof renderSyncHistory === "function") {
            renderSyncHistory();
        }

        alert(
            `Synchronization completed.\n\n` +
            `Synced: ${(result.synced || []).length}\n` +
            `Deleted: ${(result.deleted || []).length}\n` +
            `Conflicts: ${(result.conflicts || []).length}\n` +
            `Failed: ${(result.failed || []).length}`
        );

    } catch (error) {
        console.error("Sync error:", error);

        const localNotes = getLocalNotes();

        localNotes.forEach(note => {
            if (
                note.sync_status === "pending" &&
                pendingNotes.some(
                    pending => pending.client_id === note.client_id
                )
            ) {
                note.sync_status = "failed";
                note.last_sync_error = error.message;
            }
        });

        saveLocalNotes(localNotes);

        updateDashboard();
        renderPendingQueue();

        console.error(error.message);
    } finally {
        if (syncButton) {
            syncButton.disabled = false;
            syncButton.textContent = "🔄 Sync Now";
        }
    }
}

// =====================================================
// RETRY FAILED
// =====================================================

function retryFailed() {
    const notes = getLocalNotes();

    let retryCount = 0;

    notes.forEach(note => {
        if (note.sync_status === "failed") {
            note.sync_status = "pending";
            note.last_sync_error = null;
            retryCount++;
        }
    });

    saveLocalNotes(notes);

    updateDashboard();
    renderPendingQueue();

    if (!retryCount) {
        alert("No failed items to retry.");
        return;
    }

    if (navigator.onLine) {
        syncNotes();
    } else {
        alert("You are offline. Failed items have been moved back to pending.");
    }
}

// =====================================================
// CLEAR QUEUE
// =====================================================

function clearQueue() {
    const notes = getLocalNotes();

    const queueItems = notes.filter(note =>
        note.sync_status === "pending" ||
        note.sync_status === "failed" ||
        note.sync_status === "conflict"
    );

    if (!queueItems.length) {
        alert("No queue items to clear.");
        return;
    }

    if (!confirm(
        `Clear ${queueItems.length} queue item(s)?\n\n` +
        "Unsynchronized local changes will be removed."
    )) {
        return;
    }

    const remaining = notes.filter(note =>
        note.sync_status !== "pending" &&
        note.sync_status !== "failed" &&
        note.sync_status !== "conflict"
    );

    saveLocalNotes(remaining);

    updateDashboard();
    renderLocalNotes();
    renderPendingQueue();
    renderConflicts();
}

// =====================================================
// LOAD SERVER NOTES
// =====================================================

async function loadServerNotes() {
    try {
        const search = currentSearch
            ? `?search=${encodeURIComponent(currentSearch)}`
            : "";

        const response = await fetch(`/api/notes${search}`, {
            headers: {
                "Accept": "application/json"
            }
        });

        if (!response.ok) {
            throw new Error("Unable to load server notes.");
        }

        const data = await response.json();

        serverNotes = data.notes || [];

        renderServerNotes();
        updateDashboard();

    } catch (error) {
        console.error("Server note loading error:", error);
    }
}

// =====================================================
// RENDER LOCAL NOTES
// =====================================================

function renderLocalNotes() {
    const container = document.getElementById("localNotes");

    if (!container) {
        return;
    }

    let notes = getLocalNotes();

    notes = notes.filter(note => !noteIsDeleted(note));

    if (currentFilter === "favorites") {
        notes = notes.filter(note => noteIsFavorite(note));
    }

    if (currentFilter === "pinned") {
        notes = notes.filter(note => noteIsPinned(note));
    }

    if (currentFilter === "pending") {
        notes = notes.filter(note => noteIsPending(note));
    }

    if (currentSearch) {
        const search = currentSearch.toLowerCase();

        notes = notes.filter(note => {
            const title = String(note.title || "").toLowerCase();
            const content = String(note.content || "").toLowerCase();
            const tags = normalizeTags(note.tags)
                .join(" ")
                .toLowerCase();

            return (
                title.includes(search) ||
                content.includes(search) ||
                tags.includes(search)
            );
        });
    }

    if (!notes.length) {
        container.innerHTML = `
            <div class="empty-state">
                No local notes found.
            </div>
        `;
        return;
    }

    notes.sort((a, b) => {
        if (noteIsPinned(a) !== noteIsPinned(b)) {
            return noteIsPinned(b) - noteIsPinned(a);
        }

        return new Date(b.updated_at || 0) -
            new Date(a.updated_at || 0);
    });

    container.innerHTML = notes.map(note => `
        <div class="note-card">

            <div class="note-header">

                <h3>
                    ${noteIsPinned(note) ? "📌 " : ""}
                    ${noteIsFavorite(note) ? "⭐ " : ""}
                    ${escapeHtml(note.title)}
                </h3>

                <span class="sync-badge ${escapeHtml(
                    note.sync_status || "synced"
                )}">
                    ${escapeHtml(note.sync_status || "synced")}
                </span>

            </div>

            <p>
                ${escapeHtml(note.content)}
            </p>

            ${formatTags(note.tags)}

            <small>
                Last updated:
                ${formatDate(note.updated_at)}
            </small>

            <div class="note-actions">

                <button
                    type="button"
                    onclick="toggleFavorite(
                        '${escapeHtml(note.client_id)}',
                        ${!noteIsFavorite(note)}
                    )"
                >
                    ${noteIsFavorite(note)
                        ? "⭐ Unfavorite"
                        : "☆ Favorite"}
                </button>

                <button
                    type="button"
                    onclick="togglePin(
                        '${escapeHtml(note.client_id)}',
                        ${!noteIsPinned(note)}
                    )"
                >
                    ${noteIsPinned(note)
                        ? "📌 Unpin"
                        : "📍 Pin"}
                </button>

                <button
                    type="button"
                    onclick="deleteLocalNote(
                        '${escapeHtml(note.client_id)}'
                    )"
                >
                    🗑️ Delete
                </button>

            </div>

        </div>
    `).join("");
}

// =====================================================
// RENDER SERVER NOTES
// =====================================================

function renderServerNotes() {
    const container = document.getElementById("serverNotes");

    if (!container) {
        return;
    }

    let notes = [...serverNotes];

    notes = notes.filter(note => !note.deleted_at);

    if (currentFilter === "favorites") {
        notes = notes.filter(note => noteIsFavorite(note));
    }

    if (currentFilter === "pinned") {
        notes = notes.filter(note => noteIsPinned(note));
    }

    if (currentSearch) {
        const search = currentSearch.toLowerCase();

        notes = notes.filter(note => {
            const title = String(note.title || "").toLowerCase();
            const content = String(note.content || "").toLowerCase();
            const tags = normalizeTags(note.tags)
                .join(" ")
                .toLowerCase();

            return (
                title.includes(search) ||
                content.includes(search) ||
                tags.includes(search)
            );
        });
    }

    notes.sort((a, b) => {
        if (noteIsPinned(a) !== noteIsPinned(b)) {
            return noteIsPinned(b) - noteIsPinned(a);
        }

        return new Date(b.updated_at || 0) -
            new Date(a.updated_at || 0);
    });

    if (!notes.length) {
        container.innerHTML = `
            <div class="empty-state">
                No server notes found.
            </div>
        `;
        return;
    }

    container.innerHTML = notes.map(note => `
        <div class="note-card">

            <div class="note-header">

                <h3>
                    ${noteIsPinned(note) ? "📌 " : ""}
                    ${noteIsFavorite(note) ? "⭐ " : ""}
                    ${escapeHtml(note.title)}
                </h3>

            </div>

            <p>
                ${escapeHtml(note.content)}
            </p>

            ${formatTags(note.tags)}

            <small>
                Last updated:
                ${formatDate(note.updated_at)}
            </small>

            <div class="note-actions">

                <button
                    type="button"
                    onclick="toggleFavorite(
                        '${escapeHtml(note.client_id)}',
                        ${!noteIsFavorite(note)}
                    )"
                >
                    ${noteIsFavorite(note)
                        ? "⭐ Unfavorite"
                        : "☆ Favorite"}
                </button>

                <button
                    type="button"
                    onclick="togglePin(
                        '${escapeHtml(note.client_id)}',
                        ${!noteIsPinned(note)}
                    )"
                >
                    ${noteIsPinned(note)
                        ? "📌 Unpin"
                        : "📍 Pin"}
                </button>

            </div>

        </div>
    `).join("");
}

// =====================================================
// PENDING QUEUE
// =====================================================

function renderPendingQueue() {
    const container = document.getElementById("pendingQueue");

    if (!container) {
        return;
    }

    const notes = getLocalNotes().filter(note =>
        note.sync_status === "pending" ||
        note.sync_status === "failed" ||
        note.sync_status === "conflict"
    );

    if (!notes.length) {
        container.innerHTML = `
            <div class="empty-state">
                No local queue items.
            </div>
        `;
        return;
    }

    container.innerHTML = notes.map(note => `
        <div class="queue-item">

            <strong>
                ${noteIsDeleted(note) ? "🗑️ " : ""}
                ${escapeHtml(note.title || "Untitled")}
            </strong>

            <span>
                ${escapeHtml(note.sync_status || "pending")}
            </span>

            ${note.last_sync_error
                ? `
                    <div class="error-message">
                        ${escapeHtml(note.last_sync_error)}
                    </div>
                `
                : ""
            }

        </div>
    `).join("");
}

// =====================================================
// CONFLICTS
// =====================================================

function renderConflicts() {
    const container = document.getElementById("conflicts");

    if (!container) {
        return;
    }

    const conflicts = getLocalNotes().filter(note =>
        note.sync_status === "conflict"
    );

    if (!conflicts.length) {
        container.innerHTML = `
            <div class="empty-state">
                If local and server versions conflict,
                select which version should be kept.
            </div>
        `;
        return;
    }

    container.innerHTML = conflicts.map(note => `
        <div class="conflict-card">

            <h3>
                ⚔️ ${escapeHtml(note.title)}
            </h3>

            <p>
                ${escapeHtml(note.content)}
            </p>

            ${formatTags(note.tags)}

            <div class="note-actions">

                <button
                    type="button"
                    onclick="resolveKeepLocal(
                        '${escapeHtml(note.client_id)}'
                    )"
                >
                    Keep Local
                </button>

                <button
                    type="button"
                    onclick="resolveKeepServer(
                        '${escapeHtml(note.client_id)}'
                    )"
                >
                    Keep Server
                </button>

            </div>

        </div>
    `).join("");
}

// =====================================================
// RESOLVE LOCAL
// =====================================================

async function resolveKeepLocal(clientId) {
    const notes = getLocalNotes();

    const note = notes.find(item =>
        item.client_id === clientId
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
                    content: note.content,
                    tags: normalizeTags(note.tags),
                    is_favorite: Boolean(note.is_favorite),
                    is_pinned: Boolean(note.is_pinned)
                })
            }
        );

        if (!response.ok) {
            throw new Error("Unable to keep local version.");
        }

        const remaining = notes.filter(item =>
            item.client_id !== clientId
        );

        saveLocalNotes(remaining);

        await loadServerNotes();

        updateDashboard();
        renderLocalNotes();
        renderPendingQueue();
        renderConflicts();

    } catch (error) {
        console.error(error);
        alert(error.message);
    }
}

// =====================================================
// RESOLVE SERVER
// =====================================================

async function resolveKeepServer(clientId) {
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

        const result = await response.json();

        if (!response.ok) {
            throw new Error(
                result.message || "Unable to keep server version."
            );
        }

        const notes = getLocalNotes().filter(note =>
            note.client_id !== clientId
        );

        saveLocalNotes(notes);

        await loadServerNotes();

        updateDashboard();
        renderLocalNotes();
        renderPendingQueue();
        renderConflicts();

    } catch (error) {
        console.error(error);
        alert(error.message);
    }
}

// =====================================================
// SEARCH
// =====================================================

function handleSearch(value) {
    currentSearch = value.trim();

    renderLocalNotes();
    renderServerNotes();

    if (navigator.onLine) {
        loadServerNotes();
    }
}

// =====================================================
// FILTER
// =====================================================

function setFilter(filter) {
    currentFilter = filter;

    document.querySelectorAll("[data-note-filter]")
        .forEach(button => {
            button.classList.toggle(
                "active",
                button.dataset.noteFilter === filter
            );
        });

    renderLocalNotes();
    renderServerNotes();
}

// =====================================================
// DASHBOARD STATISTICS
// =====================================================

function updateDashboard() {
    const notes = getLocalNotes();

    const activeLocalNotes = notes.filter(
        note => !noteIsDeleted(note)
    );

    const pending = notes.filter(
        note => note.sync_status === "pending"
    );

    const failed = notes.filter(
        note => note.sync_status === "failed"
    );

    const conflicts = notes.filter(
        note => note.sync_status === "conflict"
    );

    const deleted = notes.filter(
        note => noteIsDeleted(note)
    );

    const favorites = [
        ...serverNotes,
        ...activeLocalNotes
    ];

    const uniqueFavorites = new Map();

    favorites.forEach(note => {
        if (noteIsFavorite(note)) {
            uniqueFavorites.set(note.client_id, note);
        }
    });

    const pinned = new Map();

    [
        ...serverNotes,
        ...activeLocalNotes
    ].forEach(note => {
        if (noteIsPinned(note)) {
            pinned.set(note.client_id, note);
        }
    });

    setText("localNotesCount", activeLocalNotes.length);
    setText("pendingCount", pending.length);
    setText("failedCount", failed.length);
    setText("conflictCount", conflicts.length);

    setText("deletedCount", deleted.length);
    setText("favoriteCount", uniqueFavorites.size);
    setText("pinnedCount", pinned.size);

    const lastSync = getLastSync();

    setText(
        "lastSync",
        lastSync ? formatDate(lastSync) : "Never"
    );
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value;
    }
}

// =====================================================
// EXPORT CSV
// =====================================================

function exportNotes() {
    if (!navigator.onLine) {
        alert("Please go online before exporting notes.");
        return;
    }

    window.location.href = "/api/notes/export";
}

// =====================================================
// SYNC STATISTICS
// =====================================================

async function loadSyncStatistics() {
    try {
        const response = await fetch(
            "/api/notes/sync-statistics",
            {
                headers: {
                    "Accept": "application/json"
                }
            }
        );

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        const stats = data.statistics || data;

        if (stats.deleted !== undefined) {
            setText("deletedCount", stats.deleted);
        }

        if (stats.favorites !== undefined) {
            setText("favoriteCount", stats.favorites);
        }

        if (stats.pinned !== undefined) {
            setText("pinnedCount", stats.pinned);
        }

        if (stats.history !== undefined) {
            setText("historyCount", stats.history);
        }

    } catch (error) {
        console.warn(
            "Unable to load sync statistics:",
            error
        );
    }
}

// =====================================================
// SYNC HISTORY
// =====================================================

async function renderSyncHistory() {
    const container = document.getElementById("syncHistory");

    if (!container) {
        return;
    }

    try {
        const response = await fetch(
            "/api/notes/sync-history",
            {
                headers: {
                    "Accept": "application/json"
                }
            }
        );

        if (!response.ok) {
            throw new Error("Unable to load synchronization history.");
        }

        const data = await response.json();

        const histories =
            data.histories ||
            data.history ||
            [];

        setText("historyCount", histories.length);

        if (!histories.length) {
            container.innerHTML = `
                <div class="empty-state">
                    No synchronization history yet.
                </div>
            `;
            return;
        }

        container.innerHTML = histories.map(history => `
            <div class="history-item">

                <strong>
                    ${escapeHtml(history.operation || "sync")}
                </strong>

                <span>
                    ${escapeHtml(history.status || "completed")}
                </span>

                <small>
                    ${formatDate(
                        history.completed_at ||
                        history.created_at
                    )}
                </small>

            </div>
        `).join("");

    } catch (error) {
        console.warn(error);

        container.innerHTML = `
            <div class="empty-state">
                No synchronization history yet.
            </div>
        `;
    }
}

// =====================================================
// INITIALIZE
// =====================================================

async function initializeApp() {
    updateOnlineStatus();

    renderLocalNotes();
    renderPendingQueue();
    renderConflicts();
    updateDashboard();

    if (navigator.onLine) {
        await loadServerNotes();
        await loadSyncStatistics();
        await renderSyncHistory();
    }
}

// =====================================================
// EVENTS
// =====================================================

window.addEventListener("online", async () => {
    updateOnlineStatus();

    await loadServerNotes();
    await syncNotes();
    await loadSyncStatistics();
    await renderSyncHistory();
});

window.addEventListener("offline", () => {
    updateOnlineStatus();
    updateDashboard();
});

window.addEventListener("DOMContentLoaded", initializeApp);

// =====================================================
// EXPOSE FUNCTIONS TO HTML
// =====================================================

window.saveNote = saveNote;
window.syncNotes = syncNotes;
window.retryFailed = retryFailed;
window.clearQueue = clearQueue;
window.deleteLocalNote = deleteLocalNote;
window.toggleFavorite = toggleFavorite;
window.togglePin = togglePin;
window.resolveKeepLocal = resolveKeepLocal;
window.resolveKeepServer = resolveKeepServer;
window.handleSearch = handleSearch;
window.setFilter = setFilter;
window.exportNotes = exportNotes;
window.loadSyncStatistics = loadSyncStatistics;
window.renderSyncHistory = renderSyncHistory;