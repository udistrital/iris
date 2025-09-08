#!/bin/bash

CONFIG_FILE="/var/app/current/include/ost-config.php"

echo "===== Running postdeploy: Secure ost-config.php =====" >> /tmp/postdeploy.log

if [ -f "$CONFIG_FILE" ]; then
    chmod 644 "$CONFIG_FILE"
    echo "Permissions set to 644 for ost-config.php" >> /tmp/postdeploy.log
else
    echo "ost-config.php not found at $CONFIG_FILE" >> /tmp/postdeploy.log
fi

echo "===== Secure config step finished =====" >> /tmp/postdeploy.log
