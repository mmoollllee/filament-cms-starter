@props(['tenant', 'title' => null])
@php
    $primary = $tenant->resolvedPrimaryColor();
    $name = $tenant->displayName();
    // SVG logos render unreliably in email clients, so only use raster logos as an
    // image; otherwise fall back to the brand name set in the tenant's color.
    $logo = $tenant->resolvedMainLogoUrl();
    $useLogo = $logo && ! str_ends_with(strtolower((string) $logo), '.svg');
    $logoUrl = $useLogo ? (str_starts_with((string) $logo, 'http') ? $logo : url($logo)) : null;

    $street = trim((string) $tenant->resolvedSiteSetting('street'));
    $zip = trim((string) $tenant->resolvedSiteSetting('postal_code'));
    $city = trim((string) $tenant->resolvedSiteSetting('city'));
    $phone = trim((string) $tenant->resolvedSiteSetting('contact_phone'));
    $email = trim((string) $tenant->resolvedSiteSetting('contact_email'));
    $siteUrl = $tenant->primary_domain ? 'https://'.$tenant->primary_domain : url('/');
    $addressLine = trim(trim($street).', '.trim($zip.' '.$city), ', ');
@endphp
<!DOCTYPE html>
<html lang="de" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $title ?? $name }}</title>
</head>
<body style="margin:0; padding:0; background:#eef1f3; -webkit-font-smoothing:antialiased; font-family:Arial, Helvetica, sans-serif; color:#1f2933;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f3;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
                    <tr><td style="height:5px; line-height:5px; font-size:0; background:{{ $primary }};">&nbsp;</td></tr>
                    <tr>
                        <td align="center" style="padding:26px 32px 14px;">
                            <a href="{{ $siteUrl }}" style="text-decoration:none;">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="{{ $name }}" height="46" style="display:inline-block; max-height:46px; border:0;">
                                @else
                                    <span style="font-size:22px; font-weight:bold; letter-spacing:.2px; color:{{ $primary }};">{{ $name }}</span>
                                @endif
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 32px 30px; font-size:15px; line-height:1.6; color:#1f2933;">
                            @if (filled($title))
                                <h1 style="margin:0 0 18px; font-size:21px; line-height:1.25; color:#0f172a;">{{ $title }}</h1>
                            @endif
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px; background:#f4f6f8; border-top:1px solid #e8edf1; font-size:12px; line-height:1.7; color:#64748b;">
                            <strong style="color:#334155;">{{ $name }}</strong><br>
                            @if (filled($addressLine)){{ $addressLine }}<br>@endif
                            @if (filled($phone))Tel. {{ $phone }}@endif@if (filled($phone) && filled($email)) &middot; @endif@if (filled($email))<a href="mailto:{{ $email }}" style="color:{{ $primary }}; text-decoration:none;">{{ $email }}</a>@endif
                            <br><span style="color:#94a3b8;">© {{ now()->year }} {{ $name }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
