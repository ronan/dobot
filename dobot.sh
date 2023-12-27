#!/bin/sh
set -e

export DOBOT_FILE DOBOT_TASK DOBOT_PARENT DOBOT_VERBOSE DOBOT_PARENT

DOBOT_FILE='./README.md'
DOBOT_TASK=".*"

while getopts 'f::t::p::v' c
do
  case $c in
    f)    DOBOT_FILE=$OPTARG ;;
    t)    DOBOT_TASK=$OPTARG ;;
    p)  DOBOT_PARENT=$OPTARG ;;
    v) DOBOT_VERBOSE=1       ;;
    *) action_help
       exit 1 ;;
  esac
done

DOBOT_FILE_IN=$DOBOT_FILE
DOBOT_FILE_OUT="$DOBOT_FILE.out"

shift $((OPTIND-1))
ACTION=${1:-"help"}

main() {
  case $ACTION in
          do) action_do ;;
         add) action_add "$DOBOT_TASK" "$DOBOT_PARENT" ;;
          ls) action_ls  "$@" ;;
         new) action_new  ;;
        redo) action_redo ;;
        help) action_help ;;
         del) action_null ;;
          rm) action_null ;;
        list) action_null ;;
     archive) action_null ;;
  esac
}

task_pattern() {
  t=$1; s=$2; d=${3:-0}
  # shellcheck disable=SC3045
  # shellcheck disable=SC2016
  printf "%*s\n" "$d" "- [$s] $t" 
    sed -r -e 's/`.*`/`(.*)`/' | sed -r 's/[\[&/\\]/\\&/g;'
  #            |                         |
  #            |                         s/[\[&/\\]/\\&/g; ----------> Escape [, &, \ and ] 
  #            s/`.*`/`(.*)`/----------------------------------------> Replace `XXX` with `.*`
}

task_indent() {
  t=$1

  read_task_file | sed -rn "/$t/ s/^(\s*).*$/\1/g p" | tr -d '\n' | wc -c
  #                        s/^(\s*).*$/\1/g-------------------------> Replace the text with any leading spaces.
}

task_template() {
  t=$1; d=$2; transput=$3;
  indent=$(printf '%*s' "$d" "")
  
  s="${transput% *}"
  o="${transput#? }"
  if [ ${#s}  != 1 ]; then s="x"; fi
  if [ ${#o} -gt 1 ]; then o=$(printf "\n\n%s\n\n" "$o" | sed "s/^/$indent  /"); fi
  
  echo "$indent$(task_pattern "$t" "$s")$o" | sed ':a;N;$!ba; s/\n/\\&/g'
}

write_task() {
  p="$1"; t="$2" d="$3"; o="$4";
  new=$(task_template "$t" "$d" "$o")

  [ "$d" -ne 0 ] && task=" {$d}$p"

  read_task_file | sed -r "\
    /^## TODO/,/^#/ {
      /^$task$/,/^ *- \[/ {
        # Append the task with the new content
        /^$task/a\\$new
        # Delete the original line
        /^$task/d
        # If the line has a todo then break
        /^ *- \[/b
        # Delete the output
        d
      }
    }" | write_task_file
}

read_task() {
  p="$1"; d="$2";

  [ "$d" -ne 0 ] && task=" {$d}$p"
  debug "p:$p\nd:$d\n"

  read_task_file | sed -rn "\
      /^## TODO/,/^#/ {
        /^$task/,/- \[/ {
          /^ {0,}- \[/ {
            /$task/! b
          }
          p
        }}"
}

read_task_file() {
  cat "$DOBOT_FILE_IN"
}

write_task_file() {
  tmp="$DOBOT_FILE.$$"
  cat - > "$tmp";
  if [ -f "$tmp" ]; then
    mv "$tmp" "$DOBOT_FILE_OUT"
    return
  fi
}

debug() { 
  if [ "$DOBOT_VERBOSE" ]; then say "DEBUG:" "$@"; fi 
}

say() {
  echo "$@" >&2
}

err() {
  say "$@"
  exit 1
}
 
## Actions

quote_task() {
  sed -r -e 's/`.*`/`(.*)`/' | sed -r 's/[\[&/\\]/\\&/g;'
}

action_add() {
  set "$1"
  task=$(echo "$1" | quote_task)
  if [ -n "$2" ]; then 
    parent=$(echo "$2" | quote_task)
    indent=$(read_task_file | sed -rn "/$parent/ s/^(\s*).*$/\1/g p" | tr -d '\n')
  else
    parent="$(read_task_file | sed -n '/\#\# TODO/,/^\#/ { /^-/p }' | tail -n1 | quote_task)"
    indent=""
  fi

  debug "
  Adding the task: '$indent- \[ ] $task'
            after: '$parent'"

  read_task_file | 
      sed -re "/$indent- \[.] $parent/{:a;n;/^$indent /ba;i\\$indent- \[ ] $task" -e '}'\
    | write_task_file
}

action_do() {
  pattern=$(echo "$DOBOT_TASK" | quote_task) || err "No task specified"
     line=$(read_task_file | sed -rn "/$pattern/p")

  pattern="^( *)- \[(.?)] (.*)$";
   indent=$(echo "$line" | sed -nr "s/$pattern/\1/p")
   status=$(echo "$line" | sed -nr "s/$pattern/\2/p")
     task=$(echo "$line" | sed -nr "s/$pattern/\3/p" | quote_task)
  
  if [ -z "$task" ]; then err "Could not find the task '$DOBOT_TASK'"; fi

  read_task_file | sed -r "s/$indent- \[.] $task/$indent- \[.] $task/" | write_task_file

  # Run the task
  transput="\
! > Hello, World 
> again!"

  status="${transput%% *}"
  output="${transput#? }"
  if [ ${#status}  != 1 ]; then status="x"; fi
  if [ ${#output} -gt 1 ]; then
    output=$(echo "$output" | sed "s/^/$indent  /")
    output=$(printf "\n\n%s\n\n" "$output" | awk -v ORS= '{print sep $0; sep="\\n"}')
  fi  
  read_task_file | sed -r "s/$indent- \[.] $task/$indent- \[$status] $task$output/" | write_task_file
}

action_ls() {
  t=$DOBOT_TASK
  p=$(task_pattern "$t" ".")
  d=$(task_indent  "$p")
  # debug "t:$t\np:$p\nd:$d\n"
  read_task "$p" "$d"
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
- add
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
    file: | $DOBOT_FILE
    task: | $DOBOT_TASK
  parent: | $DOBOT_PARENT
 verbose: | $DOBOT_VERBOSE
  action: | $ACTION
"

main "$@"
