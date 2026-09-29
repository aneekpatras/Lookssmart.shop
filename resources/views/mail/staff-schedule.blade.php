@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px; font-size:28px; line-height:1.2; color:#1d1d1d;">{{ $title ?? 'Today’s Schedule' }}</h1>
    <p style="margin:0 0 20px; font-size:16px; line-height:1.7; color:#4f4a46;">
        Hi {{ $staff_name ?? 'there' }},
    </p>
    <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#4f4a46;">
        {{ $subtitle ?? 'Your upcoming appointments at a glance.' }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; background:#faf6f1; border:1px solid #f0e5d7; border-radius:12px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 18px; font-size:14px; color:#413d38; line-height:1.7;">
                @if ($booking_count > 0)
                    @foreach ($items as $item)
                        <div style="padding:10px 0; border-bottom:1px solid #efe4d8;">
                            <strong style="color:#1d1d1d;">{{ $item['time'] }}</strong> — {{ $item['customer_name'] }}<br>
                            {{ $item['service_name'] }}
                        </div>
                    @endforeach
                @else
                    <div style="padding:10px 0;">No appointments scheduled today.</div>
                @endif
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
        <tr>
            <td align="center" bgcolor="#c9a66b" style="border-radius:999px;">
                <a href="{{ $cta_url }}" style="display:inline-block; padding:14px 24px; font-size:15px; font-weight:bold; color:#1d1d1d; text-decoration:none;">{{ $cta_label ?? 'Open schedule' }}</a>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-size:13px; line-height:1.7; color:#6d655f;">
        You have {{ $booking_count }} appointment(s) today.
    </p>
@endsection
