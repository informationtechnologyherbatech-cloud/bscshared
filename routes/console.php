<?php

use App\Models\ApiAccessLog;
use App\Models\PairingCode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jejak akses API bertambah pada setiap permintaan — termasuk yang ditolak, yang
// dapat datang dari siapa saja. Tanpa pemangkasan, tabelnya tumbuh tanpa batas.
// Kode pendaftaran yang sudah lama kedaluwarsa ikut dibuang.
Schedule::command('model:prune', [
    '--model' => [ApiAccessLog::class, PairingCode::class],
])->daily();
