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
