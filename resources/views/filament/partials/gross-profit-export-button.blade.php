@include('filament.partials.report-export-button', [
    'routeName' => 'reports.gross-profit.export',
    'dateFrom' => $dateFrom ?? null,
    'dateTo' => $dateTo ?? null,
])
