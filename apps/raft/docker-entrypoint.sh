#!/bin/sh
set -e

mkdir -p /data

# Accessing the folder of data to write data on that
chown -R raft:raft /data

ARGS=""

ARGS="$ARGS --node-id=$RAFT_NODE_ID"
ARGS="$ARGS --bind-address=0.0.0.0"
ARGS="$ARGS --advertise-address=$RAFT_ADV_ADDR"
ARGS="$ARGS --raft-port=7000"
ARGS="$ARGS --http-port=8000"
ARGS="$ARGS --data-dir=/data"

if [ -n "${RAFT_NOTIFY_URL:-}" ]; then
  ARGS="$ARGS --notify-url=$RAFT_NOTIFY_URL"
fi
if [ -n "${RAFT_NOTIFY_SECRET:-}" ]; then
  ARGS="$ARGS --notify-secret=$RAFT_NOTIFY_SECRET"
fi
if [ -n "${RAFT_NOTIFY_HEADER:-}" ]; then
  ARGS="$ARGS --notify-header=$RAFT_NOTIFY_HEADER"
fi

ARGS="$ARGS $RAFT_START_MODE"

exec gosu raft /app/skylogs-raft $ARGS
