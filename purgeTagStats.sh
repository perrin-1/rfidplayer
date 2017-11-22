#!/bin/bash
sqlite3 /var/www/html/rfidplayer.sqlite "delete from tagstats where timestamp < DATE('now','-1 day');"