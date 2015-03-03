#!/bin/bash

function internal_realpath {
    [[ $1 = /* ]] && echo "$1" || echo "$PWD/${1#./}"
}

realpath=$(type -p realpath)
if [ -z "$realpath" ]; then
    SCRIPT=$(internal_realpath $0)
else
    SCRIPT=$($realpath $0)
fi

REGRESSDIR=${SCRIPT/%\/example.sh/}
export REGRESSDIR

if [ -z "$REGRESSDIR" -o \! -d "$REGRESSDIR" ]; then
    echo "Cannot detect the directory from which this script is being run." >&2
    exit 1
fi

mkdir -p $REGRESSDIR/var/log
mkdir -p $REGRESSDIR/var/run/orders

configfile=$REGRESSDIR/apache/apache_24_linux.conf

apachebin=$(type -p apache2)
if [ -z "$apachebin" ]; then
    apachebin=$(type -p httpd)
fi
if [ -z "$apachebin" ]; then
    echo "Unable to detect apache binary to use" >&2
    exit 1;
fi
apacheversion=$($apachebin -v | grep 'Server version:' | cut -f2 -d/ | cut -f1-2 -d.)

case $(uname) in
    Darwin)
        osname=osx
        MODULEROOT=libexec/apache2
        ;;
    Linux)
        osname=linux
        MODULEROOT=/usr/lib/apache2/modules
        ;;
    *)
        osname=unknown
        ;;
esac

export MODULEROOT

configfile=$REGRESSDIR/apache/apache_${apacheversion}_${osname}.conf
if [ ! -f "$configfile" ]; then
    echo "Cannot find configuration file for apache v${apacheversion} running on ${osname} ($configfile)" >&2
    exit 1;
fi

cmd=$1
case "$cmd" in 
    start|stop|reload|graceful|restart)
        if [ $cmd = 'reload' ]; then
            cmd='graceful'
        fi
        $apachebin -f "$configfile" -k $cmd
        exitcode=$?

        if [ $cmd = 'start' -a $exitcode -eq 0 ]; then
            port=$(egrep ^Listen "$configfile" | awk '{print $2}')
            echo "Server listening on port $port"
        fi
        ;;
    *)
        echo "$0 start|stop|reload|restart" >&2
        exit 1;
        ;;
esac;

