<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 24px 0; border-collapse: separate; border-spacing: 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
    <tr>
        <td align="center" bgcolor="#dc2626" style="background-color: #dc2626; border-radius: 8px;">
            <a href="{{ $url }}" style="display: inline-block; padding: 0 28px; color: #ffffff; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 48px; font-weight: 700; text-decoration: none; white-space: nowrap;">
                {{ $label }}
            </a>
        </td>
    </tr>
</table>

<p style="margin: 0 0 6px; color: #6b6357; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 18px;">
    Jeśli przycisk nie działa, skopiuj poniższy adres do przeglądarki:
</p>
<p style="margin: 0 0 24px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 18px; word-break: break-all;">
    <a href="{{ $url }}" style="color: #991b1b; text-decoration: underline; word-break: break-all;">{{ $url }}</a>
</p>
