<?php
declare(strict_types=1);

/**
 * POST:
 *  - name: string (optional)
 *  - time: numeric string (required)
 */

$dataFile = __DIR__ . '/scores.csv';

$name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
$time = isset($_POST['time']) ? trim((string)$_POST['time']) : '';

if ($time === '' || !is_numeric($time)) {
  http_response_code(400);
  echo "Invalid time";
  exit;
}

$timeFloat = (float)$time;

// 常識的な範囲チェック（デモ用）
if ($timeFloat <= 0.0 || $timeFloat > 3600.0) {
  http_response_code(400);
  echo "Out of range";
  exit;
}

// 名前は空でもOK（匿名扱い）。長すぎは制限。
if (mb_strlen($name) > 30) {
  $name = mb_substr($name, 0, 30);
}

// ファイルがなければ作る
if (!file_exists($dataFile)) {
  touch($dataFile);
}

$fp = fopen($dataFile, 'ab');
if ($fp === false) {
  http_response_code(500);
  echo "Failed to open file";
  exit;
}

flock($fp, LOCK_EX);

// 元の形式に寄せて日時は yy/mm/dd を維持（既存データと混ぜやすい）
$date = date("y/m/d H:i:s");

// CSVとして追記
fputcsv($fp, [$date, $name, number_format($timeFloat, 3, '.', '')]);

flock($fp, LOCK_UN);
fclose($fp);

echo "OK";
