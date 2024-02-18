# General Notes

## Linux File/Text Utilities

### Busybox utilities

- awk
- cat
- comm
- cmp
- cp
- crypt
- cut
- date
- dd
- diff
- dirname
- dos2unix
- echo
- ed
- env
- envdir
- expand
- expr
- find
- fold
- getopt
- grep
- gunzip
- gzip
- head
- hexdump
- hostid
- httpd
- id
- inotifyd
- install
- length
- logger
- logname
- logread
- ls
- md5sum
- mkdir
- mknod
- mktemp
- mv
- nohup
- od
- patch
- printenv
- pwd
- rdate
- readlink
- realpath
- reset
- resize
- rm
- rmdir
- run-parts
- script
- sed
- seq
- sha1sum
- sha256sum
- sha512sum
- sort
- split
- strings
- sum
- svlogd
- sync
- tac
- tail
- tar
- tee
- test
- time
- timeout
- touch
- tr
- true
- tty
- uname
- uncompress
- unexpand
- uniq
- unix2dos
- unzip
- uudecode
- uuencode
- vi
- watch
- wc
- wget
- xargs
- yes
- zcat
-

## Tasks as executables

- [x] #! and <https://linux.die.net/man/2/execve>
- [x] Each todo should be an executable-like
  - [ ]  Recieves $argv with todo text as arg 0 and any optional inputs
  - Begin with #! where appropriate
- [x] Transput is piped in via STDIN and out via STDOUT
- [ ] Environment variables are used to pass values from parent to child
  - [ ] Eg: Current Parent, Task Stack, Task File.
    - This allows tasks to call 'dobot' wthout having to re-pass the original args
- [ ] Multiple todos can be combined into one file
  - [ ] Allow tasks to call 'dobot add'
  - Scripting Languages (php, python, js)
    - Script gets called in an environment where a lanuage specific 'todo' function is available
    - That env is probably a docker
  - [ ] Language specific helpers like PHP callback
    - [x] sh
      - Just use `$(dobot do)`
    - [~] PHP
    - [ ] JS
    - [ ] Python
    - Wherever possible the language-helpers are not magic
  - [-] MD file
    - Like a todo file but no checkmaks:
      - This is a sample todo with an `input`

        ```php
        // This code will be outdented to the level of the first non-blank line.

        // Arguments are sh style but maybe support func_get_args?
        $argv === {
          "This is a sample todo with an `input`",
          "input"
        }

        dobot('Test Second Level Todo', function() {
          dobot('Test Third Level Todo 1');
          dobot('Test Third Level Todo 2');
          dobot('Test Third Level Todo 3');
          dobot('Test Third Level Todo 4');
        });

        // Do we support both "output" and subtasks?
        return 'x > This is a simple output';
        ```

      - This is a second todo.

acpid, addgroup, adduser, adjtimex, ar, arp, arping, ash,
        awk, basename, beep, blkid, brctl, bunzip2, bzcat, bzip2, cal, cat,
        catv, chat, chattr, chgrp, chmod, chown, chpasswd, chpst, chroot,
        chrt, chvt, cksum, clear, cmp, comm, cp, cpio, crond, crontab,
        cryptpw, cut, date, dc, dd, deallocvt, delgroup, deluser, depmod,
        devmem, df, dhcprelay, diff, dirname, dmesg, dnsd, dnsdomainname,
        dos2unix, dpkg, du, dumpkmap, dumpleases, echo, ed, egrep, eject,
        env, envdir, envuidgid, expand, expr, fakeidentd, false, fbset,
        fbsplash, fdflush, fdformat, fdisk, fgrep, find, findfs, flash_lock,
        flash_unlock, fold, free, freeramdisk, fsck, fsck.minix, fsync,
        ftpd, ftpget, ftpput, fuser, getopt, getty, grep, gunzip, gzip, hd,
        hdparm, head, hexdump, hostid, hostname, httpd, hush, hwclock, id,
        ifconfig, ifdown, ifenslave, ifplugd, ifup, inetd, init, inotifyd,
        insmod, install, ionice, ip, ipaddr, ipcalc, ipcrm, ipcs, iplink,
        iproute, iprule, iptunnel, kbd_mode, kill, killall, killall5, klogd,
        last, length, less, linux32, linux64, linuxrc, ln, loadfont,
        loadkmap, logger, login, logname, logread, losetup, lpd, lpq, lpr,
        ls, lsattr, lsmod, lzmacat, lzop, lzopcat, makemime, man, md5sum,
        mdev, mesg, microcom, mkdir, mkdosfs, mkfifo, mkfs.minix, mkfs.vfat,
        mknod, mkpasswd, mkswap, mktemp, modprobe, more, mount, mountpoint,
        mt, mv, nameif, nc, netstat, nice, nmeter, nohup, nslookup, od,
        openvt, passwd, patch, pgrep, pidof, ping, ping6, pipe_progress,
        pivot_root, pkill, popmaildir, printenv, printf, ps, pscan, pwd,
        raidautorun, rdate, rdev, readlink, readprofile, realpath,
        reformime, renice, reset, resize, rm, rmdir, rmmod, route, rpm,
        rpm2cpio, rtcwake, run-parts, runlevel, runsv, runsvdir, rx, script,
        scriptreplay, sed, sendmail, seq, setarch, setconsole, setfont,
        setkeycodes, setlogcons, setsid, setuidgid, sh, sha1sum, sha256sum,
        sha512sum, showkey, slattach, sleep, softlimit, sort, split,
        start-stop-daemon, stat, strings, stty, su, sulogin, sum, sv,
        svlogd, swapoff, swapon, switch_root, sync, sysctl, syslogd, tac,
        tail, tar, taskset, tcpsvd, tee, telnet, telnetd, test, tftp, tftpd,
        time, timeout, top, touch, tr, traceroute, true, tty, ttysize,
        udhcpc, udhcpd, udpsvd, umount, uname, uncompress, unexpand, uniq,
        unix2dos, unlzma, unlzop, unzip, uptime, usleep, uudecode, uuencode,
        vconfig, vi, vlock, volname, watch, watchdog, wc, wget, which, who,
        whoami, xargs, yes, zcat, zcip
