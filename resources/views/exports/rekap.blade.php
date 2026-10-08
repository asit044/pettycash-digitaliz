<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
        .kop { border-bottom: 2px solid #1e36af; padding-bottom: 10px; margin-bottom: 14px; }
        .kop table { width: 100%; border: 0; margin: 0; }
        .kop td { border: 0; padding: 0; vertical-align: bottom; }
        .company { font-size: 10px; font-weight: bold; color: #1e36af; letter-spacing: 1px; text-transform: uppercase; }
        h1 { font-size: 17px; margin: 2px 0 0; }
        .muted { color: #64748b; }
        .meta td { font-size: 9.5px; text-align: right; color: #475569; line-height: 1.5; }
        .filters { background: #f1f5f9; border-radius: 4px; padding: 7px 10px; margin-bottom: 12px; font-size: 9.5px; color: #334155; }
        .filters b { color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; vertical-align: top; }
        table.data th { background: #1e36af; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: .3px; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right !important; }
        .nowrap { white-space: nowrap; }
        .total td { font-weight: bold; background: #e2e8f0 !important; }
        h2 { font-size: 11px; margin: 18px 0 6px; }
        .bottom { width: 100%; margin-top: 18px; }
        .bottom td { vertical-align: top; border: 0; padding: 0; }
        .signature { width: 230px; text-align: center; font-size: 10.5px; }
        .signature .space { height: 62px; }
        .signature .name { font-weight: bold; text-decoration: underline; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="kop">
        <table>
            <tr>
                <td>
                    <div class="company">{{ $companyName }}</div>
                    <h1>Rekap Pengajuan Petty Cash &amp; Reimbursement</h1>
                    <div class="muted">Periode: {{ $from ? \Illuminate\Support\Carbon::parse($from)->translatedFormat('d F Y') : '-' }} s/d {{ $to ? \Illuminate\Support\Carbon::parse($to)->translatedFormat('d F Y') : '-' }}</div>
                </td>
                <td class="meta">
                    <table><tr><td>
                        Dicetak: {{ now()->format('d-m-Y H:i') }}<br>
                        Oleh: {{ $printedBy ?? '-' }}<br>
                        Jumlah data: {{ $rows->count() }}
                    </td></tr></table>
                </td>
            </tr>
        </table>
    </div>

    <div class="filters">
        Filter — Status: <b>{{ $statusLabel ?? 'Semua status' }}</b> &nbsp;·&nbsp; Kode anggaran: <b>{{ $budgetFilter ?? 'Semua kode' }}</b>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 22px">No</th>
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
            @if ($rows->isEmpty())
                <tr>
                    <td colspan="9">Tidak ada data pada periode/filter ini.</td>
                </tr>
            @endif
            @foreach ($rows as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="nowrap">{{ $item->request_number }}</td>
                    <td class="nowrap">{{ $item->submitted_at?->format('d-m-Y') }}</td>
                    <td>{{ $item->requester?->name }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="nowrap">{{ $item->budget_code }}</td>
                    <td>{{ $item->budget_description }}</td>
                    <td class="right nowrap">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                    <td class="nowrap">{{ \App\Enums\RequestStatus::from($item->status)->label() }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="7" class="right">Total</td>
                <td class="right nowrap">Rp {{ number_format($total, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <table class="bottom">
        <tr>
            <td>
                @if (isset($byBudget) && $byBudget->isNotEmpty())
                    <h2>Ringkasan per Kode Anggaran</h2>
                    <table class="data" style="width: 70%">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Uraian</th>
                                <th class="right">Jumlah</th>
                                <th class="right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byBudget as $budget)
                                <tr>
                                    <td class="nowrap">{{ $budget['code'] }}</td>
                                    <td>{{ $budget['description'] }}</td>
                                    <td class="right">{{ $budget['count'] }}</td>
                                    <td class="right nowrap">Rp {{ number_format($budget['nominal'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
            <td style="width: 240px">
                <div class="signature">
                    <p>{{ now()->translatedFormat('d F Y') }}</p>
                    <p>Mengetahui:</p>
                    <p class="space"></p>
                    <p class="name">{{ $signerName }}</p>
                    <p>{{ $signerTitle }}</p>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">Dokumen ini dihasilkan otomatis oleh Sistem Petty Cash {{ $companyName }}.</div>
</body>
</html>
