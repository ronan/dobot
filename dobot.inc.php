#!/usr/local/bin/php
<?php

require "dobot.php";

function dobot($key = null, $callback = null)
{
    dobot_log("🤖 do: $key");

    dobot_sh("echo '.' | dobot -t \"$key\" set");

    $args = [];

    dobot_log("DOBOT_PARENT: " . getenv('DOBOT_PARENT'));

    $transput = '!';
    if ($callback) {
        $parent = getenv("DOBOT_PARENT");
        putenv("DOBOT_PARENT=$key");
        $result = call_user_func_array($callback, $args) ?? "!";
        $transput = escapeshellarg($result);
        putenv("DOBOT_PARENT=$parent");
    }
    dobot_sh("echo $transput | dobot -t \"$key\" set");

    dobot_log($transput);
    dobot_log("🤖 end: $key");
}
