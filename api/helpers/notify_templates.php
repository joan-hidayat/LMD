<?php
/**
 * Template notifikasi LMD (T-43). Setiap template menerima variabel dan mengembalikan [subjek, baris isi].
 * Variabel umum: name, subscription_no, app_name, package_name, total, lookup_url.
 */
return [
    'order_created' => function ($v) {
        return ["Pesanan {$v['subscription_no']} — tagihan {$v['total']}", [
            "Terima kasih telah memesan {$v['app_name']} paket {$v['package_name']}.",
            '',
            "No. pesanan : {$v['subscription_no']}",
            "Toko        : {$v['store_name']} ({$v['instance_domain']})",
            "Total       : {$v['total']} (termasuk PPN)",
            "Bayar sebelum: {$v['expires_at']}",
            '',
            $v['payment_url'] ? "Selesaikan pembayaran di: {$v['payment_url']}" : 'Pembayaran akan dikonfirmasi oleh admin Central SaaS.',
            'Simpan nomor pesanan ini untuk melihat status pesanan Anda.',
        ]];
    },
    'payment_paid' => function ($v) {
        return ["Pembayaran diterima — {$v['subscription_no']}", [
            "Pembayaran {$v['payment_no']} sebesar {$v['total']} telah kami terima. Terima kasih!",
            '',
            "Bukti pembayaran (bisa dicetak / disimpan PDF): {$v['receipt_url']}",
            'Lisensi Anda sedang diaktifkan; email berikutnya berisi link aplikasi.',
        ]];
    },
    'payment_failed' => function ($v) {
        return ["Pembayaran gagal — {$v['subscription_no']}", [
            "Pembayaran {$v['payment_no']} untuk pesanan {$v['subscription_no']} gagal diproses.",
            "Coba lagi dengan membuat tagihan baru di: {$v['payment_url']}",
        ]];
    },
    'payment_expired' => function ($v) {
        return ["Tagihan kedaluwarsa — {$v['subscription_no']}", [
            "Tagihan {$v['payment_no']} ({$v['total']}) untuk pesanan {$v['subscription_no']} sudah melewati batas waktu pembayaran.",
            "Buat tagihan baru di: {$v['payment_url']}",
        ]];
    },
    'license_active' => function ($v) {
        $lines = [
            "Lisensi {$v['app_name']} paket {$v['package_name']} untuk toko {$v['store_name']} sudah AKTIF.",
            '',
            "Masa aktif  : {$v['start_date']} – {$v['end_date']}",
            "License key : {$v['license_key']}",
        ];
        if ($v['app_url']) {
            $lines[] = "Buka aplikasi: {$v['app_url']}";
            $lines[] = '';
            $lines[] = "Saat pertama dibuka, buat akun admin dengan email ini ({$v['email']}).";
        } else {
            $lines[] = '';
            $lines[] = 'Masukkan license key di atas saat instalasi aplikasi di server Anda.';
        }
        return ["Lisensi aktif — {$v['app_name']} ({$v['subscription_no']})", $lines];
    },
    'order_rejected' => function ($v) {
        return ["Pesanan dibatalkan — {$v['subscription_no']}", [
            "Pesanan {$v['subscription_no']} ({$v['app_name']} paket {$v['package_name']}) dibatalkan oleh admin Central SaaS.",
            'Bila Anda sudah membayar, hubungi kami dengan menyertakan nomor pesanan ini.',
        ]];
    },
    'license_expiring' => function ($v) {
        return ["Lisensi {$v['app_name']} berakhir {$v['days_text']}", [
            "Masa aktif lisensi toko {$v['store_name']} ({$v['package_name']}) berakhir pada {$v['end_date']}.",
            'Perpanjang sebelum tanggal tersebut agar aplikasi tetap bisa dipakai tanpa jeda.',
            "Perpanjang / upgrade: {$v['showcase_url']}",
        ]];
    },
    'license_expired' => function ($v) {
        return ["Lisensi {$v['app_name']} telah berakhir", [
            "Masa aktif lisensi toko {$v['store_name']} ({$v['package_name']}) berakhir pada {$v['end_date']}. Aplikasi tidak bisa dipakai sampai diperpanjang.",
            "Perpanjang: {$v['showcase_url']}",
        ]];
    },
    'admin_reset_link' => function ($v) {
        return ["Link reset password admin {$v['app_name']}", [
            "Admin Central SaaS membuat link reset password akun admin toko {$v['store_name']}.",
            '',
            $v['reset_url'] ? "Buka link ini (sekali pakai, berlaku sampai {$v['expires_at']}):" : "Kode reset (masukkan di aplikasi, berlaku sampai {$v['expires_at']}):",
            $v['reset_url'] ?: $v['token'],
            '',
            'Abaikan email ini bila Anda tidak meminta reset password.',
        ]];
    },
];
