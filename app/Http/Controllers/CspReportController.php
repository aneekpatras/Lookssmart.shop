<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives `report-uri` violation reports from SecurityHeaders' CSP. Browsers POST these with
 * Content-Type `application/csp-report` (older) or `application/reports+json` (Reporting API) — never
 * assume JSON is well-formed or trustworthy input, just log it for visibility.
 */
class CspReportController extends Controller
{
    public function store(Request $request): Response
    {
        // Browsers send these as `application/csp-report` or `application/reports+json`, neither of
        // which Laravel auto-merges into $request->all() — read the raw body directly so real
        // violations are actually visible instead of logging an empty array.
        $report = json_decode($request->getContent(), true) ?? $request->all();

        Log::channel('stack')->warning('CSP violation reported', [
            'report' => $report,
            'content_type' => $request->header('Content-Type'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->noContent();
    }
}
