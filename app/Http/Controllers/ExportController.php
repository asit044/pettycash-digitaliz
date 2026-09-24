<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rekap-petty-cash-'.now()->format('Ymd-His').'.csv"',
        ];

        $callback = function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Nomor', 'Tanggal', 'Pengaju', 'Keperluan', 'Kode Anggaran', 'Uraian Anggaran',
                'Nominal', 'Status', 'Folder Drive',
            ]);

            foreach ($rows as $item) {
                fputcsv($out, [
                    $item->request_number,
                    $item->submitted_at?->format('d-m-Y'),
                    $item->requester?->name,
                    $item->description,
                    $item->budget_code,
                    $item->budget_description,
                    (float) $item->nominal,
                    RequestStatus::from($item->status)->label(),
                    $item->drive_folder_url,
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function pdf(Request $request): Response
    {
        $rows = $this->query($request)->get();

        $total = (float) $rows->sum('nominal');

        $pdf = Pdf::loadView('exports.rekap', [
            'rows' => $rows,
            'total' => $total,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'signerName' => Setting::get('signer_name', ''),
            'signerTitle' => Setting::get('signer_title', ''),
            'companyName' => Setting::get('company_name', ''),
        ]);

        return $pdf->download('rekap-petty-cash-'.now()->format('Ymd-His').'.pdf');
    }

    private function query(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');

        return PettyCashRequest::query()
            ->with('requester')
            ->when($from, fn ($q) => $q->where('submitted_at', '>=', $from.' 00:00:00'))
            ->when($to, fn ($q) => $q->where('submitted_at', '<=', $to.' 23:59:59'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('budget_code'), fn ($q) => $q->where('budget_code', $request->input('budget_code')))
            ->latest('submitted_at');
    }
}
