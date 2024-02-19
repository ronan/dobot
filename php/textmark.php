#!/usr/local/bin/php
<?php

require "textmark.inc.php";

function textmark_php($key = null, $callback = null, $initial_state = ' ', $create_if_missing = true)
{
    static $stack = []; // The strategic static task stack.

    textmark_log("🤖  do: $key");

    $task = textmark_read_task($key);
    if (!$task && $create_if_missing) {
        if ($p = textmark_read_task(end($stack))) {
            textmark_log("🤖 add: $key");
            $task = textmark_write_task([
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
    textmark_execute($task, $callback);
    array_pop($stack);

    textmark_log("🤖 end: $key");
}

function textmark_execute($task, $callback)
{
    static $last = null;
    $last = $task;
    if (!in_array($task['state'], ['x', '-'])) {
        if ($f = textmark_file_path("tasks/$task[key].inc.php")) {
            include($f);
        }
        $task = textmark_write_task($task, ['state' => '.']);
        $result = $callback ? call_user_func_array($callback, $task['args']) : '';
        $result = $result ? $result : (($last['key'] == $task['key']) ? 'x' : '~');
        if (is_string($result)) {
            $state = substr($result, 0, 1);
            $task['state']  = $state == "\n" ? ' ' : $state;
            $task['output'] = explode("\n", substr($result, 1));
        }
        $task = textmark_write_task($task);
    }
}

function textmark_read_task($key)
{
    if (!$key) return;

    $regex = preg_replace("/`.+`/", "`(.*)`", preg_quote($key));
    $regex = "|(?<indent> *)\- +\[(?<state>.)?\] (?<key>$regex)|";

    $lines = textmark_readme();
    if ($m = textmark_match($regex, $lines)) {
        unset($m[0], $m[1], $m[2], $m[3]);

        $task = array_intersect_key($m, array_flip(['indent', 'state', 'key', 'idx', 'regex']));
        $task['args']   = array_diff_key($m, $task, array_flip(range(0, 3)));
        $task['len']    = 1;
        $task['output'] = [];
        $task['next']   = $task['idx'];
        $task['depth']  = strlen($task['indent']);

        while (++$task['next'] < count($lines)) {
            $d = textmark_indent_depth($l = $lines[$task['next']]);
            if (!$d || $d < $task['depth']) {
                // $task['next']--;
                break;
            }
            if (substr($l, $task['depth'] + 2, 3) == "- [") {
                while ($l === "" || textmark_indent_depth($l) > $task['depth']) {
                    $l = $lines[++$task['next']] ?? null;
                }
                $task['next']--;
                break;
            }
            $output[] = $l;
        }
        $task['len']    = count($task['output']) + 1;
        $task['output'] = textmark_reformat($task['output']);
        return $task;
    }
}

function textmark_write_task($task, $changes = [])
{
    $task = $changes + $task;
    $markdown = [
        "$task[indent]- [$task[state]] $task[key]",
        ...textmark_reformat($task['output'], "$task[indent]  ")
    ];

    $lines = textmark_readme();
    array_splice($lines, $task['idx'], $task['len'], $markdown);
    textmark_readme($lines);

    return textmark_read_task($task['key']);
}

function textmark_trim_newlines($lines)
{
    if ($lines && $lines = trim(implode("\n", $lines), "\n")) {
        return explode("\n", $lines);
    }
    return [];
}

function textmark_indent_depth($lines)
{
    return array_reduce(
        (array)$lines,
        fn ($l, $line) => min($l, strlen($line) - strlen(ltrim($line, " "))),
        100
    );
}

function textmark_reformat($lines, $indent = "")
{
    if (empty($lines)) return [];

    $lines = textmark_trim_newlines($lines);
    $depth = textmark_indent_depth($lines);
    $lines = array_map(
        function ($line) use ($depth, $indent) {
            $line = trim(substr($line, $depth));
            return $line ? "$indent$line" : '';
        },
        $lines
    );

    return $lines ? ['', ...$lines, '', ''] : [];
}

function textmark_readme($lines = null)
{
    return textmark_file("README.md", $lines);
}



function textmark_match($pattern, $lines = null)
{
    foreach (textmark_matches($pattern, $lines) as $i => $m) {
        return $m + ['idx' => $i, 'regex' => $pattern];
    }
}

function textmark_matches($pattern, $lines = null)
{
    $lines = $lines ?? textmark_readme();
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

function textmark_config($key, $default = null)
{
    $lines = [implode('', textmark_readme())];
    $key = preg_quote("**$key:**");
    $sep = "(`|\*\*)";
    if ($m = textmark_match("/$key.*?$sep([^$sep]*)$sep/", $lines)) {
        return trim($m[2], "` \n");
    }
    return $default;
}

function textmark_config_table($values)
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

function textmark_table($values, $headers = null, $rules = null, $widths = null)
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

function textmark_prefix_lines($lines, $prefix)
{
    $lines = is_string($lines) ? explode("\n", $lines) : $lines;
    return array_map(fn ($l) => "$prefix: $l", $lines);
}

# If this script run directly from the command line.
if (realpath($argv[0]) == realpath(__FILE__)) {
    $action = $argv[1] ?? 'do';
    $readme = textmark_file_path("README.md");
    if ($action == 'do'  && !$readme) die("There's no README.md in this directory. Try `textmark new`\n");
    if ($action == 'new' &&  $readme) die("A README.md file exists this directory. Try `textmark do`\n");

    $user = trim(textmark_sh('git config user.name'));
    $mail = trim(textmark_sh('git config user.email'));

    if (in_array($action, ['new', 'redo'])) {
        $path = textmark_file_path("README.md", true);
        $date = date("Y-m-d");
        textmark_readme(
            "# Untitled DoBot Project

## About

**Version:** `0.0.1`

## TODO

- [ ] Configure project

## Changelog

## [0.0.1] - $date

- **Added:** README.md file created automatically at $path for **$user**

## Contributors

- **🤖 DoBot:** <https://github.com/ronan/textmark>
- **$user:** <$mail>
"
        );
    } else if ($action == 'do') {
        $start = microtime(true);
        textmark_log("DoBot do run by $user", 0);
        foreach (textmark_matches('/^\- \[.?\] (.+)/') as $m) {
            textmark($m[1]);
        }
        $took = microtime(true) - $start;
        textmark_log("DoBot do run complete (took: $took ms)");
    }
}

# Built-in tasks
/*
textmark("Configure project", function () {
});
*/