#!/bin/sh

unset PARENT VERBOSE
FILE='./README.md'
TASK=".*"

while getopts 'd::f::t::p::v' c
do
  case $c in
    f)    FILE=$OPTARG ;;
    t)    TASK=$OPTARG ;;
    p)  PARENT=$OPTARG ;;
    v) VERBOSE=1       ;;
  esac
done

FILE_IN=$FILE
FILE_OUT="$FILE.out"

shift $((OPTIND-1))
ACTION=${1:-"help"}

main() {
  case $ACTION in
        help) action_help ;;
          do) action_do   ;;
         new) action_new  ;;
        redo) action_redo ;;
         add) action_add  ;;
          ls) action_ls   ;;
         del) action_null ;;
          rm) action_null ;;
        list) action_null ;;
     archive) action_null ;;
  esac
}

task_pattern() {
  local t=$1; local s=$2
  echo "- [$s] $t\n" | sed -r -e 's/`.*`/`(.*)`/' -e 's/[\[&/\\]/\\&/g'
  #                               |                   |
  #                               |                 s/[\[&/\\]/\\&/g-> Add a '\' before '[', '/', '\'
  #                               s/`.*`/`(.*)`/---------------------> Replace `XXX` with `.*`
}

task_indent() {
  local t=$1
  read_task_file | sed -rn "/$t/ s/^(\s*).*$/\1/g p" | tr -d '\n' | wc -c
  #                         |
  #                         s/^(\s*).*$/\1/g-------------------------> Replace the text with any leading spaces.
}

task_template() {
  local t=$1; local d=$2 local o=$3;
  debug "task_template t:$t\nd:$d\no:'$o'\n"

  # If the first line starts with a single character that's the status.
  # Otherwise the status is 'x' (done)
  s=$(echo "$o" | sed -nr '/^(.)/ s/^(.).*$/\1/p') || 'x'
  #                        |         |
  #                        /^(.)/ -- | ------------------------------> Match any line beginging with any character and then a space.
  #                               s/^(.).*$/\1/p --------------------> Replace the line with the first character and print it.

  debug "t:$t, d:$d, o:'$o', s:'$s'"

  o=$(echo "$o" | sed -r  "/^(.)$/d; /^(.) / s/^. ?//; s/^/$i  /")
  #                            |        |       |         |
  #                        /^(.)$/d; -- | ----- | ------- | ---------> If the line is just one character delete it.
  #                                  /^(.) / -- | ------- | ---------> Find lines beginning with a character and then a space.
  #                                          s/^. ?//; -- | ---------> Remove the first character and trailing space.
  #                                                    s/^/$i  /-----> Indent 2 spaces deeper than the task itself.

  if [ ! -z "$o" ]; then
    o=$(echo "\n\n$o\n" | sed ':a;N;$!ba; s/\n/\\&/g' );
    #                            |           |
    #                          :a;N;$!ba; -- | ----------------------> Append the line to the pattern space and repeat until te last line.
    #                                     s/\n/\\&/g ----------------> Add a backslash before every newline to escape for sed.
  fi

  printf '%*s' $d
  echo "$(task_pattern "$t" "$s")$o\\n"
}


run_task() {
  local p=$1
  echo "! > Hello, World!";
}

write_task() {
  local p=$1; local t=$2 local d=$3; local o=$4;
  local n=$(task_template "$t" "$d" "$o")
  local tmp="$FILE.$random"

  [ $d -ne 0 ] && p=" {$d}$p"

  debug "p:$p\nt:$t\nd:$d\no:$o\nn:$n'"

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
  local p="$1"; local d="$2";
  [ $d -ne 0 ] && p=" {$d}$p"

  read_tasks | sed -rn "
      /^$p/,/^ {0,$d}-/ {
        /^ {0,}- \[/ {
          /$p/! b
        }
        p
      }"
}

read_tasks() {
  read_task_file | sed -rn "/^## TODO/,/^#/ p"
}

read_task_file() {
  cat $FILE_IN
}

write_task_file() {
  local tmp="$FILE.$random"
  cat - > $tmp;
  if [ -f $tmp ]; then
    mv $tmp $FILE_OUT
    return
  fi

}

debug() { 
  if [ $VERBOSE ]; then say "DEBUG:$@"; fi 
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
  local t=${@:-$TASK}
  local g="^$"
  local d=0


  if [ ! -z "$PARENT" ]; then
    g=$(task_pattern "$PARENT" ".")
    d=$(task_indent  "$g")
  fi

  local n=$(task_template "$t" $(($d + 2)) ' ')
  debug "t:$t\nd:$d\ng:$g\n'$i'"

  local tmp="$FILE.$random"
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
  local t=${@:-$TASK}
  local p=$(task_pattern "$t" ".")
  local d=$(task_indent  "$p")
  local c=$(read_task    "$p" "$d")

  debug "t:$t\np:$p\nd:$d\nc:$c\n"

  if [ -z "$current" ]; then err "Could not find the task '$TASK'"; fi

  write_task "$p" "$t" "$d" "."
  local result=$(run_task $p)
  write_task "$p" "$t" "$d" "$result"
}

action_ls() {
  p=$(task_pattern "${@:-$TASK}" ".")
  d=$(task_indent  "$p")

  read_task "$p" "$d"
}

action_null() {
  say "This action is not yet implemented"
}

action_new() {
  if [ -f $FILE ]; then
    echo "The file '$FILE' already exists. Try 'dobot redo' to start over."
    exit 1
  fi
  echo "\
### Untitled DoBot Project

## TODO

## Contributors
- **🤖 DoBot:** <https://github.com/ronan/dobot>
" > $FILE
}

action_help() {
  echo "\
# Dobot

Usage: $0 [ -f README.md ] [ -t TASK ] [ -p PARENT ] action

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
    file: | $FILE
    task: | $TASK
  action: | $ACTION
  parent: | $PARENT
 verbose: | $VERBOSE
"

main "$@"
