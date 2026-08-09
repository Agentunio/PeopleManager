@extends('emails.layout')

@section('title', 'Aktywuj swoje konto')
@section('preheader', 'Twoje konto czeka na aktywację. Link jest ważny przez 24 godziny.')

@section('content')
    <h1 style="margin: 0 0 14px; color: #1a1612; font-family: Arial, Helvetica, sans-serif; font-size: 28px; line-height: 34px; font-weight: 700;">
        Witaj, {{ $firstName }}!
    </h1>

    <p style="margin: 0 0 16px; color: #1a1612; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 25px;">
        Twoje konto w Sortowni Orlen Paczka w Toruniu czeka na aktywację.
    </p>

    @include('emails.partials.login-info', ['username' => $username])

    <p style="margin: 0; color: #1a1612; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 25px;">
        Aby potwierdzić swoją tożsamość i ustawić hasło, użyj poniższego przycisku.
    </p>

    @include('emails.partials.action-button', [
        'url' => $activationUrl,
        'label' => 'Aktywuj konto',
    ])

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#fef2f2" style="width: 100%; background-color: #fef2f2; border-left: 4px solid #dc2626; border-collapse: separate; border-spacing: 0;">
        <tr>
            <td style="padding: 14px 16px;">
                <p style="margin: 0; color: #6b1d1d; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 20px;">
                    Link jest ważny przez 24 godziny. Podczas aktywacji poprosimy Cię o podanie daty urodzenia.
                </p>
            </td>
        </tr>
    </table>
@endsection
