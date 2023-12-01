#!/usr/local/bin/php
<?php

dobot("Specify a project type", function () {
  $current_type = dobot_config("Project Type");
  $items = '';
  foreach (['Custom', 'Pantheon', 'Platform.sh'] as $type) {
    $s = $type == $current_type ? 'x' : ' ';
    $items .= "  - ($s) $type\n";
  }
  if ($current_type) {
    dobot("Configure a *$current_type* project");
    return;
  }
  return "!> Please pick a **Project Type**\n\n$items\n\n";
});

dobot("Configure a *Pantheon* project", function () {
    return "!> Please fill out the form:

    |               Config item | Value                  
    | ------------------------: | :---------------------
    | **Pantheon Site ID:**     | 
    | **Pantheon Environment:** | 
    ";
});

dobot("Configure a *Custom* project", function () {
  return '!> You must pick a **Project Type**';
});