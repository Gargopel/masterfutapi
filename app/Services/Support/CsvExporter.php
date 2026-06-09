<?php

namespace App\Services\Support;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    public function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value), $row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
