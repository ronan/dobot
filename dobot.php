#!/usr/local/bin/php
<?php

function dobot_php($key = null, $callback = null, $initial_state = ' ', $create_if_missing = true)
{
    static $stack = []; // The strategic static task stack.

    dobot_log("🤖  do: $key");

    $task = dobot_read_task($key);
    if (!$task && $create_if_missing) {
        if ($p = dobot_read_task(end($stack))) {
            dobot_log("🤖 add: $key");
            $task = dobot_write_task([
                'key'    => $key,
                'state'  => $initial_state,
                'indent' => $p['indent'] . '  ',
                'idx'    => $p['next'],
                'len'    => 0,
                'output' => '',
            ]);
        }
    }
    if (!$task) return;

    $stack[] = $task['key'];
    dobot_execute($task, $callback);
    array_pop($stack);

    dobot_log("🤖 end: $key");
}

function dobot_execute($task, $callback)
{
    static $last = null;
    $last = $task;
    if (!in_array($task['state'], ['x', '-'])) {
        if ($f = dobot_file_path("tasks/$task[key].inc.php")) {
            include($f);
        }
        $task = dobot_write_task($task, ['state' => '.']);
        $result = $callback ? call_user_func_array($callback, $task['args']) : '';
        $result = $result ? $result : (($last['key'] == $task['key']) ? 'x' : '~');
        if (is_string($result)) {
            $state = substr($result, 0, 1);
            $task['state']  = $state == "\n" ? ' ' : $state;
            $task['output'] = explode("\n", substr($result, 1));
        }
        $task = dobot_write_task($task);
    }
}

function dobot_read_task($key)
{
    if (!$key) return;

    $regex = preg_replace("/`.+`/", "`(.*)`", preg_quote($key));
    $regex = "|(?<indent> *)\- +\[(?<state>.)?\] (?<key>$regex)|";

    $lines = dobot_readme();
    if ($m = dobot_match($regex, $lines)) {
        unset($m[0], $m[1], $m[2], $m[3]);

        $task = array_intersect_key($m, array_flip(['indent', 'state', 'key', 'idx', 'regex']));
        $task['args']   = array_diff_key($m, $task, array_flip(range(0, 3)));
        $task['len']    = 1;
        $task['output'] = [];
        $task['next']   = $task['idx'];
        $task['depth']  = strlen($task['indent']);

        while (++$task['next'] < count($lines)) {
            $d = dobot_indent_depth($l = $lines[$task['next']]);
            if (!$d || $d < $task['depth']) {
                // $task['next']--;
                break;
            }
            if (substr($l, $task['depth'] + 2, 3) == "- [") {
                while ($l === "" || dobot_indent_depth($l) > $task['depth']) {
                    $l = $lines[++$task['next']] ?? null;
                }
                $task['next']--;
                break;
            }
            $output[] = $l;
        }
        $task['len']    = count($task['output']) + 1;
        $task['output'] = dobot_reformat($task['output']);
        return $task;
    }
}

function dobot_write_task($task, $changes = [])
{
    $task = $changes + $task;
    $markdown = [
        "$task[indent]- [$task[state]] $task[key]",
        ...dobot_reformat($task['output'], "$task[indent]  ")
    ];

    $lines = dobot_readme();
    array_splice($lines, $task['idx'], $task['len'], $markdown);
    dobot_readme($lines);

    return dobot_read_task($task['key']);
}

function dobot_trim_newlines($lines)
{
    if ($lines && $lines = trim(implode("\n", $lines), "\n")) {
        return explode("\n", $lines);
    }
    return [];
}

function dobot_indent_depth($lines)
{
    return array_reduce(
        (array)$lines,
        fn ($l, $line) => min($l, strlen($line) - strlen(ltrim($line, " "))),
        100
    );
}

function dobot_reformat($lines, $indent = "")
{
    if (empty($lines)) return [];

    $lines = dobot_trim_newlines($lines);
    $depth = dobot_indent_depth($lines);
    $lines = array_map(
        function ($line) use ($depth, $indent) {
            $line = trim(substr($line, $depth));
            return $line ? "$indent$line" : '';
        },
        $lines
    );

    return $lines ? ['', ...$lines, '', ''] : [];
}

