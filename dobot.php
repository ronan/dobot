#!/usr/local/bin/php
<?php

function dobot($key = null, $callback = null, $initial_state = ' ')
{
    static $stack = []; // The strategic static task stack.

    dobot_log("🤖  do: $key");

    $task = dobot_read_task($key);
    if (!$task && $p = end($stack)) {
        $task = dobot_write_task([
            'key'    => $key, 
            'state'  => $initial_state,
            'indent' => $p['indent'] . '  ',
            'idx'    => $p['next']],
        );
    }

    $stack[] = $task;
    if (!in_array($task['state'], ['x', '-'])) {
        $task   = dobot_write_task($task, '.');
        $result = dobot_execute($task, $callback);
        $task   = dobot_write_task($task, $result);
    }
    array_pop($stack);

    dobot_log("🤖 end: $key");
}

function dobot_execute($task, $callback) {
    static $last = null;
    $last = $task;
    if ($f = dobot_file_path("$task[key].inc.php")) {
        include($f);
    }
    $out = $callback ? call_user_func_array($callback, $task['args']) : ' ';
    return $out ? $out : (($last == $task) ? 'x' : '~');
}

function dobot_read_task($key)
{
    $regex = preg_replace("/`.+`/", "`(.*)`", preg_quote($key));
    $regex = "|(?<indent> *)\- +\[(?<state>.)?\] (?<key>$regex)|";

    $lines = dobot_file();
    if ($m = dobot_match($regex, $lines)) {
        unset($m[0], $m[1], $m[2], $m[3]);
        $task = array_intersect_key($m, array_flip(['indent', 'state', 'key', 'idx', 'regex']));
        $task['args'] = array_diff_key($m, $task, array_flip(range(0, 3)));
        $output = []; $next = $task['idx'] + 1;
        if (empty($lines[$next]) && !empty($lines[$next+1])) {
            while (is_string($l = $lines[++$next] ?? null)) {
                if (
                    !str_starts_with($l, "$task[indent]  ") ||
                     str_starts_with($l, "$task[indent]  - [")
                ) break;
                $output[] = $l;
            }
        }
        $task['next'] = $next;
        $task['output'] = dobot_deformat($output);
        return $task;
    }
}

function dobot_write_task($task, $result = ' ')
{
    if (is_string($result)) {
        $task['state'] = substr($result, 0, 1);
        $task['output'] = substr($result, 1);
    }
    if ($output = dobot_deformat($task['output'])) {
        $output = dobot_reformat($output, "$task[indent]  ");
    }
    $markdown = [
        "$task[indent]- [$task[state]] $task[key]",
        ...$output
    ];

    $idx  = $task['idx']  ?? dobot_match($task['regex'])['idx'];
    $next = $task['next'] ?? $idx;

    $lines = dobot_file();
    array_splice($lines, $task['idx'], $next - $idx, $markdown);
    dobot_file($lines);

    return dobot_read_task($task['key']);
}

function dobot_trim_newlines($lines) {
    if ($lines && $lines = trim(implode("\n", $lines), "\n")) {
        return explode("\n", $lines);
    }
    return [];
}

function dobot_outdent($lines) {
    if (empty($lines)) return $lines;

    $depth = strlen($lines[0]) - strlen(ltrim($lines[0]));
    return array_map(function($line) use ($depth) {
        return substr($line, $depth);
    }, $lines);
}

function dobot_deformat($lines) {
    if (empty($lines)) return [];

    $lines = is_array($lines) ? $lines : explode("\n", (string)$lines);
    $lines = dobot_trim_newlines($lines);
    $depth = strlen($lines[0]) - strlen(ltrim($lines[0]));
    $lines = array_map(function($line) use ($depth) {
        return substr($line, $depth);
    }, $lines);
    $lines = dobot_trim_newlines($lines);
    return $lines;
}

function dobot_reformat($lines, $indent) {
    $lines = array_map( 
                fn($line) => trim($line) ? "$indent$line" : '',
                dobot_trim_newlines($lines)
            );

    return $lines ? ['', ...$lines, ''] : [];
}

function dobot_file($lines = null) {
    if (!$path = dobot_file_path("README.md", true)) {
        return [];
    }
    if ($lines) {
        file_put_contents($path, implode("\n", $lines));
    }

    return file_exists($path) ? explode("\n", file_get_contents($path)) : false;
}

function dobot_match_idx($pattern, $lines = null) {
    return array_key_first(dobot_matches($pattern, $lines));
}

function dobot_match($pattern, $lines = null) {
    foreach (dobot_matches($pattern, $lines) as $i => $m) {
        return $m + ['idx' => $i, 'regex' => $pattern];
    }
}

function dobot_matches($pattern, $lines = null) {
    $lines = $lines ?? dobot_file();
    return
        array_filter(
            array_map(
                function($line) use ($pattern) {
                    $m = [];
                    preg_match($pattern, $line, $m);
                    return $m;
                }, 
                $lines
            ));
    }

function dobot_file_path($path = "README.md", $create = false) {
    if (substr($path, 0, 1) == '/' && file_exists($path)) return $path;
    $paths = [
        '.', './tasks', realpath(__DIR__) . '/tasks'
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

function dobot_config($key, $val = null) {
    $key = preg_quote($key);
    if ($m = dobot_match("/$key\:[\s*|\*]*(.+)/")) {
        return trim($m[1]);
    }
}

function dobot_log($msg, $flags = FILE_APPEND) {
    $msg = date("c") . ": $msg\n";
    echo $msg;
    file_put_contents(dobot_file_path('dobot.log', true), $msg, $flags);
}

function dobot_sh($command = "echo 'dobot_sh() was called without a command'") {
    dobot_log("-----\n> $command\n");
    $out = `$command 2>&1`;
    dobot_log(`$command 2>&1`);
    return $out;
}

# if this script run directly from the command line.
if (realpath($argv[0]) == realpath(__FILE__)) {
    $action = $argv[1] ?? 'do';
    $readme = dobot_file_path();
    if ($action == 'do'  && !$readme) die("There's no README.md in this directory. Try `dobot new`\n");
    if ($action == 'new' &&  $readme) die("A README.md file exists this directory. Try `dobot do`\n");

    if (in_array($action, ['new', 'redo'])) {
        $path = dobot_file_path("README.md", true);
        $date = date("Y-m-d");
        $user = trim(dobot_sh('git config user.name'));
        $mail = trim(dobot_sh('git config user.email'));
        dobot_file([
"# Untitled DoBot Project

## About

|     Configuration | Value                  |
| ----------------: | :--------------------- |
|     **Git Repo:** |                        |
|      **Version:** | 0.0.1                  |

## TODO

- [x] Create README.md
- [-] Configure project

## Changelog

## Changelog

## [0.0.1] - $date

- **Added:** README.md file created automatically at $path for **$user**

## Contributors

- **🤖 DoBot:** <https://github.com/ronan/dobot>
- **$user:** <$mail>

"]);
    }
    else if ($action == 'do') {
        dobot_log("DoBot do", 0);
        foreach (dobot_matches('/^\- \[.?\] (.+)/') as $m) {
            dobot($m[1]);
        }
    }
}
