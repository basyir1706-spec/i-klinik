# i-Klinik Politeknik Besut

Sistem pengurusan klinik dalam talian untuk Politeknik Besut dengan sokongan untuk pelajar, staff, doktor, dan OKU.

## Ciri-ciri Utama

### 4 Jenis Pengguna
1. **Student** - Pelajar Politeknik Besut
2. **Doctor** - Doktor klinik
3. **Staff** - Staff klinik
4. **OKU** - Pengguna kurang upaya (boleh terdiri daripada student atau staff)

### Login
- **Student/Staff**: Gunakan nombor matrik (contoh: 34DIT24F1001)
- **Doctor**: Gunakan emel (contoh: doctor@polibesut.edu.my)
- **Kata Laluan Lalai**: `123456`

### Dashboard Student/Staff (3 Menu)
1. **Dashboard**
   - Nombor giliran semasa
   - Panduan klinik dengan 5 langkah
   - Senarai giliran hari ini
   
2. **Appointment**
   - Buat temujanji baru
   - Lihat sejarah temujanji
   - Batal temujanji
   
3. **Pinjaman Stok**
   - Pinjam first aid (ubatan asas, pembalut, rawatan luka)
   - Lihat sejarah pinjaman
   
4. **Pinjaman OKU** (khusus untuk pengguna OKU)
   - Pinjam peralatan OKU
   - Lihat sejarah pinjaman

### Dashboard Doctor (7 Menu)
1. **Dashboard**
   - 5 matrix card statistik:
     - Appointment hari ini
     - Peratusan stok inventory
     - Bilangan ubat pharmacy
     - Peralatan OKU dipinjam
     - Pesakit kritikal
   - Aktiviti terkini
   
2. **Appointment**
   - Update patient selepas consultation
   - Lihat nombor giliran semasa
   - Sahkan temujanji
   - Mulakan rawatan
   
3. **Patient**
   - Cari seluruh warga Polibesut
   - Lihat maklumat lengkap patient
   - Edit maklumat patient
   - Sejarah lawatan
   
4. **Unit OKU**
   - Lulus/tolak permohonan pinjaman OKU
   - Lihat pinjaman aktif
   - Edit senarai peralatan OKU
   
5. **Inventory (First Aid)**
   - Status stok (jumlah item dipinjam, peratusan stok)
   - Lulus/tolak permohonan pinjaman
   - Edit/update inventory
   - 3 kategori: Ubatan Asas, Rawatan Luka, Pembalut
   
6. **Pharmacy**
   - Tambah ubat baru
   - Medicine inventory
   - Dispense ubat kepada patient
   
7. **Unit Kecemasan**
   - Laporan kecemasan
   - Jadual staff pemandu
   - Log perjalanan pemandu

## Ciri-ciri UI/UX

### Tema
- **Warna**: Green Mesh Gradient
```css
background-color: #022c2e;
background-image: 
    radial-gradient(at 20% 30%, #00ff00 0px, transparent 50%),
    radial-gradient(at 80% 40%, #009a44 0px, transparent 50%);
background-attachment: fixed;
```

### Navbar
- Icon profil user di atas kanan
- Dropdown untuk profil dan log keluar
- Tanda mark merah (seperti TikTok) untuk notifikasi baru di sidebar

### Background Images
- **Login**: politebesut.jpg dengan green mesh gradient overlay
- **Dashboard Banner**: footage_klinik.webp

## Installation

### ⚡ Quick Start (Recommended)

1. **Start XAMPP**
   - Buka XAMPP Control Panel
   - Start **Apache** dan **MySQL** (pastu kedua-dua hijau)

2. **Check Setup**
   - Buka browser: **http://localhost/eklinik/setup_checker.php**
   - Ikut langkah yang dipaparkan jika ada error

3. **Login**
   - Jika semua check passed, klik "Go to Login Page"
   - Atau pergi ke: **http://localhost/eklinik**

### 📋 Langkah-langkah Lengkap

1. **Start XAMPP**
   - Buka XAMPP Control Panel
   - Start **Apache** dan **MySQL**

2. **Import Database**
   - Buka browser dan pergi ke: http://localhost/phpmyadmin
   - Klik tab "SQL" di bahagian atas
   - Copy dan paste kandungan file `database/eklinik.sql`
   - Klik "Go" untuk execute
   - **ATAU**: Drop database `eklinik` jika sudah wujud, kemudian import semula

3. **Reset Password (Jika Login Tidak Berfungsi)**
   - Jika anda sudah import database sebelumnya dan login tidak berfungsi:
   - Buka phpMyAdmin
   - Pilih database `eklinik`
   - Klik tab "SQL"
   - Copy dan paste kandungan file `database/reset_passwords.sql`
   - Klik "Go"
   - Ini akan reset semua password kepada '123456'

