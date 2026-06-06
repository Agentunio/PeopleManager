<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Przerwa techniczna - PeopleManager</title>
    <style>
        :root {
            --bg: #101010;
            --surface: #181818;
            --surface-soft: #202020;
            --accent: #e50914;
            --text: #f0f0f0;
            --muted: #b8b8b8;
            --border: #333333;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 32px 18px;
            background:
                linear-gradient(135deg, rgba(229, 9, 20, 0.14), transparent 34%),
                radial-gradient(circle at 78% 18%, rgba(240, 240, 240, 0.08), transparent 28%),
                var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(100%, 560px);
            padding: 36px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: linear-gradient(180deg, var(--surface), var(--surface-soft));
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.38);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .status::before {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 6px rgba(229, 9, 20, 0.18);
            content: "";
        }

        h1 {
            margin: 0 0 14px;
            font-size: clamp(28px, 6vw, 42px);
            line-height: 1.08;
            letter-spacing: 0;
        }

        p {
            margin: 0;
            color: var(--muted);
            font-size: 17px;
            line-height: 1.65;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-top: 32px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 14px;
        }

        @media (max-width: 520px) {
            main {
                padding: 28px 22px;
            }

            .footer {
                display: grid;
            }
        }
    </style>
</head>
<body>
<main>
    <div class="status">Przerwa techniczna</div>
    <h1>Za chwilę wracamy</h1>
    <p>
        Pracujemy nad ulepszeniem naszego systemu!
        Za problemy przepraszamy. Wróć później.
    </p>
    <div class="footer">
        <span>Kod odpowiedzi: 503</span>
    </div>
</main>
</body>
</html>
