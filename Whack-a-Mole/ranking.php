<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$dataFile = __DIR__ . '/scores.csv';
if (!file_exists($dataFile)) {
  echo json_encode([], JSON_UNESCAPED_UNICODE);
  exit;
}

$lines = file($dataFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$rows = [];

foreach ($lines as $line) {
  $cols = str_getcsv($line);
  if (count($cols) < 3) continue;

  [$date, $name, $time] = $cols;

  if (!is_numeric($time)) continue;

  $rows[] = [
    'date' => (string)$date,
    'name' => (string)$name,
    'time' => (string)$time,
    '_timeFloat' => (float)$time,
  ];
}

// タイムが短い順
usort($rows, function ($a, $b) {
  return $a['_timeFloat'] <=> $b['_timeFloat'];
});

// 上位10件
$top = array_slice($rows, 0, 10);
foreach ($top as &$r) {
  unset($r['_timeFloat']);
}

echo json_encode($top, JSON_UNESCAPED_UNICODE);
