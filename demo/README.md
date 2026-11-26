# Coordina — Interactive Demo (Frontend-Only)

Versi demo interaktif mandiri (*frontend-only*) dari **Coordina SMKN 4 Padalarang** berbasis **Nuxt 4** dan **Design System V2**. Demo ini berjalan sepenuhnya di browser memakai mock data lokal (`Pinia` + `localStorage`) tanpa backend Laravel, API eksternal, atau basis data.

## Fitur Utama Demo

- **Pengalih Peran Presentasi:** Berpindah langsung antara **Pengurus MPK** dan **Pengurus Ekskul** (dapat memilih akun organisasi ekskul tertentu, termasuk contoh profil lengkap dan belum lengkap).
- **Simulasi 5 Menu Utama:**
  1. `Tugas & Persetujuan` — Master-Detail responsif (berdampingan di desktop/tablet, daftar & detail bergantian di mobile) lengkap dengan panduan **"Apa yang Harus Dilakukan Sekarang?"**.
  2. `Jadwal Fasilitas` — Tampilan slot rutin, pemakaian sementara, slot kosong, manajemen jadwal/fasilitas (khusus MPK), dan tombol **Pinjam Sekarang** ke formulir resmi.
  3. `Ajukan Peminjaman` — Formulir pengajuan dengan deteksi bentrok jadwal otomatis (*real-time*) sebelum dikirim.
  4. `Arsip Surat Resmi` — Daftar surat ber-nomor resmi lengkap dengan modal pratinjau surat dan tombol cetak (*Print*).
  5. `Profil & Tanda Tangan` — Checklist 3 unsur kelengkapan tanda tangan, edit identitas, contoh preset/upload logo & TTD, serta daftar akun organisasi (khusus MPK).
- **Akurasi Alur Bisnis:**
  - **Workflow A (Antar-Ekskul):** Pengajuan + TTD Pemohon → Pemeriksaan & TTD MPK → Persetujuan/Penolakan & TTD Pemilik Jadwal → Verifikasi Administratif & Publikasi MPK (**3 blok TTD**).
  - **Workflow B (Peminjaman Resmi MPK):** Pengajuan + TTD MPK langsung ke Pemilik Jadwal → Persetujuan/Penolakan & TTD Pemilik → Pencatatan & Pengarsipan MPK (**2 blok TTD**, tanpa persetujuan ulang MPK).

> **Catatan:** Seluruh tanda tangan, persetujuan, dan surat resmi di aplikasi ini bersifat **simulasi demo** untuk kebutuhan presentasi.

---

## Menjalankan Secara Lokal

Pastikan **Node.js 20+** (atau versi LTS terbaru) dan **npm** sudah terpasang.

```bash
cd demo
npm install
npm run dev
```

Buka URL lokal yang ditampilkan (umumnya `http://localhost:3000`).

---

## Build Produksi & Pratinjau Lokal

```bash
cd demo
npm install
npm run generate   # Menghasilkan static SPA di .output/public (untuk Vercel)
```

Untuk menguji pratinjau hasil build secara lokal melalui Node server (`npx nuxt preview`), jalankan `npm run build` terlebih dahulu:

```bash
npm run build
npm run preview
```

---

## Deploy ke Vercel (Terpisah dari Backend/Aplikasi Utama)

Karena demo berada di subdirektori `demo/`, atur **Root Directory** pada proyek Vercel ke `demo`. Konfigurasi `demo/vercel.json` sudah disiapkan:

- **Root Directory:** `demo`
- **Framework Preset:** `Nuxt.js` (otomatis terdeteksi)
- **Install Command:** `npm install`
- **Build Command:** `npm run generate`
- **Output Directory:** `.output/public`

Atau melalui Vercel CLI dari direktori `demo/`:

```bash
cd demo
npx vercel --prod
```

