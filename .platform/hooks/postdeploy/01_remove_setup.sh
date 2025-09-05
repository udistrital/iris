#!/bin/bash

SETUP_DIR="/var/app/current/setup"

echo "===== Running postdeploy: Remove osTicket setup folder =====" >> /tmp/postdeploy.log

if [ -d "$SETUP_DIR" ]; then
    echo "Setup directory found. Removing..." >> /tmp/postdeploy.log
    rm -rf "$SETUP_DIR"
    echo "Setup directory removed." >> /tmp/postdeploy.log
else
    echo "Setup directory does not exist. Nothing to do." >> /tmp/postdeploy.log
fi

echo "===== Postdeploy script finished =====" >> /tmp/postdeploy.log
