<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #6b7280; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 11px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .total td { font-weight: bold; }
        .signature { margin-top: 64px; width: 260px; margin-left: auto; text-align: center; font-size: 12px; }
        .signature .space { height: 64px; }
        .signature .line { border-top: 1px solid #111827; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Rekap Pengajuan Petty Cash &amp; Reimbursement</h1>
    <p class="muted">{{ $companyName }} · Periode: {{ $from ?: '-' }} s/d {{ $to ?: '-' }}</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nomor</th>
                <th>Tanggal</th>
                <th>Pengaju</th>
                <th>Keperluan</th>
                <th>Kode</th>
                <th>Uraian Anggaran</th>
                <th class="right">Nominal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->request_number }}</td>
                    <td>{{ $item->submitted_at?->format('d-m-Y') }}</td>
                    <td>{{ $item->requester?->name }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->budget_code }}</td>
                    <td>{{ $item->budget_description }}</td>
                    <td class="right">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                    <td>{{ \App\Enums\RequestStatus::from($item->status)->label() }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="7" class="right">Total</td>
                <td class="right">Rp {{ number_format($total, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="signature">
        <p>Mengetahui:</p>
        <p class="space">{{ $signerName }}</p>
        <p class="line">{{ $signerTitle }}</p>
    </div>
</body>
</html>