<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ config('app.name') }}</title>
    <style>
        body, table, td, p {
            font-family: Arial, Helvetica, sans-serif;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        @media only screen and (max-width: 620px) {
            .wrapper {
                width: 100% !important;
            }

            .content {
                width: 100% !important;
            }

            .inner {
                width: 100% !important;
            }

            .stack {
                display: block !important;
                width: 100% !important;
            }

            .button {
                display: block !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f5f1ea; color:#1c1c1c;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f1ea; margin:0; padding:0; width:100%;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="wrapper" style="max-width:620px; background:#ffffff; border:1px solid #ece1d6; border-radius:18px; overflow:hidden;">
                    <tr>
                        <td style="background:#1d1d1d; padding:24px 32px; text-align:center;">
                            <div style="font-size:12px; letter-spacing:2px; color:#d8c1a0; text-transform:uppercase; font-weight:bold;">
                                Looks Smart Beauty Salon
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="inner" style="padding:32px; background:#ffffff;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#faf6f1; padding:24px 32px; border-top:1px solid #f0e5d7; color:#5f5348; font-size:12px; line-height:1.6; text-align:center;">
                            Looks Smart Beauty Salon · 123 Beauty Lane · hello@lookssmart.com
                            @isset($unsubscribe_url)
                                <br><a href="{{ $unsubscribe_url }}" style="color:#8a6a34;">Unsubscribe from this channel</a>
                            @endisset
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
