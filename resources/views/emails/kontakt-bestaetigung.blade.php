<x-mail.branded :tenant="$tenant" title="Vielen Dank für Ihre Anfrage!">
    <p style="margin:0 0 14px;">Hallo {{ $data['name'] }},</p>
    <p style="margin:0 0 14px;">
        vielen Dank für Ihre Nachricht. Wir haben Ihre Anfrage erhalten und melden uns kurzfristig bei Ihnen.
    </p>

    <p style="margin:20px 0 6px; color:#64748b; font-size:13px;">Ihre Nachricht an uns</p>
    <div style="padding:16px; background:#f4f6f8; border-radius:6px; white-space:pre-wrap; font-size:15px; line-height:1.6;">{{ $data['message'] }}</div>

    <p style="margin:22px 0 0;">Bei Rückfragen erreichen Sie uns gerne jederzeit telefonisch oder per E-Mail.</p>
    <p style="margin:18px 0 0;">Freundliche Grüße<br>{{ $tenant->displayName() }}</p>
</x-mail.branded>
