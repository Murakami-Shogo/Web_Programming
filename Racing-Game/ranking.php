<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

$dataFile = __DIR__ . '/data/race_records.txt';
if (!file_exists($dataFile)) {
  // データが無い場合は空で返す
  exit;
}

$lines = file($dataFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$records = [];

foreach ($lines as $line) {
  $parts = explode(',', $line, 5);
  if (count($parts) < 3) continue;

  $date = $parts[0];
  $name = $parts[1] ?? 'anonymous';
  $time = $parts[2] ?? '';

  if (!preg_match('/^\d+(\.\d{1,2})?$/', $time)) continue;

  $records[] = [
    'date' => $date,
    'name' => $name,
    'time' => $time,
    'time_num' => (float)$time
  ];
}

// タイムが短い順
usort($records, function($a, $b) {
  return $a['time_num'] <=> $b['time_num'];
});

// 上位10件を "date,name,time" で返す
$limit = min(10, count($records));
for ($i = 0; $i < $limit; $i++) {
  $r = $records[$i];
  echo $r['date'] . "," . $r['name'] . "," . $r['time'] . "\n";
}
