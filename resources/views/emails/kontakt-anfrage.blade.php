@php $primary = $tenant->resolvedPrimaryColor(); @endphp
<x-mail.branded :tenant="$tenant" title="Neue Kontaktanfrage">
    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; font-size:15px; line-height:1.5;">
        <tr><td style="padding:5px 0; width:120px; color:#64748b; vertical-align:top;">Name</td><td style="padding:5px 0;">{{ $data['name'] }}</td></tr>
        <tr><td style="padding:5px 0; color:#64748b; vertical-align:top;">E-Mail</td><td style="padding:5px 0;"><a href="mailto:{{ $data['email'] }}" style="color:{{ $primary }}; text-decoration:none;">{{ $data['email'] }}</a></td></tr>
        @if (filled($data['phone']))
            <tr><td style="padding:5px 0; color:#64748b; vertical-align:top;">Telefon</td><td style="padding:5px 0;">{{ $data['phone'] }}</td></tr>
        @endif
        <tr><td style="padding:5px 0; color:#64748b; vertical-align:top;">Eingegangen</td><td style="padding:5px 0;">{{ $data['submitted_at'] }}</td></tr>
        @if (filled($data['source_url'] ?? null))
            <tr><td style="padding:5px 0; color:#64748b; vertical-align:top;">Gesendet von</td><td style="padding:5px 0;"><a href="{{ $data['source_url'] }}" style="color:{{ $primary }}; text-decoration:none;">{{ $data['source_url'] }}</a></td></tr>
        @endif
    </table>

    <p style="margin:22px 0 6px; color:#64748b; font-size:13px;">Nachricht</p>
    <div style="padding:16px; background:#f4f6f8; border-radius:6px; white-space:pre-wrap; font-size:15px; line-height:1.6;">{{ $data['message'] }}</div>

    <p style="margin:24px 0 0;">
        <a href="mailto:{{ $data['email'] }}" style="display:inline-block; padding:12px 24px; background:{{ $primary }}; color:#ffffff; text-decoration:none; border-radius:6px; font-weight:bold; font-size:14px;">Antworten</a>
    </p>
</x-mail.branded>
