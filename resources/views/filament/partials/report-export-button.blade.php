@php
    $params = [];
    if (! empty($dateFrom ?? null)) {
        $params['from'] = $dateFrom;
    }
    if (! empty($dateTo ?? null)) {
        $params['to'] = $dateTo;
    }
@endphp
<a href="{{ route($routeName, $params) }}"
    style="display:inline-flex; align-items:center; gap:6px; padding:10px 16px; background:#1a1a2e; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; text-decoration:none;">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Download Excel
</a>
