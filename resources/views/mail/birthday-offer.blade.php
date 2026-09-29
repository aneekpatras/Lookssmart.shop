@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px; font-size:28px; line-height:1.2; color:#1d1d1d;">{{ $title ?? 'Happy Birthday!' }}</h1>
    <p style="margin:0 0 20px; font-size:16px; line-height:1.7; color:#4f4a46;">
        Hi {{ $customer_name ?? 'there' }},
    </p>
    <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#4f4a46;">
        {{ $subtitle ?? 'A little gift from us to celebrate your special day.' }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; background:#faf6f1; border:1px solid #f0e5d7; border-radius:12px; margin-bottom:24px;">
        <tr>
            <td style="padding:20px 18px; font-size:14px; color:#413d38; line-height:1.7; text-align:center;">
                <div style="font-size:36px; line-height:1; margin-bottom:10px;">🎉</div>
                <strong style="color:#1d1d1d; font-size:18px;">Birthday Offer</strong><br>
                Enjoy 10% off your next visit.<br>
                <strong style="color:#1d1d1d;">Code:</strong> {{ $offer_code ?? 'BIRTHDAY10' }}
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
        <tr>
            <td align="center" bgcolor="#c9a66b" style="border-radius:999px;">
                <a href="{{ $cta_url }}" style="display:inline-block; padding:14px 24px; font-size:15px; font-weight:bold; color:#1d1d1d; text-decoration:none;">{{ $cta_label ?? 'Book now' }}</a>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-size:13px; line-height:1.7; color:#6d655f;">
        We’re so grateful to celebrate with you and can’t wait to welcome you back.
    </p>
@endsection
