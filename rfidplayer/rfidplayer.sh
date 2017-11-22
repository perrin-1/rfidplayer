#!/bin/sh
#daemon script for rfid player

(
cd /home/pi
/usr/bin/python /home/pi/rfidplayer-sqlite.py
) &

