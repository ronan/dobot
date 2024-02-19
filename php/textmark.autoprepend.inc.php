#!/usr/local/bin/php
<?php

require "textmark.inc.php";

function textmark($key = null, $callback = null)
{
    textmark_log("🤖 do: $key");

    textmark_sh("echo '.' | textmark -t \"$key\" set");

    $args = [];

    textmark_log("TEXTMARK_PARENT: " . getenv('TEXTMARK_PARENT'));

    $transput = '!';
    if ($callback) {
        $parent = getenv("TEXTMARK_PARENT");
        putenv("TEXTMARK_PARENT=$key");
        $transput = call_user_func_array($callback, $args) ?? "x";
        putenv("TEXTMARK_PARENT=$parent");
    }
    $transput = escapeshellarg($transput);
    textmark_sh("echo $transput | textmark -t \"$key\" set");
    textmark_log($transput);
    textmark_log("🤖 end: $key");
}
