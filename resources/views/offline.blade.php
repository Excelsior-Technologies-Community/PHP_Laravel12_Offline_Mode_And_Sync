<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>You Are Offline</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <style>

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(
                135deg,
                #667eea,
                #764ba2
            );

            display: flex;
            justify-content: center;
            align-items: center;

            min-height: 100vh;

            color: white;
            text-align: center;
        }

        .card {
            width: 90%;
            max-width: 450px;

            background: rgba(
                255,
                255,
                255,
                0.12
            );

            padding: 40px;

            border-radius: 18px;

            backdrop-filter: blur(10px);

            box-shadow:
                0 15px 40px
                rgba(0,0,0,0.2);
        }

        .emoji {
            font-size: 60px;
        }

        h2 {
            font-size: 30px;
            margin-bottom: 10px;
        }

        p {
            font-size: 16px;
            line-height: 1.6;
            opacity: 0.9;
        }

    </style>

</head>

<body>

    <div class="card">

        <div class="emoji">
            📡
        </div>

        <h2>
            You are offline
        </h2>

        <p>
            Your notes are safe in the
            local offline queue.
        </p>

        <p>
            They will automatically synchronize
            when your internet connection returns.
        </p>

    </div>

</body>

</html>