#!/bin/sh
set -e

# Dobot Shell Script
scriptdir="/workspace"
PATH="$scriptdir/bin/tasks:$scriptdir/bin/utils:$PWD/tasks:$PATH"

export DOBOT_FILE DOBOT_TASK DOBOT_PARENT DOBOT_VERBOSE DOBOT_PARENT DOBOT_STACK DOBOT_RUN

DOBOT_FILE='./DOBOT.md'
DOBOT_TASK=""

while getopts 'f::t::p::r::uva' c
do
  case $c in
    f)    DOBOT_FILE=$OPTARG ;;
    t)    DOBOT_TASK=$OPTARG ;;
    p)  DOBOT_PARENT=$OPTARG ;;
    r)     DOBOT_RUN=$OPTARG ;;
    u) DOBOT_UNCHECK=1       ;;
    v) DOBOT_VERBOSE=1       ;;
    # a)     DOBOT_ALL=1       ;;
    *) action_help
       exit 1 ;;
  esac
done

export  DOBOT_FILE_IN="$DOBOT_FILE"
export DOBOT_FILE_OUT="$DOBOT_FILE"

shift $((OPTIND-1))
ACTION=${1:-"help"}

err() {
  say "\n## ! $1"
  if [ -n "$2" ]; then
    usage=$(echo "$2" | sed 's/^/> /') 
    say "
Try:

$usage
"
  fi
  exit 1;
}

## Actions
action_do() {

  if [ -z "$DOBOT_TASK" ]; then err "No task specified!" "\
$0 -t 'Name of task' do   # do the named task
$0 -a do                  # do all unchecked tasks"
  fi

  status=$(taskstatus)

  if [ "$DOBOT_ACTION" = "get" ] && [ -z "$status" ]; then
    err "Could not find the task '$DOBOT_TASK' in '$DOBOT_FILE'";
  fi

  if [ "$DOBOT_UNCHECK" ]; then status=" "; fi
  
  if [ "$DOBOT_ACTION" = "do" ] && [ "$status" = 'x' ]; then
    err "## '$DOBOT_TASK' is already complete." "$0 -u -t '$DOBOT_TASK' do"
  fi

  case $ACTION in
    get) DOBOT_ACTION="get";  gettask ;;
    set) DOBOT_ACTION="set";  settask ;;
    do)  DOBOT_ACTION="do";   dotask  ;;
  esac

  debug "Done $DOBOT_ACTION-ing '$DOBOT_TASK'"
}

dotask() {
  debug "Running the task: '$DOBOT_TASK'"
  echo "." | settask
  tasktransput | DOBOT_PARENT="$DOBOT_TASK" "$DOBOT_TASK" | settask
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
        new) DOBOT_ACTION="new"; action_do  ;;
        do)  DOBOT_ACTION="do";  action_do   ;;
       get)  DOBOT_ACTION="get"; action_do ;;
       set)  DOBOT_ACTION="set"; action_do ;;
      #  add)  DOBOT_ACTION="add"; action_do ;;
      # redo)  DOBOT_ACTION="redo"; action_do ;;
      help) action_help ;;
       del) action_null ;;
        rm) action_null ;;
      list) action_null ;;
    archive) action_null ;;
esac