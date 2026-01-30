<!DOCTYPE html>
<html>
<head>
    <title>Offline Notes App</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background: white;
            width: 90%;
            max-width: 450px;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            text-align: center;
        }

        h2 {
            margin-bottom: 20px;
            color: #333;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 14px;
            transition: 0.3s;
        }

        input:focus, textarea:focus {
            border-color: #4facfe;
            outline: none;
            box-shadow: 0 0 0 2px rgba(79,172,254,0.2);
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 15px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        #status {
            margin-top: 15px;
            font-size: 14px;
            color: green;
            min-height: 20px;
        }

        .offline-badge {
            display: none;
            background: #ff4d4f;
            color: white;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 10px;
        }
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

// Show offline badge
function updateOnlineStatus() {
    const badge = document.getElementById('offlineBadge');
    if (!navigator.onLine) {
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }
}

window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);
updateOnlineStatus();
</script>
</body>
</html>
