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
         add) action_add "$@" ;;
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
  t=$1; s=$2
  echo "- [$s] $t\n" | sed -r -e 's/`.*`/`(.*)`/' -e 's/[\[&/\\]/\\&/g'
  #                               |                   |
  #                               |                 s/[\[&/\\]/\\&/g-> Add a '\' before '[', '/', '\'
  #                               s/`.*`/`(.*)`/---------------------> Replace `XXX` with `.*`
}

task_indent() {
  t=$1
  read_task_file | sed -rn "/$t/ s/^(\s*).*$/\1/g p" | tr -d '\n' | wc -c
  #                         |
  #                         s/^(\s*).*$/\1/g-------------------------> Replace the text with any leading spaces.
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
  n=$(task_template "$t" "$d" "$o")

  [ "$d" -ne 0 ] && p=" {$d}$p"

  # debug "p:$p\nt:$t\nd:$d\no:$o\nn:$n'"

  read_task_file | sed -r "\
    /^## TODO/,/^#/ {
      /^$p$/,/^ *- \[/ {
        # Append the task with the new content
        /^$p/a\\$n
        # Delete the original line
        /^$p/d
        # If the line has a todo then break
        /^ *- \[/b
        # Delete the output
        d
      }
    }" | write_task_file
}

read_task() {
  p="$1"; d="$2";
  [ "$d" -ne 0 ] && p=" {$d}$p"
  # debug "p:$p\nd:$d\n"

  read_task_file | sed -rn "\
      /^## TODO/,/^#/ {
        /^$p/,/^ {0,$d}-/ {
          /^ {0,}- \[/ {
            /$p/! b
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
  if [ $DOBOT_VERBOSE ]; then say "DEBUG:" "$@"; fi 
}

say() {
  echo "$@" >&2
}

err() {
  say "$@"
  exit 1
}
 
## Actions

action_add() {
  t=${*:-$DOBOT_TASK}
  g="^$"
  d=0

  if [ -n "$DOBOT_PARENT" ]; then
    g=$(task_pattern "$DOBOT_PARENT" ".")
    d=$(task_indent  "$g")
  fi

  n=$(task_template "$t" $((d + 2)) ' ')
  debug "t:$t\nd:$d\ng:$g\n"

  # Todo: Figure out how to add to the root without leaving a space in the wrong place
  read_task_file | sed -r "$(printf '
    /^## TODO/,/^#/ {
      /%s$/,/^ {0,%d}- \[/ {
        /%s/b
        /^$/b
        /^ {%d}/b
        i\\%s
      }
    }' "$g" "$d" "$g" "$d" "$n")" | write_task_file
}

action_do() {
  p=$(task_pattern "$DOBOT_TASK" ".")
  d=$(task_indent  "$p")
  c=$(read_task    "$p" "$d")

  if [ -z "$c" ]; then err "Could not find the task '$DOBOT_TASK'"; fi

  # debug "t:$DOBOT_TASK\np:$p\nd:$d\nc:$c\n"

  write_task "$p" "$DOBOT_TASK" "$d" "."

  # Run the task
  result="! > Hello, World!"

  write_task "$p" "$DOBOT_TASK" "$d" "$result"
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
