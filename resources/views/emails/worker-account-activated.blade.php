@extends('emails.layout')

@section('title', 'Konto zostało aktywowane')
@section('preheader', 'Twoje konto zostało aktywowane i jest gotowe do użycia.')

@section('content')
    <h1 style="margin: 0 0 14px; color: #1a1612; font-family: Arial, Helvetica, sans-serif; font-size: 28px; line-height: 34px; font-weight: 700;">
        Konto aktywowane
    </h1>

    <p style="margin: 0 0 16px; color: #1a1612; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 25px;">
        Cześć, {{ $firstName }}! Twoje konto zostało pomyślnie aktywowane.
    </p>

    @include('emails.partials.login-info', ['username' => $username])

    <p style="margin: 0; color: #1a1612; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 25px;">
        Możesz teraz zalogować się do swojego panelu.
    </p>

    @include('emails.partials.action-button', [
        'url' => $loginUrl,
        'label' => 'Zaloguj się',
    ])

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#faf8f3" style="width: 100%; background-color: #faf8f3; border-left: 4px solid #d4cdbe; border-collapse: separate; border-spacing: 0;">
        <tr>
            <td style="padding: 14px 16px;">
                <p style="margin: 0; color: #6b6357; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 20px;">
                    Jeśli nie aktywowałeś tego konta, skontaktuj się z administratorem.
                </p>
            </td>
        </tr>
    </table>
@endsection
