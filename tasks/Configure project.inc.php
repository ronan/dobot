#!/usr/local/bin/php
<?php
// $current_type = dobot_config("Project Type");
// dobot("Specify a project type", function () use ($current_type) {
//   $items = '';
//   foreach (['Custom', 'Pantheon', 'Platform.sh'] as $type) {
//     $type = $type == $current_type ? "**$type**" : $type;
//     $items .= "- $type\n";
//   }
//   if ($current_type && $current_type != "Select One") {
//     return "x**Project Type:**\n\n$items";
//   }
//   return "!> Please pick a **Project Type:**\n\n$items";
// });
// if ($current_type) {
//   dobot("Configure a $current_type project");
// }
// dobot("Configure a Custom project", function () {
//   $git_repo = dobot_config("Git Repo");
//   $out = $git_repo ? "x" : "!> Please fill out the following details:";
//   return $out . dobot_config_table(["Git Repo" => $git_repo]);
// }, ' ', false);

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
  return "!> Please pick a **Project Type**\n\n$items";
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