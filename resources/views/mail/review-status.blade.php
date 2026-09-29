@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px; font-size:28px; line-height:1.2; color:#1d1d1d;">{{ $title }}</h1>
    <p style="margin:0 0 20px; font-size:16px; line-height:1.7; color:#4f4a46;">
        Hi {{ $customer_name }},
    </p>
    <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#4f4a46;">
        @if ($is_reply)
            The salon team left a public reply on your review:
        @else
            Thank you for sharing your feedback — your {{ $rating }}-star review is now live on our site.
        @endif
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; background:#faf6f1; border:1px solid #f0e5d7; border-radius:12px; margin-bottom:16px;">
        <tr>
            <td style="padding:20px 18px; font-size:14px; color:#413d38; line-height:1.7;">
                <strong style="color:#1d1d1d;">Your review ({{ $rating }}/5):</strong><br>
                <span style="white-space:pre-line;">{{ $review_body }}</span>
            </td>
        </tr>
    </table>

    @if ($is_reply && $admin_reply)
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; background:#fff; border:1px solid #ece1d6; border-radius:12px; margin-bottom:24px;">
            <tr>
                <td style="padding:20px 18px; font-size:14px; color:#413d38; line-height:1.7;">
                    <strong style="color:#1d1d1d;">Our reply:</strong><br>
                    <span style="white-space:pre-line;">{{ $admin_reply }}</span>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin:0; font-size:14px; line-height:1.7; color:#7a7369;">
        Thank you for being a valued client.
    </p>
@endsection
