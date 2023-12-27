#!/bin/sh
set -e

# Dobot Shell Script

scriptdir=$(CDPATH=' ' cd -- "$(dirname -- "$0")" && pwd)
PATH="$scriptdir/bin/tasks:$scriptdir/bin/utils:$PWD/tasks:$PATH"

export DOBOT_FILE DOBOT_TASK DOBOT_PARENT DOBOT_VERBOSE DOBOT_PARENT DOBOT_STACK

DOBOT_FILE='./README.md'
DOBOT_TASK=""

while getopts 'f::t::p::uva' c
do
  case $c in
    f)    DOBOT_FILE=$OPTARG ;;
    t)    DOBOT_TASK=$OPTARG ;;
    p)  DOBOT_PARENT=$OPTARG ;;
    u) DOBOT_UNCHECK=1       ;;
    v) DOBOT_VERBOSE=1       ;;
    a)     DOBOT_ALL=1       ;;
    *) action_help
       exit 1 ;;
  esac
done

export  DOBOT_FILE_IN="$DOBOT_FILE"
export DOBOT_FILE_OUT="$DOBOT_FILE.out"

shift $((OPTIND-1))
ACTION=${1:-"help"}


## Actions

action_add() {
  set "$1"
  task=$(echo "$1" | quotetask)
  if [ -n "$2" ]; then 
    parent=$(echo "$2" | quotetask)
    indent=$(readtasks | sed -rn "/$parent/ s/^(\s*).*$/\1/g p" | tr -d '\n')
  else
    parent="$(readtasks | sed -n '/\#\# TODO/,/^\#/ { /^-/p }' | tail -n1 | quote_task)"
    indent=""
  fi

  debug "
  Adding the task: '$indent- \[ ] $task'
            after: '$parent'"

  readtasks |
      sed -re "/$indent- \[.] $parent/{:a;n;/^$indent /ba;i\\$indent- \[ ] $task" -e '}' |
      writetasks
}

action_do() {
  parse_task_pattern="^( *)- \[(.?)] (.*)$";

  if [ "$DOBOT_ALL" ]; then
    :
    # pattern="^- \["
    # DOBOT_ALL=0
    # while 
    #   line=$(readtasks | sed -rn "/$pattern/p") &&
    #   task=$(echo "$line" | sed -nr "s/$pattern/\3/p")
    # do
    #   DOBOT_TASK="$task";
    #   action_do
    # done
    # exit
  fi

  if [ -z "$DOBOT_TASK" ]; then err "\
## No task specified!
  
  > Try:
  > $0 -t 'Name of task' do
  > or
  > $0 -a do
  > To run all unchecked tasks
"
  fi

  find_task_pattern=$(echo "$DOBOT_TASK" | quotetask)  
  line=$(readtasks | sed -rn "/$find_task_pattern/p")

  if [ -z "$line" ]; then err "Could not find the task '$DOBOT_TASK' in '$DOBOT_FILE'"; fi

   indent=$(echo "$line" | sed -nr "s/$parse_task_pattern/\1/p")
   status=$(echo "$line" | sed -nr "s/$parse_task_pattern/\2/p")
     task=$(echo "$line" | sed -nr "s/$parse_task_pattern/\3/p")
  pattern=$(echo "$indent- [$status] $task" | quotetask)

  if [ "$status" = 'x' ] && [ "$DOBOT_UNCHECK" != 1 ]; then 
    debug "Skipping the task: '$pattern'"
    err "The task '$task' is already complete. 

    Try '$0 -u -t '$task' do"
  fi

  transput=$(readtasks | sed -rn "/^$pattern$/,/- \[/ { //b; p }")
  readtasks | sed -r "/$pattern/ s/\[.]/\[.]/" | writetasks

  # Run the task
  debug "Running the task: '$task'"
  transput=$(echo "$transput" | "$task")
  debug "Completed with output: \n'$transput'"

  status="${transput%% *}"
  output="${transput#? }"
  if [ ${#status}  != 1 ]; then status="x"; fi
  if [ ${#output} -gt 1 ]; then
    output=$(echo "$output" | sed "s/^/$indent  /")
    transput=$(printf "\n\n%s\n\n" "$output" | awk -v ORS= '{print sep $0; sep="\\n"}')
  fi
  
  readtasks |
    sed -r "/$pattern/,/- \[/ { //b; d }"                        |
    sed -r "/$pattern/ { s/- \[./- \[$status/; s/$/$transput/ }" |
    writetasks
}

action_null() {
  say "This action is not yet implemented"
}

action_new() {
  if [ -f "$DOBOT_FILE" ]; then
    echo "The file '$DOBOT_FILE' already exists. Try 'dobot redo' to start over."
    exit 1
  fi
  echo "\
### Untitled DoBot Project

## TODO

- [x] Create TODO list

## Contributors

- **🤖 DoBot:** <https://github.com/ronan/dobot>
" > "$DOBOT_FILE"
}

action_redo() {
  if [ -f "$DOBOT_FILE" ]; then
    debug "Deleting '$DOBOT_FILE'"
    rm "$DOBOT_FILE"
  fi

  action_new
}

action_help() {
  echo "\
# Dobot

Usage: $0 [ -f README.md ] [ -t DOBOT_TASK ] [ -p DOBOT_PARENT ] action

## Actions:

do
: Run a task and it's sub-tasks.

new
: Create a new task list.

redo
: Recreate a task list. The existing file will be deleted.

help
: Show this help.

### Coming soon
- del / rm
- list
- archive

## Options
-f *filename*
: The file to read and write to. 
: If a file path is not specified then dobot uses './README.md'

-v
: Verbose output

-t *pattern*
: Run all tasks that match the given *pattern*

-p *pattern*
: Restrict action to subtasks of tasks matching the given *pattern*

"; 
  exit 0;
}

debug "
## Initial Values:

     Key: | Value    
--------: | :-----
    PATH: | $PATH
     cmd: | $0
    args: | $*
     pwd: | $PWD
    file: | $DOBOT_FILE
    task: | $DOBOT_TASK
  parent: | $DOBOT_PARENT
 verbose: | $DOBOT_VERBOSE
  action: | $ACTION
"

case $ACTION in
        add) action_add "$DOBOT_TASK" "$DOBOT_PARENT" ;;
        do) action_do   ;;
        new) action_new  ;;
      redo) action_redo ;;
      help) action_help ;;
        del) action_null ;;
        rm) action_null ;;
      list) action_null ;;
    archive) action_null ;;
esac