<!DOCTYPE html>
{{--
    Maintenance page. Rendered ahead of time by `php artisan down --render="errors::503"`
    (see docker/maintenance.sh) and stored in storage/framework/down, so it must stay
    self-contained: no @vite, no hashed build assets. During a deploy the new container
    wipes public/ and copies a fresh snapshot (docker/entrypoint.sh), which would break
    every hashed URL baked into this snapshot. Only stable paths are safe here:
    /fonts/*.woff2 and /images/favicon.png.

    Design tokens mirror resources/css/login.css (paper theme).
--}}
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="60">
    <title>Przerwa techniczna - PeopleManager</title>
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <style>
        @font-face {
            font-family: 'Space Grotesk';
            font-style: normal;
            font-weight: 400 600;
            font-display: swap;
            src: url('/fonts/space-grotesk-latin.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }

        @font-face {
            font-family: 'Space Grotesk';
            font-style: normal;
            font-weight: 400 600;
            font-display: swap;
            src: url('/fonts/space-grotesk-latin-ext.woff2') format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }

        @font-face {
            font-family: 'JetBrains Mono';
            font-style: normal;
            font-weight: 500 600;
            font-display: swap;
            src: url('/fonts/jetbrains-mono-latin.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }

        @font-face {
            font-family: 'JetBrains Mono';
            font-style: normal;
            font-weight: 500 600;
            font-display: swap;
            src: url('/fonts/jetbrains-mono-latin-ext.woff2') format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }

        :root {
            --m-bg: #f5f3ee;
            --m-panel: #ffffff;
            --m-line: #e8e3d6;
            --m-text: #1a1612;
            --m-muted: #6b6357;
            --m-accent: #dc2626;
            --m-shadow: 0 1px 0 rgba(26, 22, 18, 0.04), 0 12px 32px rgba(26, 22, 18, 0.06);
            --m-font: 'Space Grotesk', 'Helvetica Neue', Arial, sans-serif;
            --m-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: var(--m-bg);
        }

        body {
            min-height: 100vh;
            min-height: 100svh;
            margin: 0;
            background: var(--m-bg);
            color: var(--m-text);
            font-family: var(--m-font);
        }

        .maintenance-page {
            width: 100%;
            min-height: 100vh;
            min-height: 100svh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .maintenance-card {
            width: 600px;
            max-width: 100%;
            padding: 46px 50px 42px;
            background: var(--m-panel);
            border: 1px solid var(--m-line);
            border-radius: 10px;
            box-shadow: var(--m-shadow);
        }

        .maintenance-status {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0 0 12px;
            color: var(--m-accent);
            font-family: var(--m-mono);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .maintenance-status::before {
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-radius: 50%;
            background: var(--m-accent);
            animation: maintenance-pulse 1.8s ease-in-out infinite;
            content: "";
        }

        @keyframes maintenance-pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.32);
            }

            50% {
                box-shadow: 0 0 0 5px rgba(220, 38, 38, 0);
            }
        }

        .maintenance-card h1 {
            margin: 0 0 6px;
            color: var(--m-text);
            font-size: 36px;
            font-weight: 500;
            letter-spacing: 0;
            line-height: 1.05;
        }

        .maintenance-card p {
            margin: 0;
            color: var(--m-muted);
            font-size: 16px;
            line-height: 1.45;
        }

        .maintenance-card .maintenance-foot {
            margin: 32px 0 0;
            padding-top: 18px;
            border-top: 1px solid var(--m-line);
            font-size: 13px;
            line-height: 1.5;
        }

        @media (prefers-reduced-motion: reduce) {
            .maintenance-status::before {
                animation: none;
            }
        }

        @media (max-width: 560px) {
            .maintenance-page {
                align-items: stretch;
                padding: 0;
            }

            .maintenance-card {
                width: 100%;
                min-height: 100vh;
                min-height: 100svh;
                padding: 18vh 16px 28px;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .maintenance-status {
                margin-bottom: 10px;
                font-size: 9px;
            }

            .maintenance-card h1 {
                font-size: 24px;
            }

            .maintenance-card p {
                font-size: 12px;
            }

            .maintenance-card .maintenance-foot {
                margin-top: 26px;
                padding-top: 16px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
<main class="maintenance-page" aria-labelledby="maintenance-title">
    <section class="maintenance-card">
        <p class="maintenance-status">Przerwa techniczna</p>
        <header>
            <h1 id="maintenance-title">Trwa aktualizacja systemu</h1>
            <p>Wracamy wkrótce!</p>
        </header>
        <p class="maintenance-foot">W pilnych sprawach skontaktuj się z administratorem.</p>
    </section>
</main>
</body>
</html>
