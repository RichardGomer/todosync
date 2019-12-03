#!/bin/bash
# A script to start the sync job; safe to call regularly from CRON, because it
# will just exit if the command's already running

cd "$(dirname "$0")" # Change to our parent directory

if ! pgrep -u "$(whoami)" -f "php sync.php" # Check sync isn't already running
then
    php sync.php > sync.log &
fi