4. **Verify Images**
   - Pastikan file berikut wujud:
     - `polibesut.jpg` (background login)
     - `footage_klinik.webp` (background banner dashboard)

5. **Test Connection**
   - Buka browser dan pergi ke: http://localhost/eklinik/setup_checker.php
   - Pastikan semua test menunjukkan ✓ (successful)
   - Jika ada error, ikut langkah yang dipaparkan

6. **Access System**
   - Buka browser dan pergi ke: http://localhost/eklinik

## Default Users

Semua password: **123456**

### Doctor
- **Email**: doctor@polibesut.edu.my
- **Nama**: Dr. Ahmad Razak

### Staff
- **Email**: staff@polibesut.edu.my
- **Nama**: Nurse Siti Nurhaliza

### Students
| Matrix No | Name | Email |
|-----------|------|-------|
| 34DIT24F1001 | Ali bin Abu | student1@polibesut.edu.my |
| 34DIT24F1002 | Siti Aminah | student2@polibesut.edu.my |
| 34DIT24F1003 | Ahmad bin Mohamed | student3@polibesut.edu.my |
| 34DIT24F1004 | Fatimah binti Ismail (OKU) | oku_student@polibesut.edu.my |

### OKU Staff
- **Email**: oku_staff@polibesut.edu.my
- **Nama**: Mohd Azman

## Struktur Folder

```
eklinik/
├── assets/
│   └── css/
│       └── style.css
├── config/
│   ├── config.php
│   └── database.php
├── database/
│   └── eklinik.sql
├── doctor/
│   ├── layout.php
│   ├── dashboard.php
│   ├── appointment.php
│   ├── patient.php
│   ├── oku-unit.php
│   ├── inventory.php
│   ├── pharmacy.php
│   └── emergency.php
├── student/
│   ├── layout.php
│   ├── dashboard.php
│   ├── appointment.php
│   ├── stock-loan.php
│   └── oku-loan.php
├── footage_klinik.webp
├── polibesut.jpg
├── index.php
├── login.php
├── logout.php
└── unauthorized.php
```

## Notifikasi Sistem

Notifikasi akan dihantar secara automatik untuk:
- Temujanji baru/disahkan/selesai
- Permohonan pinjaman stok/OKU diluluskan/ditolak
- Laporan kecemasan baru
- Ubat diberikan

## Waktu Operasi Klinik

**8:00 Pagi - 5:00 Petang**

## Panduan Mendapatkan Rawatan

1. **Buat Temujanji** - Tempah slot masa melalui sistem
2. **Daftar Diri** - Hadir 10 minit awal dan scan kad matrik
3. **Tunggu Giliran** - Pantau status melalui dashboard
4. **Rawatan** - Masuk bilik doktor apabila nombor dipanggil
5. **Ambil Ubat** - Ambil ubat di farmasi jika ada preskripsi

## Teknologi Digunakan

- **Backend**: PHP (tanpa framework)
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Icons**: Font Awesome 6
- **Styling**: Custom CSS dengan Green Mesh Gradient theme

## Troubleshooting

### ❌ Login Tidak Berfungsi / Tidak Response

**Langkah 1: Test Connection**
- Buka: http://localhost/eklinik/test_connection.php
- Pastikan semua test menunjukkan ✓ (successful)

**Langkah 2: Reset Password**
- Buka phpMyAdmin: http://localhost/phpmyadmin
- Pilih database `eklinik`
- Klik tab "SQL"
- Copy dan paste kandungan file `database/reset_passwords.sql`
- Klik "Go"
- Cuba login semula dengan password: `123456`

**Langkah 3: Clear Session**
- Tutup semua browser tab
- Clear browser cache dan cookies
- Restart Apache dalam XAMPP Control Panel
- Cuba login semula

### Database Connection Failed
- Pastikan MySQL running dalam XAMPP Control Panel (hijau)
- Verify database `eklinik` telah diimport
- Check credential dalam `config/database.php`
- Test connection: http://localhost/eklinik/test_connection.php

### Images Not Showing
- Pastikan file `polibesut.jpg` dan `footage_klinik.webp` wujud dalam folder `eklinik`
- Check path dalam CSS dan HTML
- Clear browser cache

### Session Issues
- Clear browser cache dan cookies
- Pastikan `session_start()` dipanggil di setiap halaman
- Restart Apache dalam XAMPP Control Panel

### Error "Cannot Modify Header Information"
- Ini bermakna ada output sebelum `header()` dipanggil
- Clear browser cache
- Restart Apache
- Pastikan tiada whitespace sebelum `<?php`

### White Screen / Blank Page
- Buka: http://localhost/eklinik/test_connection.php
- Check error message yang dipaparkan
- Pastikan error reporting dihidupkan dalam `config/config.php`

## Support

Untuk sebarang isu atau pertanyaan, sila hubungi unit IT Politeknik Besut.

---

**© 2024 i-Klinik Politeknik Besut**
