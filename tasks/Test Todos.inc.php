#!/usr/local/bin/php
<?php

dobot('Create README.md', function() {
  return '-';
});

// dobot('Test a todo with an output', function() {
//   return 'x > This is a simple output';
// });xzxz

dobot('Test Second Level Todo', function() {
  dobot('Test Third Level Todo');
});