# Panduan Penggunaan — Sistem Petty Cash & Reimbursement Internal Digitaliz

Dokumen ini memandu setiap peran dalam menggunakan portal internal pengajuan petty cash & reimbursement:
mulai dari login, membuat pengajuan, validasi oleh Admin, pencairan oleh Finance, pelacakan status,
hingga ekspor laporan dan pengaturan sistem.

---

## Daftar Isi

1. [Ringkasan Sistem](#1-ringkasan-sistem)
2. [Cara Login](#2-cara-login)
3. [Panduan Pengaju (Karyawan/Tim)](#3-panduan-pengaju)
4. [Panduan Admin](#4-panduan-admin)
5. [Panduan Finance](#5-panduan-finance)
6. [Panduan Head of Digitaliz](#6-panduan-head-of-digitaliz)
7. [Ekspor Laporan (CSV & PDF)](#7-ekspor-laporan-csv--pdf)
8. [Pengaturan (Khusus Admin)](#8-pengaturan-khusus-admin)
9. [Demo / Test Case](#9-demo--test-case)
10. [FAQ / Troubleshooting](#10-faq--troubleshooting)

---

## 1. Ringkasan Sistem

Portal internal Digitaliz menyatukan seluruh alur pengajuan kas kecil (petty cash) dan reimbursement
ke satu tempat — menggantikan WhatsApp/Notion/Drive manual.

### Alur status

```
Menunggu Validasi Admin  → (Admin: Minta Revisi) → Perlu Revisi → diajukan ulang → Menunggu Validasi Admin
                        → (Admin: Tolak)          → Ditolak
                        → (Admin: Approve)        → Diproses Finance → (Finance + bukti cair) → Selesai
```

### Hak akses per peran

| Peran | Boleh | Tidak Boleh |
| --- | --- | --- |
| **Pengaju** | Membuat pengajuan, mengunggah lampiran, melihat status & riwayat miliknya | Melihat/mengisi kode anggaran; melihat pengajuan orang lain |
| **Admin** | Melihat semua pengajuan, mengisi kode & uraian anggaran, Approve/Revisi/Tolak, ekspor laporan | Menandai pengajuan sebagai "sudah dicairkan" |
| **Finance** | Memproses pencairan, mengunggah bukti transfer resmi, menandai selesai, ekspor laporan | Mengubah kode anggaran, menyetujui pengajuan |
| **Head of Digitaliz** | Melihat laporan & ringkasan | — |

### Aturan utama sistem

- Pengajuan **tidak dapat di-approve sebelum kode anggaran terisi** (diisi Admin).
- Status **tidak dapat menjadi "Selesai" tanpa bukti transfer resmi** yang diunggah Finance.
- Setiap tindakan Admin tercatat: **siapa, kapan, apa** (audit trail di linimasa status).
- Tim Pengaju **tidak pernah melihat field kode anggaran**.

---

## 2. Cara Login

1. Buka aplikasi di browser, lalu klik tombol **Masuk**.
2. Isi email dan password, lalu klik **Log in**.
3. Setelah masuk, halaman **Dashboard** menampilkan menu sesuai peran Anda (lihat bilah atas).

### Akun demo

> Semua akun demo menggunakan password: **`password`**

| Peran | Email |
| --- | --- |
| Admin | `admin@digitaliz.id` |
| Finance | `finance@digitaliz.id` |
| Head of Digitaliz | `hengki@digitaliz.id` |
| Pengaju | `apredovic@example.com` berserta 4 akun pengaju lainnya (random, lihat hasil `database/seeders/DatabaseSeeder.php`) |

> Akun demo diisi saat `php artisan migrate:fresh --seed`. Kredensial pengaju bersifat acak;
> Anda bisa melihat daftar lengkap lewat `php artisan tinker --execute "echo App\Models\User::pluck('email')->implode(PHP_EOL);"`.

---

## 3. Panduan Pengaju

### 3.1 Membuat pengajuan

1. Klik menu **Pengajuan Saya** → tombol **+ Ajukan Baru**.
2. Isi formulir:

   | Field | Wajib? | Keterangan / contoh |
   | --- | --- | --- |
   | Keperluan | ✅ | Keterangan yang dibayarkan. Contoh: *"Beli pulsa meeting tim"* |
   | Nominal (Rp) | ✅ | Angka tanpa titik/koma. Contoh: `250000` |
   | Invoice / Struk | ⬜ | File PDF/JPG/PNG maks. 10 MB |
   | Bukti Transfer Awal | ⬜ | File PDF/JPG/PNG maks. 10 MB |

3. Klik **Kirim Pengajuan**.

> ⚠️ Tim **tidak perlu** memilih kategori/kode anggaran — sistem memperlihatkan pengingat
> "Kode anggaran ditentukan oleh Admin". Kode anggaran dipasang oleh Admin pada tahap validasi.

### 3.2 Memantau status

1. Buka menu **Pengajuan Saya** → tombol **Detail →** pada nomor pengajuan (mis. `KC-2026-0001`).
2. Lihat **Linimasa Status**: setiap perubahan terekam (siapa, kapan, keterangan).

### 3.3 Kirim ulang saat diminta revisi

1. Jika status **Perlu Revisi**, buka detail pengajuan.
2. Baca alasan revisi di linimasa.
3. Perbaiki **Keperluan** pada form *Ajukan Ulang*, lalu klik **Ajukan Ulang**.
   Status kembali menjadi *Menunggu Validasi Admin*.

### 3.4 Notifikasi

- Anda menerima notifikasi WhatsApp otomatis saat: pengajuan **diminta revisi / ditolak** (beserta alasan)
  dan saat **pencairan selesai**.
- Pastikan kolom nomor HP (`phone`) pada profil Anda terisi agar notifikasi terkirim.

---

## 4. Panduan Admin

### 4.1 Melihat antrean pengajuan

1. Buka menu **Validasi Pengajuan**.
2. Gunakan **kotak pencarian** (nomor / nama pengaju / keperluan) dan **filter status** untuk menyaring daftar.
3. Prioritas: pengajuan berstatus **Menunggu Validasi Admin**.

### 4.2 Validasi & kode anggaran

1. Klik **Review →** pada pengajuan yang akan dilihat.
2. Isi **Kode Anggaran** (pilih dari daftar di **Pengaturan**) dan **Uraian Anggaran**.
3. Pilih salah satu tindakan:

| Tindakan | Syarat | Efek |
| --- | --- | --- |
| **Approve** | Kode & uraian anggaran terisi | Status → *Diproses Finance*, Finance dinotifikasi WhatsApp |
| **Minta Revisi** | Wajib isi **Alasan** | Status → *Perlu Revisi*, pengaju dinotifikasi (dengan alasan) |
| **Tolak** | Wajib isi **Alasan** | Status → *Ditolak*, pengaju dinotifikasi (dengan alasan) |

> ⚠️ Tombol **Approve** ditolak sistem bila kode anggaran belum diisi — hal ini disengaja
> (aturan: *"pengajuan tidak boleh di-approve sebelum kode anggaran terisi"*).

### 4.3 Audit trail

Setiap tindakan Anda terekam otomatis di **Linimasa Status** detail pengajuan (siapa=Admin, kapan, apa).

---

## 5. Panduan Finance

### 5.1 Proses pencairan

1. Buka menu **Pencairan** → daftar pengajuan **Diproses Finance** (yang sudah di-approve Admin).
2. Klik **Proses →** pada pengajuan.
3. Lakukan transfer dana ke pengaju melalui mekanisme di luar sistem (bank / e-wallet).

### 5.2 Menyelesaikan pengajuan

1. Kembali ke halaman detail → panel **Proses Pencairan & Selesaikan**.
2. Unggah **Bukti Transfer Resmi** (PDF/JPG/PNG maks. 10 MB).
3. Klik **Tandai Selesai**.

> ⚠️ Status **tidak dapat** menjadi *Selesai* tanpa bukti transfer resmi terunggah
> (aturan: *"status tidak bisa menjadi 'Selesai' tanpa bukti transfer resmi terunggah"*).

### 5.3 Setelah selesai

- Pengaju dinotifikasi WhatsApp otomatis bahwa pengajuannya sudah cair.
- Data otomatis masuk arsip dan siap diekspor (CSV/PDF).

---

## 6. Panduan Head of Digitaliz

1. Buka menu **Laporan**.
2. Atur **Dari Tanggal**, **Sampai Tanggal**, dan **Filter Status**.
3. Lihat kartu ringkasan (jumlah pengajuan, total nominal, nominal sudah selesai) dan tabel detail.

> ℹ️ Head bersifat **read-only**: tidak bisa menyetujui, tidak bisa ekspor. Ekspor milik Admin/Finance.
> Tanda tangan pada PDF otomatis diambil dari **Pengaturan** (*Mengetahui: {nama} {jabatan}*).

---

## 7. Ekspor Laporan (CSV & PDF)

Khusus **Admin** dan **Finance**:

1. Buka menu **Laporan**.
2. Atur **periode** (dari/sampai tanggal) dan **filter status** (opsional).
3. Pilih:
   - **⬇ Ekspor CSV** → rekap dalam format spreadsheet untuk olah lanjut.
   - **⬇ Ekspor PDF** → laporan formal siap cetak / tanda tangan.
4. PDF otomatis menyertakan blok tanda tangan:
   ```
   Mengetahui:
   {Nama Penandatangan}
   {Jabatan Penandatangan}
   ```
   Nilai nama/jabatan diambil dari **Pengaturan** (dapat diubah, bukan tertanam di kode).

---

## 8. Pengaturan (Khusus Admin)

Buka menu **Pengaturan**.

### 8.1 Laporan & Penandatangan

- **Nama Penandatangan** → nama yang tampil pada blok tanda tangan PDF (default: `Hengki`).
- **Jabatan Penandatangan** → jabatan penandatangan (default: `Head of Digitaliz`).
- **Nama Perusahaan** → dipakai untuk nama folder root Google Drive.
- Klik **Simpan Pengaturan** setelah mengubah.

### 8.2 Kode Anggaran

- **Tambah**: isi *Kode* (mis. `MKT-002`) dan *Uraian* (mis. `Marketing Event`), klik **Tambah**.
- **Aktifkan / Nonaktifkan**: kode nonaktif tetap ada tapi tidak bisa dipilih Admin saat validasi.
- **Hapus**: menghapus kode dari daftar.

> Kode anggaran yang tampil di panel validasi Admin diambil dari daftar ini.

---

## 9. Demo / Test Case

Skenario uji manual untuk memverifikasi seluruh alur (QA). Jalankan sesuai peran.

### Test Case 1 — Alur lengkap absurd: Submit → Approve → Paid → Done

1. Login **Pengaju** → **+ Ajukan Baru** → isi keperluan `Beli pulsa meeting` dan nominal `250000` → **Kirim Pengajuan**.
   - *Verifikasi*: muncul `KC-2026-XXXX`, status **Menunggu Validasi Admin**, linimasa berisi "Pengajuan dibuat".
2. Login **Admin** → **Validasi Pengajuan** → **Review** pada pengajuan tersebut.
   - Coba klik **Approve** tanpa kode anggaran → *Verifikasi*: ditolak (error kode anggaran wajib).
   - Isi **Kode Anggaran** `OPR-001` + **Uraian** `Operasional & Umum` → **Approve**.
   - *Verifikasi*: status **Diproses Finance**, linimasa "Disetujui Admin", There WA log `wa_logs` event `approved` → ke role `finance`.
3. Login **Finance** → **Pencairan** → **Proses** → coba **Tandai Selesai** tanpa bukti →
   - *Verifikasi*: ditolak (bukti wajib).
   - Unggah **Bukti Transfer Resmi** → **Tandai Selesai**.
   - *Verifikasi*: status **Selesai**, file bertipe `official_receipt` tersimpan, wa_logs event `completed` → pengaju.
4. Login **Pengaju** → buka detail → *Verifikasi*: status **Selesai**, bukti transfer bisa diunduh, nomor HP = OK.

### Test Case 2 — Alur revisi

1. Login **Pengaju** → buat pengajuan `Transport meeting klien` `150000`.
2. Login **Admin** → **Review** → klik **Minta Revisi** dengan alasan *"Lampiran invoice kurang jelas"*.
   - *Verifikasi*: status **Perlu Revisi**, linimasa berisi alasan, wa_logs event `needs_revision` → pengaju.
3. Login **Pengaju** → **Detail** → baca alasan → perbaiki keperluan → **Ajukan Ulang**.
   - *Verifikasi*: status kembali **Menunggu Validasi Admin**.

### Test Case 3 — Alur tolak

1. Login **Pengaju** → buat pengajuan `Reimburse makan siang` `100000`.
2. Login **Admin** → **Review** → **Tolak** dengan alasan *"Di luar cakupan petty cash"*.
   - *Verifikasi*: status **Ditolak**, linimasa berisi alasan, wa_logs event `rejected` → pengaju.
3. Login **Pengaju** → detail → *Verifikasi*: tidak ada form "Ajukan Ulang" (pengajuan tertolak final).

### Test Case 4 — Keamanan akses / segregation of duty

| Aksi | Pengaju | Admin | Finance | Head |
| --- | --- | --- | --- | --- |
| Membuat pengajuan | ✅ | ❌ | ❌ | ❌ |
| Melihat pengajuan orang lain | ❌ (403) | ✅ | ✅ | ✅ |
| Mengisi kode anggaran | ❌ | ✅ | ❌ | ❌ |
| Approve / Revisi / Tolak | ❌ | ✅ | ❌ | ❌ |
| Menandai selesai | ❌ | ❌ | ✅ | ❌ |
| Ekspor CSV/PDF | ❌ | ✅ | ✅ | ❌ |
| Melihat laporan & ringkasan | ❌ | ✅ | ✅ | ✅ |
| Akses menu Pengaturan | ❌ | ✅ | ❌ | ❌ |

### Test Case 5 — Ekspor

1. Evaluasi *minimal* 1 pengajuan selesai (lihat Test Case 1).
2. Login **Admin** → **Laporan** → atur periode yang mencakup data → **⬇ Ekspor CSV**.
   - *Verifikasi*: file `.csv` terunduh, berisi baris-baris pengajuan (dengan kode anggaran & nominal).
3. Klik **⬇ Ekspor PDF**.
   - *Verifikasi*: file `.pdf` terunduh, memuat tabel rekap + blok tanda tangan *Mengetahui: {nama} {jabatan}*.

### Test Case 6 — Pengaturan penandatangan (configurable)

1. Login **Admin** → **Pengaturan** → ubah **Nama Penandatangan** menjadi `A. Test` & **Jabatan** menjadi `Finance Manager` → **Simpan**.
2. **⬇ Ekspor PDF** lagi → *Verifikasi*: blok tanda tangan kini bertuliskan `A. Test` / `Finance Manager`.
3. (Opsional) kembalikan nilainya ke semula.

### Test Case 7 — Notifikasi WhatsApp

> Menyala bila `FONNTE_TOKEN` terisi di `.env` dan nomor HP user terisi. Tanpa token, sistem
> mencatat log `failed`/`skipped` di tabel `wa_logs` dan tidak melempar error.

1. Pastikan nomor pengaju/admin/finance valid.
2. Ulangi alur Test Case 1 dan cek tabel `wa_logs`:
   - `submitted` → ke Admin.
   - `approved` → ke Finance.
   - `needs_revision` / `rejected` (+ alasan) → ke Pengaju.
   - `completed` → ke Pengaju.

---

## 10. FAQ / Troubleshooting

### Tombol **Approve** tidak berfungsi / Kode berlaku wajib
Pastikan **Kode Anggaran** dan **Uraian Anggaran** sudah terisi di panel validasi. Sistem memang
memaksakan aturan: *approve wajib kode anggaran terisi*.

### Status tidak berubah setelah tindakan
Refresh halaman (F5) atau buka kembali menu terkait. Semua perubahan tersimpan di **Linimasa Status**
detail pengajuan.

### Notifikasi WhatsApp tidak terkirim
- Pastikan `FONNTE_TOKEN` sudah diisi di `.env` dan aplikasi di-restart (`php artisan config:clear`).
- Pastikan nomor HP user (registrasi `phone`) valid dan berformat `628xxxxxxxxxx` (tanpa `0` di depan).
- Tanpa token, sistem mencatat log `failed` di tabel `wa_logs` — integrasi tetap *tidak* mengganggu alur.

### Folder / berkas Google Drive tidak muncul
- Integrasi Drive menyala jika `GOOGLE_SERVICE_ACCOUNT_JSON` (path file JSON) dan akun Service Account
  diberi akses ke folder root diisi.
- Tanpa konfigurasi, berkas tetap tersimpan lokal dan tautan Drive tidak tampil (tidak error).

### Nomor pengajuan yang muncul aneh / restart sequence
Format `KC-YYYY-NNNN`. Penomoran berjalan per tahun berdasarkan nomor terakhir yang ada di database.
Jika ingin reset di tahun berjalan, perlu diatur ulang basis data (bukan bagian dari alur normal).

### Pengajuan "Ditolak" tidak bisa diajukan ulang
Benar — *Ditolak* adalah status final. **Perlu Revisi** adalah satu-satunya jalur untuk perbaikan
(dengan alasan dari Admin).

### Siapa yang bisa mengekspor laporan?
**Admin** dan **Finance**. Head hanya melihat ringkasan read-only.

### Siapa yang menandai "sudah dicairkan"?
**Finance** — dan hanya setelah mengunggah **bukti transfer resmi**. Admin tidak memiliki hak tersebut.

---

© Digitaliz — Sistem Petty Cash & Reimbursement Internal.