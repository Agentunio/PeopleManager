<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background: #1a1a1a; color: #f0f0f0; margin: 0; padding: 20px; }
        .container { max-width: 520px; margin: 0 auto; background: #242424; border-radius: 8px; padding: 32px; border: 1px solid #333; }
        h1 { color: #e50914; font-size: 22px; margin-top: 0; }
        .info-box { background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 16px; margin: 20px 0; }
        .info-label { color: #888; font-size: 13px; display: block; margin-bottom: 4px; }
        .info-value { color: #f0f0f0; font-size: 16px; font-weight: bold; }
        .btn { display: inline-block; background: #e50914; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: bold; font-size: 15px; margin: 20px 0; }
        .note { color: #888; font-size: 13px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Konto aktywowane!</h1>
        <p>Cześć, {{ $firstName }}! Twoje konto zostało pomyślnie aktywowane.</p>

        <div class="info-box">
            <span class="info-label">Twój login</span>
            <span class="info-value">{{ $username }}</span>
        </div>

        <p>Możesz teraz zalogować się do tutaj:</p>

        <a href="{{ $loginUrl }}" class="btn">Zaloguj się</a>

        <p class="note">Jeśli nie aktywowałeś tego konta, skontaktuj się z administratorem.</p>
    </div>
</body>
</html>
