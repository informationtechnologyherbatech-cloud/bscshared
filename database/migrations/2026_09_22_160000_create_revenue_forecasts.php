<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sheet "L1 Target Revenue" bagian A–F: bahan penyusunan target revenue
 * setahun per entitas. Satu baris per entitas per tahun target.
 *
 * Isinya bentuk bebas (jumlah brand, channel, inisiatif berbeda tiap entitas),
 * jadi disimpan sebagai JSON; hasil hitungnya tidak disimpan — selalu dihitung
 * ulang oleh App\Support\Bsc\RevenueForecast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('year', 4);                          // tahun target
            $table->json('history')->nullable();                // realisasi 3 tahun sebelum tahun dasar
            $table->decimal('base_ytd', 22, 2)->nullable();     // timpa YTD tahun dasar (bila data bulanan belum ada)
            $table->unsignedTinyInteger('base_months')->nullable();
            $table->json('channels')->nullable();
            $table->json('brands')->nullable();
            $table->json('ansoff')->nullable();
            $table->json('swot')->nullable();
            $table->decimal('swot_adjustment', 8, 6)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_forecasts');
    }
};
