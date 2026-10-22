<?php

$month = isset($argv[1]) ? (int)$argv[1] : 8;

chdir('C:/laragon/www/finance');

// Clear existing DB env vars so finance .env is strictly honored
putenv('DB_DATABASE=finance');
$_ENV['DB_DATABASE'] = 'finance';
$_SERVER['DB_DATABASE'] = 'finance';

require_once 'C:/laragon/www/finance/vendor/autoload.php';
$app = require_once 'C:/laragon/www/finance/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/fat/analisa-rasio', 'GET', ['month' => $month]);
$controller = new \App\Http\Controllers\Fat\FinancialRatioController();
$view = $controller->index($request);
$data = $view->getData()['ratioGroups'] ?? [];

echo json_encode($data, JSON_UNESCAPED_UNICODE);
