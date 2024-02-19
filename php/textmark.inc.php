#!/usr/local/bin/php
<?php

function textmark_file_path($path, $create = false)
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

function textmark_file($path = null, $lines = null, $flags = 0)
{
  if (!$path) return [];
  if ($lines) {
    $path = textmark_file_path($path, true);
    $lines = is_array($lines) ? implode("\n", $lines) : $lines;
    $lines = preg_replace("/\n\n+/", "\n\n", $lines);
    file_put_contents($path, $lines, $flags);
  }

  $path = textmark_file_path($path);
  return file_exists($path) ? explode("\n", file_get_contents($path)) : false;
}

function textmark_log($msg, $flags = FILE_APPEND)
{
  $prefix = date("c");
  $msg = implode("\n", array_map(fn ($l) => "$prefix: $l", explode("\n", (string)$msg)));
  $msg = "$msg\n";
  textmark_file('textmark.log', $msg, $flags);
  fwrite(STDERR, $msg);
}

function textmark_sh($command = "echo 'textmark_sh() was called without a command'")
{
  textmark_log("> $command\n");
  $out = `$command 2>&1`;
  textmark_log($out);
  return $out;
}
