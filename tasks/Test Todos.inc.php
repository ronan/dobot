#!/usr/local/bin/php
<?php


dobot('Test a todo with an output', function () {
  return 'x > This is a simple output';
});

dobot('Test Second Level Todo', function() {
  dobot('Test Third Level Todo 1');
  dobot('Test Third Level Todo 2');
  dobot('Test Third Level Todo 3');
  dobot('Test Third Level Todo 4');
});