<div>
    <!-- Content Header -->
    <div class="content-header pb-2">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h1 class="m-0 text-dark font-weight-bold" style="font-size: 22px;">
                        <i class="fas fa-th text-teal mr-2"></i> Peta Pos Akun & Unit Kerja (Level 3)
                    </h1>
                    <p class="text-muted small mb-0 mt-1">
                        Matriks pemetaan hubungan dan pengaruh unit kerja departemen terhadap 12 pos akun finansial inti pabrik manufaktur botol plastik.
                    </p>
                </div>
                <div class="col-sm-5 text-sm-right mt-2 mt-sm-0">
                    <button type="button" wire:click="resetMatrix" class="btn btn-sm btn-outline-secondary mr-2" title="Kembalikan ke Matriks Standar">
                        <i class="fas fa-undo mr-1"></i> Reset Default
                    </button>
                    <div class="d-inline-flex align-items-center bg-white px-3 py-1 rounded shadow-xs border">
                        <label class="small font-weight-bold mr-2 mb-0 text-muted">Periode:</label>
                        <select wire:model.live="selectedPeriod" class="form-control form-control-sm border-0 font-weight-bold text-teal" style="width: auto; background: transparent;">
                            @foreach($periods as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Metric Cards & Legend -->
            <div class="row mb-3">
                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-teal text-white rounded"><i class="fas fa-building"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">UNIT KERJA (ODOO)</span>
                            <span class="info-box-number text-dark font-weight-bold" style="font-size: 20px;">{{ $totalDepts }} Departemen</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-info text-white rounded"><i class="fas fa-list-ol"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">POS AKUN INTI</span>
                            <span class="info-box-number text-dark font-weight-bold" style="font-size: 20px;">{{ $totalPos }} Pos Finansial</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-primary text-white rounded font-weight-bold">K</span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">PENGARUH KUAT (K)</span>
                            <span class="info-box-number text-primary font-weight-bold" style="font-size: 20px;">{{ $countK }} Titik Pengaruh</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-secondary text-white rounded font-weight-bold">L</span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">PENGARUH LEMAH (L)</span>
                            <span class="info-box-number text-secondary font-weight-bold" style="font-size: 20px;">{{ $countL }} Titik Pendukung</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Matriks Table Card -->
            <div class="card card-outline card-teal shadow-xs mb-4">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center flex-wrap">
                        <span class="font-weight-bold text-dark mr-3">
                            <i class="fas fa-table text-teal mr-1"></i> Matriks Hubungan 12 Pos Akun Inti & Unit Kerja Odoo
                        </span>
                        <!-- Legenda Singkat -->
                        <div class="d-inline-flex align-items-center" style="gap: 12px; font-size: 12px;">
                            <span><span class="badge badge-primary px-2 py-1 font-weight-bold">K</span> = Pengaruh Kuat (Direct Driver)</span>
                            <span><span class="badge badge-secondary px-2 py-1 font-weight-bold">L</span> = Pengaruh Lemah (Indirect Driver)</span>
                            <span><span class="badge badge-light border px-2 py-1 text-muted font-weight-bold">-</span> = Tidak Ada Pengaruh</span>
                        </div>
                    </div>
                    <small class="text-muted mt-2 mt-md-0">
                        <i class="fas fa-check-circle text-teal mr-1"></i> Tersambung langsung ke database Odoo ERP (mysql_finance).
                    </small>
                </div>

                <div class="card-body p-0 table-responsive" style="max-height: 650px; overflow-x: auto; overflow-y: auto;">
                    <style>
                        .matrix-table {
                            font-size: 12px;
                            border-collapse: separate;
                            border-spacing: 0;
                        }
                        .matrix-table th, .matrix-table td {
                            border: 1px solid #e2e8f0;
                            vertical-align: middle !important;
                            text-align: center;
                            padding: 6px 4px;
                        }
                        .matrix-table th.unit-col {
                            min-width: 230px;
                            max-width: 260px;
                            text-align: left !important;
                            position: sticky;
                            left: 0;
                            background: #f8fafc;
                            z-index: 3;
                            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
                        }
                        .matrix-table td.unit-col {
                            text-align: left !important;
                            position: sticky;
                            left: 0;
                            background: #ffffff;
                            z-index: 2;
                            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
                        }
                        .matrix-table th.pos-header {
                            min-width: 82px;
                            padding: 8px 4px;
                            background: #f1f5f9;
                            position: sticky;
                            top: 0;
                            z-index: 2;
                        }
                        .matrix-select {
                            appearance: none;
                            -webkit-appearance: none;
                            background-color: transparent;
                            border: 1px solid transparent;
                            border-radius: 6px;
                            width: 50px;
                            height: 28px;
                            text-align: center;
                            text-align-last: center;
                            font-weight: 700;
                            font-size: 12px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        }
                        .matrix-select:hover {
                            border-color: #cbd5e1;
                            background-color: #f8fafc;
                        }
                        .matrix-select.val-k {
                            background-color: #e0e7ff;
                            color: #3730a3;
                            border-color: #c7d2fe;
                        }
                        .matrix-select.val-l {
                            background-color: #f1f5f9;
                            color: #475569;
                            border-color: #e2e8f0;
                        }
                        .matrix-select.val-dash {
                            color: #94a3b8;
                        }
                    </style>

                    <table class="table table-bordered table-hover m-0 matrix-table">
                        <thead>
                            <tr>
                                <th class="unit-col font-weight-bold text-uppercase bg-light" style="top: 0; z-index: 4;">
                                    UNIT KERJA (DEPARTEMEN ODOO)
                                </th>
                                @foreach($posAkunList as $pa)
                                    <th class="pos-header" title="{{ $pa['full_name'] }} &middot; Driver Rasio: {{ $pa['ratio_link'] }}">
                                        <div class="font-weight-bold text-dark" style="font-size: 11px;">{{ $pa['code'] }}</div>
                                        <div class="font-weight-bold text-teal text-truncate" style="font-size: 10px; max-width: 85px;">
                                            {{ $pa['name'] }}
                                        </div>
                                        <div class="text-muted text-truncate" style="font-size: 9px; max-width: 85px;">
                                            {{ $pa['ratio_link'] }}
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($departments as $dept)
                                @php
                                    $dCode = $dept->code;
                                    $kpiList = $deptObjectives->get($dCode) ?? collect([]);
                                @endphp
                                <tr>
                                    <td class="unit-col">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="badge badge-teal font-weight-bold mr-1" style="font-size: 11px;">
                                                    {{ $dept->code }}
                                                </span>
                                                <strong class="text-dark">{{ $dept->name }}</strong>
                                            </div>
                                            @if($kpiList->count() > 0)
                                                <span class="badge badge-light border text-muted ml-1" title="{{ $kpiList->count() }} Sasaran Mutu terhubung" style="font-size: 10px;">
                                                    <i class="fas fa-bullseye text-teal mr-1"></i>{{ $kpiList->count() }}
                                                </span>
                                            @endif
                                        </div>
                                        <small class="text-muted d-block mt-1" style="font-size: 10px;">
                                            {{ isset($dept->description) && $dept->description ? $dept->description : ('Divisi Operasional ' . $dept->name) }}
                                        </small>
                                    </td>
                                    @foreach($posAkunList as $pa)
                                        @php
                                            $paCode = $pa['code'];
                                            $cellVal = $matrix[$dCode][$paCode] ?? '-';
                                            $selectClass = 'val-dash';
                                            if ($cellVal === 'K') $selectClass = 'val-k';
                                            elseif ($cellVal === 'L') $selectClass = 'val-l';
                                        @endphp
                                        <td class="p-1">
                                            <select wire:change="updateCell('{{ $dCode }}', '{{ $paCode }}', $event.target.value)" 
                                                    class="matrix-select {{ $selectClass }}"
                                                    title="{{ $dept->name }} &rarr; {{ $pa['code'] }} {{ $pa['name'] }} ({{ $pa['full_name'] }})">
                                                <option value="-" {{ $cellVal === '-' ? 'selected' : '' }}>-</option>
                                                <option value="K" {{ $cellVal === 'K' ? 'selected' : '' }}>K</option>
                                                <option value="L" {{ $cellVal === 'L' ? 'selected' : '' }}>L</option>
                                            </select>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-hand-pointer text-teal mr-1"></i> Klik kotak untuk mengubah pengaruh: <strong>K (Kuat)</strong>, <strong>L (Lemah)</strong>, atau <strong>- (Netral)</strong>. Tersimpan instan.
                    </div>
                    <div>
                        Pabrik Botol Plastik &middot; Total <strong>{{ $totalDepts }} Unit Kerja &times; {{ $totalPos }} Pos Akun Inti</strong>.
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
