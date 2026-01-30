<!DOCTYPE html>
<html>
<head>
    <title>Offline</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: white;
            text-align: center;
        }

        .card {
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 400px;
        }

        h2 {
            margin-bottom: 10px;
            font-size: 28px;
        }

        p {
            font-size: 16px;
            opacity: 0.9;
        }

        .emoji {
            font-size: 50px;
            margin-bottom: 15px;
        }
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
