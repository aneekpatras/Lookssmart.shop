@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px; font-size:28px; line-height:1.2; color:#1d1d1d;">{{ $title ?? 'Booking Confirmed' }}</h1>
    <p style="margin:0 0 20px; font-size:16px; line-height:1.7; color:#4f4a46;">
        Hi {{ $customer_name ?? 'there' }},
    </p>
    <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#4f4a46;">
        {{ $subtitle ?? 'Your appointment is locked in and ready to go.' }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; background:#faf6f1; border:1px solid #f0e5d7; border-radius:12px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 18px; font-size:14px; color:#413d38; line-height:1.7;">
                <strong style="color:#1d1d1d;">Booking:</strong> {{ $booking_code }}<br>
                <strong style="color:#1d1d1d;">Service:</strong> {{ $service_name }}<br>
                <strong style="color:#1d1d1d;">Date:</strong> {{ $booking_date }}<br>
                <strong style="color:#1d1d1d;">Time:</strong> {{ $booking_time }}<br>
                <strong style="color:#1d1d1d;">Duration:</strong> {{ $total_duration }}<br>
                <strong style="color:#1d1d1d;">Total:</strong> {{ $total }}
            </td>
        </tr>
    </table>

    @if (!empty($notes))
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; background:#fbf8f3; border:1px solid #f0e5d7; border-radius:12px; margin-bottom:24px;">
            <tr>
                <td style="padding:16px 18px; font-size:14px; color:#413d38; line-height:1.7;">
                    <strong style="color:#1d1d1d; display:block; margin-bottom:4px;">Special Notes / Instructions</strong>
                    {!! nl2br(e($notes)) !!}
                </td>
            </tr>
        </table>
    @endif

    <p style="margin:0 0 24px; font-size:14px; line-height:1.7; color:#4f4a46;">
        <a href="{{ $google_calendar_url }}" style="color:#8a6a34;">Add to Google Calendar</a>
        &nbsp;|&nbsp;
        <a href="{{ $ics_url }}" style="color:#8a6a34;">Download calendar file</a>
    </p>

    <p style="margin:0 0 20px; font-size:15px; line-height:1.7; color:#4f4a46;">
        A calendar invitation is attached, and you can manage your booking anytime using the link below.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
        <tr>
            <td align="center" bgcolor="#c9a66b" style="border-radius:999px;">
                <a href="{{ $cta_url }}" style="display:inline-block; padding:14px 24px; font-size:15px; font-weight:bold; color:#1d1d1d; text-decoration:none;">{{ $cta_label ?? 'Manage this booking' }}</a>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-size:13px; line-height:1.7; color:#6d655f;">
        No account or password needed — this link is scoped to this booking only.
    </p>
@endsection
