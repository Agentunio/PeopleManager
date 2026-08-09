<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>@yield('title')</title>
</head>
<body bgcolor="#f5f3ee" style="margin: 0; padding: 0; background-color: #f5f3ee; color: #1a1612; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent; line-height: 1px; font-size: 1px;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f5f3ee" style="width: 100%; margin: 0; padding: 0; background-color: #f5f3ee; border-collapse: collapse; border-spacing: 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
        <tr>
            <td align="center" style="padding: 28px 12px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" bgcolor="#ffffff" style="width: 100%; max-width: 600px; background-color: #ffffff; border: 1px solid #e8e3d6; border-top: 4px solid #dc2626; border-collapse: separate; border-spacing: 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                    <tr>
                        <td style="padding: 24px 36px 20px; border-bottom: 1px solid #e8e3d6;">
                            <p style="margin: 0; color: #991b1b; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 18px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">
                                Sortownia Orlen Paczka&nbsp;&bull;&nbsp;Toruń
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 36px 36px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#faf8f3" style="padding: 18px 36px; background-color: #faf8f3; border-top: 1px solid #e8e3d6;">
                            <p style="margin: 0; color: #6b6357; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 18px;">
                                To jest automatyczna wiadomość. Nie odpowiadaj na nią.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
