# Dea's Board — Panduan Desain

Dokumen ini mengikat. Setiap perubahan antarmuka harus selaras dengan aturan di bawah.

## Identitas

**Dea's Board** adalah papan tugas internal untuk **Iwan** dan sesi-sesi **Hermes**, dalam konteks kerja kantor. Aplikasi dibuka sering dari **ponsel**; layout dan target sentuh harus nyaman di layar kecil.

## Dial

| Dimensi | Nilai | Alasan |
|---------|-------|--------|
| **ENERGY** | 1 | Antarmuka tenang agar fokus pada daftar tugas, bukan dekorasi. |
| **RHYTHM** | 1 | Pola visual seragam (kartu, tabel, form) mengurangi beban kognitif saat beralih layar. |
| **MOTION** | 1 | Hampir tanpa animasi; respons instan lebih penting di jaringan dan perangkat kantor. |

## Palet

| Token | Kelas Tailwind | Alasan |
|-------|----------------|--------|
| Latar | `zinc-950` | Dasar gelap yang tidak memantulkan cahaya berlebihan di layar HP. |
| Permukaan | `zinc-900` | Kartu dan panel terpisah dari latar tanpa kontras keras. |
| Garis | `zinc-800` | Pembatas halus antar blok tanpa mengganggu isi. |
| Teks utama | `zinc-100` | Baca nyaman di permukaan gelap dengan kontras memadai. |
| Teks kedua | `zinc-400` | Metadata dan label sekunder; **tidak boleh lebih redup** (mis. `zinc-500`/`zinc-600`) karena kontras terhadap `zinc-900` harus mencapai **6,91:1**. |
| Aksen | `teal-500` | Satu-satunya warna aksi (tombol utama, fokus, tautan penting). |

## Tipografi

- **Keluarga:** font sistem sans (`ui-sans-serif`, stack default Tailwind).
- **Ukuran isi tabel dan kartu:** **14 piksel** (`text-sm`); alasan: kepadatan informasi tugas tanpa mengorbankan keterbacaan di HP.

## Radius

- **Input, select, textarea:** **8 piksel** (`rounded-lg`).
- **Tombol** (aksi utama, sekunder, tautan bergaya tombol): **12 piksel** (`rounded-xl`).
- **Panel** (kartu, modal, blok besar): **12 piksel** (`rounded-xl`).

## Tombol

- **Tinggi:** **40 piksel** di layar `sm` ke atas (`min-h-10`); **44 piksel** di layar sangat kecil (`min-h-11 sm:min-h-10`) agar area ketuk di ponsel tetap memadai.
- **Alasan:** tombol lebih ringan agar tidak bersaing dengan isi papan, tetapi area ketuk di ponsel tetap 44px.
- **Bayangan:** hanya tombol utama (`shadow-sm`); tombol sekunder tanpa bayangan.
- **Fokus:** `focus-visible:ring-2 focus-visible:ring-teal-500/60` dengan `ring-offset-1` (bukan 2).

## Larangan mengikat

1. **Tidak ada gradien** pada latar, tombol, atau teks.
2. **Tidak ada emoji dekoratif** di label, judul, atau navigasi.
3. **Tidak ada karakter em dash (—)** di teks antarmuka; gunakan frasa bahasa Indonesia (mis. «Belum diisi», «Tanpa sesi»).

## Aksesibilitas

- **Input, select, textarea,** dan target sentuh selain tombol desktop: minimal **44 piksel** tinggi (`min-h-11`).
- **Tombol:** ikuti aturan tinggi responsif di bagian Tombol (44px di layar kecil, 40px di desktop).
- **Cincin fokus** `teal-500` harus terlihat jelas (`focus-visible:ring-2` pada teal).
- **Kontras teks normal** minimal **4,5:1**; teks sekunder mengikuti aturan `zinc-400` di atas.
