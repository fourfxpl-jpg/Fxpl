#!/bin/bash

echo "Running on PORT=$PORT"

sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

apache2ctl -D FOREGROUND
