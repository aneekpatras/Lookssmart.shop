@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px; font-size:28px; line-height:1.2; color:#1d1d1d;">New Booking Received</h1>
    <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#4f4a46;">
        A new booking just came in through {{ $source }}.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; background:#faf6f1; border:1px solid #f0e5d7; border-radius:12px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 18px; font-size:14px; color:#413d38; line-height:1.7;">
                <strong style="color:#1d1d1d;">Booking:</strong> {{ $booking_code }}<br>
                <strong style="color:#1d1d1d;">Customer:</strong> {{ $customer_name }}<br>
                <strong style="color:#1d1d1d;">Service(s):</strong> {{ $service_name }}<br>
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

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
        <tr>
            <td align="center" bgcolor="#c9a66b" style="border-radius:999px;">
                <a href="{{ $cta_url }}" style="display:inline-block; padding:14px 24px; font-size:15px; font-weight:bold; color:#1d1d1d; text-decoration:none;">View in admin dashboard</a>
            </td>
        </tr>
    </table>
@endsection
