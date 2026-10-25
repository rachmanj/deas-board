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

- **Kontrol** (input, tombol, select): **8 piksel** (`rounded-lg`).
- **Panel** (kartu, modal, blok besar): **12 piksel** (`rounded-xl`).

## Larangan mengikat

1. **Tidak ada gradien** pada latar, tombol, atau teks.
2. **Tidak ada emoji dekoratif** di label, judul, atau navigasi.
3. **Tidak ada karakter em dash (—)** di teks antarmuka; gunakan frasa bahasa Indonesia (mis. «Belum diisi», «Tanpa sesi»).

## Aksesibilitas

- Semua **kontrol interaktif** minimal **44 piksel** tinggi (`min-h-11` atau setara).
- **Cincin fokus** `teal-500` harus terlihat jelas (`focus-visible:ring-2` pada teal).
- **Kontras teks normal** minimal **4,5:1**; teks sekunder mengikuti aturan `zinc-400` di atas.
