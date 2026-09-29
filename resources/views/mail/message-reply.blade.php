@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px; font-size:28px; line-height:1.2; color:#1d1d1d;">{{ $title ?? 'We replied to your message' }}</h1>
    <p style="margin:0 0 20px; font-size:16px; line-height:1.7; color:#4f4a46;">
        Hi {{ $customer_name ?? 'there' }},
    </p>
    <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#4f4a46; white-space:pre-line;">{{ $reply_body }}</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; background:#faf6f1; border:1px solid #f0e5d7; border-radius:12px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 18px; font-size:13px; color:#7a7369; line-height:1.7;">
                <strong style="color:#1d1d1d;">Your original message:</strong><br>
                <span style="white-space:pre-line;">{{ $original_body }}</span>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-size:14px; line-height:1.7; color:#7a7369;">
        — {{ $replied_by }}, {{ config('app.name') }}
    </p>
@endsection
