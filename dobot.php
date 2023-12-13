#!/usr/local/bin/php
<?php

function dobot($key = null, $callback = null, $initial_state = ' ', $create_if_missing=true)
{
    static $stack = []; // The strategic static task stack.

    dobot_log("🤖  do: $key");

    $task = dobot_read_task($key);
    if (!$task && $create_if_missing && $p = dobot_read_task(end($stack))) {
        $task = dobot_write_task([
            'key'    => $key,
            'state'  => $initial_state,
            'indent' => $p['indent'] . '  ',
            'idx'    => $p['next'],
            'len'    => 0,
            'output' => '',
        ]);
    }
    if (!$task) return;

    $stack[] = $task['key'];
    if (!in_array($task['state'], ['x', '-'])) {
        dobot_execute($task, $callback);
    }
    array_pop($stack);

    dobot_log("🤖 end: $key");
}

function dobot_execute($task, $callback) {
    static $last = null;
    $last  = $task;
    $task['state'] = '.';
    $task  = dobot_write_task($task);
    if ($f = dobot_file_path("$task[key].inc.php")) {
        include($f);
    }
    $result = $callback ? call_user_func_array($callback, $task['args']) : ' ';
    $result = $result ? $result : (($last == $task) ? 'x' : '~');
    if (is_string($result)) {
        $state = substr($result, 0, 1);
        $task['state'] = $state == "\n" ? ' ' : $state;
        $task['output'] = explode("\n", substr($result, 1));
    }
    $task   = dobot_write_task($task);
}

function dobot_read_task($key)
{
    if (!$key) return;

    $regex = preg_replace("/`.+`/", "`(.*)`", preg_quote($key));
    $regex = "|(?<indent> *)\- +\[(?<state>.)?\] (?<key>$regex)|";

    $lines = dobot_file();
    if ($m = dobot_match($regex, $lines)) {
        unset($m[0], $m[1], $m[2], $m[3]);
        $task = array_intersect_key($m, array_flip(['indent', 'state', 'key', 'idx', 'regex']));
        $task['args'] = array_diff_key($m, $task, array_flip(range(0, 3)));
        $task['len'] = 1;
        
        $output = []; 
        $depth = strlen($task['indent']);
        while(key($lines) <= $task['idx']) next($lines);
        while (is_string($l = current($lines))) {
            if ($l && dobot_indent_depth($l) <= $depth) {
                prev($lines);
                break;
            }
            if (substr($l, $depth + 2, 3) == "- [") {
                while (empty($l) || dobot_indent_depth($l) > $depth) {
                    $l = next($lines);
                }
                break;
            }
            if ($l || $output) {
                $output[] = $l;
                $task['len'] = key($lines) - $task['idx'];
            }
            next($lines);
        }
        $task['next']   = key($lines);
        $task['len']    = count($output) + 1;
        $task['output'] = dobot_reformat($output);
        return $task;
    }
}

function dobot_write_task($task, $result = null)
{
    $markdown = [
        "$task[indent]- [$task[state]] $task[key]",
        ...dobot_reformat($task['output'], "$task[indent]  ")
    ];

    $lines = dobot_file();
    array_splice($lines, $task['idx'], $task['len'], $markdown);
    dobot_file($lines);

    return dobot_read_task($task['key']);
}

function dobot_trim_newlines($lines) {
    if ($lines && $lines = trim(implode("\n", $lines), "\n")) {
        return explode("\n", $lines);
    }
    return [];
}

function dobot_indent_depth($line) {
    if (is_array($line)) $line = reset($line);
    return strlen($line) - strlen(ltrim($line, " "));
}

function dobot_reformat($lines, $indent="") {
    if (empty($lines)) return [];

    $lines = dobot_trim_newlines($lines);
    $depth = dobot_indent_depth($lines);
    $lines = array_map(
        function($line) use ($depth, $indent) {
            $line = trim(substr($line, $depth));
            return $line ? "$indent$line" : '';
        },
        $lines
    );

    return $lines ? ['', ...$lines, ''] : [];
}

function dobot_file($lines = null) {
    if (!$path = dobot_file_path("README.md", true)) {
        return [];
    }
    if ($lines) {
        $file = preg_replace("/\n\n+/", "\n\n", implode("\n", $lines));
        file_put_contents($path, $file);
    }

    return file_exists($path) ? explode("\n", file_get_contents($path)) : false;
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

function dobot_config($key, $default = null) {
    $lines = [implode('', dobot_file())];
    $key = preg_quote("**$key:**");
    $sep = "(`|\*\*)";
    if ($m = dobot_match("/$key.*?$sep([^$sep]*)$sep/", $lines)) {
        return trim($m[2], "` \n");
    }
    return $default;
}

function dobot_config_table($values) {
    $key_length = strlen('Configuration');
    $val_length = strlen('Value');
    $table = [];
    foreach ($values as $key => $val) {
        $table["**$key:**"] = "`$val`";
        $key_length = max($key_length, strlen("**$key:**"));
        $val_length = max($key_length, strlen("`$val`"));
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

    $user = trim(dobot_sh('git config user.name'));
    $mail = trim(dobot_sh('git config user.email'));

    if (in_array($action, ['new', 'redo'])) {
        $path = dobot_file_path("README.md", true);
        $date = date("Y-m-d");
        dobot_file([
"# Untitled DoBot Project

## About

**Version:** `0.0.1`

## TODO

- [x] Create README.md
- [ ] Configure project

## Changelog

## [0.0.1] - $date

- **Added:** README.md file created automatically at $path for **$user**

## Contributors

- **🤖 DoBot:** <https://github.com/ronan/dobot>
- **$user:** <$mail>

"]);
    }
    else if ($action == 'do') {
        dobot_log("DoBot do run by $user", 0);
        foreach (dobot_matches('/^\- \[.?\] (.+)/') as $m) {
            dobot($m[1]);
        }
    }
}

# Built-in tasks
dobot("Configure project", function () {
    $current_type = dobot_config("Project Type");
    dobot("Specify a project type", function () use ($current_type) {
        $items = '';
        foreach (['Custom', 'Pantheon', 'Platform.sh'] as $type) {
            $type = $type == $current_type ? "**$type**" : $type;
            $items .= "- $type\n";
        }
        if ($current_type && $current_type != "Select One") {
            return "x**Project Type:**\n\n$items";
        }
        return "!> Please pick a **Project Type:**\n\n$items";
    });
    if ($current_type == 'Pantheon') {        
        dobot("Configure a *Pantheon* project", function () {
            $site_id = dobot_config("Pantheon Site ID");
            $site_env = dobot_config("Pantheon Site ID", "dev");
            $out = ($site_id && $site_env) ? "" : "!> Please fill out the following details:";
            return $out . dobot_config_table([
                "Pantheon Site ID"      => $site_id,
                "Pantheon Environment"  => $site_env
            ]);
        });
    }
    if ($current_type == 'Custom') {
        dobot("Configure a *Custom* project", function () {
            $git_repo = dobot_config("Git Repo");
            $out = $git_repo ? "x" : "!> Please fill out the following details:";
            return $out . dobot_config_table(["Git Repo" => $git_repo]);
        });
    }
});