function dobot_readme($lines = null)
{
    return dobot_file("README.md", $lines);
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

function dobot_match($pattern, $lines = null)
{
    foreach (dobot_matches($pattern, $lines) as $i => $m) {
        return $m + ['idx' => $i, 'regex' => $pattern];
    }
}

function dobot_matches($pattern, $lines = null)
{
    $lines = $lines ?? dobot_readme();
    return
        array_filter(
            array_map(
            function ($line) use ($pattern) {
                    $m = [];
                    preg_match($pattern, $line, $m);
                    return $m;
            },
                $lines
        )
    );
}

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

function dobot_config($key, $default = null)
{
    $lines = [implode('', dobot_readme())];
    $key = preg_quote("**$key:**");
    $sep = "(`|\*\*)";
    if ($m = dobot_match("/$key.*?$sep([^$sep]*)$sep/", $lines)) {
        return trim($m[2], "` \n");
    }
    return $default;
}

function dobot_config_table($values)
{
    $key_length = strlen('Configuration');
    $val_length = strlen('Value');
    $table = [];
    foreach ($values as $key => $val) {
        $table["**$key:**"] = "`$val`";
        $key_length = min(30, max($key_length, strlen("**$key:**")));
        $val_length = min(50, max($key_length, strlen("`$val`")));
    }
    $table = [
        "Configuration" => "Value",
        str_repeat("-", $key_length - 1) . ":" => ":" . str_repeat("-", $val_length - 1)
    ] + $table;
    $out = "";
    foreach ($table as $key => $val) {
        $key = str_pad($key, $key_length, ' ', STR_PAD_LEFT);
        $val = str_pad($val, $val_length);
        $out .=  "| $key | $val |\n";
    }
    return "\n\n$out\n\n";
}

function dobot_table($values, $headers = null, $rules = null, $widths = null)
{
    $cols = array_reduce($values, fn ($c, $i) => max($c, count($i)));
    $widths  = $widths  ?? array_fill(0, $cols, intval(80 / $cols));
    $headers = $headers ?? array_fill(0, $cols, '');
    $rules   = $rules   ?? array_fill(0, $cols, "-");
    $values  = [$headers, $rules, ...$values];
    $values  = array_map(
        function ($row) use ($widths) {
            $padded = array_map(
                fn ($c, $w) => str_pad((string)$c, $w, ' '),
                $row,
                $widths
            );
            return "| " . implode(" | ", $padded) . " |";
        },
        $values
    );
    return "\n\n" . implode("\n", $values) . "\n\n";
}

function dobot_log($msg, $flags = FILE_APPEND)
{
    $prefix = date("c");
    $msg = implode("\n", array_map(fn ($l) => "$prefix: $l", explode("\n", (string)$msg)));
    $msg = "$msg\n";
    dobot_file('dobot.log', $msg, $flags);
    fwrite(STDERR, $msg);
}

function dobot_prefix_lines($lines, $prefix)
{
    $lines = is_string($lines) ? explode("\n", $lines) : $lines;
    return array_map(fn ($l) => "$prefix: $l", $lines);
}

function dobot_sh($command = "echo 'dobot_sh() was called without a command'")
{
    dobot_log("> $command\n");
    $out = `$command 2>&1`;
    dobot_log($out);
    return $out;
}

# If this script run directly from the command line.
if (realpath($argv[0]) == realpath(__FILE__)) {
    $action = $argv[1] ?? 'do';
    $readme = dobot_file_path("README.md");
    if ($action == 'do'  && !$readme) die("There's no README.md in this directory. Try `dobot new`\n");
    if ($action == 'new' &&  $readme) die("A README.md file exists this directory. Try `dobot do`\n");

    $user = trim(dobot_sh('git config user.name'));
    $mail = trim(dobot_sh('git config user.email'));

    if (in_array($action, ['new', 'redo'])) {
        $path = dobot_file_path("README.md", true);
        $date = date("Y-m-d");
        dobot_readme(
            "# Untitled DoBot Project

## About

**Version:** `0.0.1`

## TODO

- [ ] Configure project

## Changelog

## [0.0.1] - $date

- **Added:** README.md file created automatically at $path for **$user**

## Contributors

- **🤖 DoBot:** <https://github.com/ronan/dobot>
- **$user:** <$mail>
"
        );
    } else if ($action == 'do') {
        $start = microtime(true);
        dobot_log("DoBot do run by $user", 0);
        foreach (dobot_matches('/^\- \[.?\] (.+)/') as $m) {
            dobot($m[1]);
        }
        $took = microtime(true) - $start;
        dobot_log("DoBot do run complete (took: $took ms)");
    }
}

# Built-in tasks
/*
dobot("Configure project", function () {
});
*/