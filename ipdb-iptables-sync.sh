#!/bin/bash
set -Ceu

IPTABLES_CHAIN_NAME="ipdb-sync"
IPDB_FEED_URL="https://your.ipdb.host.invalid/atk/fgfeed.php?range=net"
IPTABLES_ACTION="DROP"

ipdb_feed_list=`curl -fsSL "$IPDB_FEED_URL"`

set +e
iptables -n --list "$IPTABLES_CHAIN_NAME" 2>&1 > /dev/null
set -e
iptables_chain_exists=$?
if [ $iptables_chain_exists -ne 0 ]; then
        iptables -N "$IPTABLES_CHAIN_NAME" > /dev/null
else
        iptables -F "$IPTABLES_CHAIN_NAME" > /dev/null
fi;

while read -r ip_entry; do
        if [[ $ip_entry == \#* ]]; then
                continue
        fi;
        iptables -A "$IPTABLES_CHAIN_NAME" -s "$ip_entry" -j "$IPTABLES_ACTION"
done < <(printf '%s\n' "$ipdb_feed_list")

set +e
iptables -D INPUT -j "$IPTABLES_CHAIN_NAME" 2>&1 > /dev/null
iptables -I INPUT 1 -j "$IPTABLES_CHAIN_NAME" 2>&1 > /dev/null
set -e
