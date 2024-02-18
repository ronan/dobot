#!/bin/bash

dobot -p "$DOBOT_PARENT" -t "This is a task" add
dobot -p "This is a task" -t "This is a subtask" add

echo "x > Hello, world! I'm testing the todos"
debug "This is a debug statement"

dobot -t "Generate backstop reference images" -e <<<EOF
  #!/bin/sh
  docker exec -it backstop backstop reference
EOF;
