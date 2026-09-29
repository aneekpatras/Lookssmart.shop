@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px; font-size:28px; line-height:1.2; color:#1d1d1d;">{{ $title ?? 'Your Appointment Is Coming Up' }}</h1>
    <p style="margin:0 0 20px; font-size:16px; line-height:1.7; color:#4f4a46;">
        Hi {{ $customer_name ?? 'there' }},
    </p>
    <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#4f4a46;">
        {{ $subtitle ?? 'A quick reminder from your salon team.' }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; background:#faf6f1; border:1px solid #f0e5d7; border-radius:12px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 18px; font-size:14px; color:#413d38; line-height:1.7;">
                <strong style="color:#1d1d1d;">Booking:</strong> {{ $booking_code }}<br>
                <strong style="color:#1d1d1d;">Service:</strong> {{ $service_name }}<br>
                <strong style="color:#1d1d1d;">Date:</strong> {{ $booking_date }}<br>
                <strong style="color:#1d1d1d;">Time:</strong> {{ $booking_time }}
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
        <tr>
            <td align="center" bgcolor="#c9a66b" style="border-radius:999px;">
                <a href="{{ $cta_url }}" style="display:inline-block; padding:14px 24px; font-size:15px; font-weight:bold; color:#1d1d1d; text-decoration:none;">{{ $cta_label ?? 'View booking' }}</a>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-size:13px; line-height:1.7; color:#6d655f;">
        We’ll see you soon — please arrive 5–10 minutes early if you can.
    </p>
@endsection
