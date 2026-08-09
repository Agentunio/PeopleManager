@extends('emails.layout')

@section('title', 'Ustaw nowe hasło')
@section('preheader', 'Otrzymaliśmy prośbę o zmianę hasła do Twojego konta.')

@section('content')
    <h1 style="margin: 0 0 14px; color: #1a1612; font-family: Arial, Helvetica, sans-serif; font-size: 28px; line-height: 34px; font-weight: 700;">
        Ustaw nowe hasło
    </h1>

    <p style="margin: 0 0 16px; color: #1a1612; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 25px;">
        Otrzymaliśmy prośbę o zmianę hasła do Twojego konta.
    </p>

    @include('emails.partials.login-info', ['username' => $username])

    @include('emails.partials.action-button', [
        'url' => $resetUrl,
        'label' => 'Ustaw nowe hasło',
    ])

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#fef2f2" style="width: 100%; background-color: #fef2f2; border-left: 4px solid #dc2626; border-collapse: separate; border-spacing: 0;">
        <tr>
            <td style="padding: 14px 16px;">
                <p style="margin: 0; color: #6b1d1d; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 20px;">
                    Link jest ważny przez {{ $expiresIn }} minut i może zostać użyty tylko raz.
                    Jeśli nie prosiłeś o zmianę hasła, zignoruj tę wiadomość.
                </p>
            </td>
        </tr>
    </table>
@endsection
