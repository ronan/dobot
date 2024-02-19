#!/usr/local/bin/php
<?php

function dobot_file_path($path, $create = false)
{
  if (substr($path, 0, 1) == '/' && file_exists($path)) return $path;
  $paths = [
    '.', realpath(__DIR__)
  ];
  foreach ($paths as $base) {
    if (file_exists("$base/$path")) {
      return "$base/$path";
    }
  }
  if ($create) {
    file_put_contents("./$path", "");
    return "./$path";
  }
  return false;
}

function dobot_file($path = null, $lines = null, $flags = 0)
{
  if (!$path) return [];
  if ($lines) {
    $path = dobot_file_path($path, true);
    $lines = is_array($lines) ? implode("\n", $lines) : $lines;
    $lines = preg_replace("/\n\n+/", "\n\n", $lines);
    file_put_contents($path, $lines, $flags);
  }

  $path = dobot_file_path($path);
  return file_exists($path) ? explode("\n", file_get_contents($path)) : false;
}

function dobot_log($msg, $flags = FILE_APPEND)
{
  $prefix = date("c");
  $msg = implode("\n", array_map(fn ($l) => "$prefix: $l", explode("\n", (string)$msg)));
  $msg = "$msg\n";
  dobot_file('dobot.log', $msg, $flags);
  fwrite(STDERR, $msg);
}

function dobot_sh($command = "echo 'dobot_sh() was called without a command'")
{
  dobot_log("> $command\n");
  $out = `$command 2>&1`;
  dobot_log($out);
  return $out;
}
