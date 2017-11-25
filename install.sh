#!/bin/bash
#use raspian minimal with pixel
CONFIG=/boot/config.txt

echo "Quick Install script for RFIDPlayer."
echo "Warn: This script is quick an dirty work."
read -r -p "Do you want to continue? [y/N] " response
case "$response" in
    [yY][eE][sS]|[yY]) 
        echo "Continuing..."
        ;;
    *)
        exit 0
        ;;
esac

#####BASE CONFIG


if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

echo "boot/config.txt -> add line lcd_rotate=2"
if grep -Fxq lcd_rotate=2  /boot/config.txt
then
    # code if found
    echo "Boot config has already been modified"
else
    # code if not found
    echo "lcd_rotate=2" >> /boot/config.txt
fi


#
echo "Doing raspi-config -> ssh enable, keyboard-layout german, advanced enable spi, timezone Europe/Berlin"

#subroutine to set config variables.
#source credits: raspi-config
set_config_var() {
  lua - "$1" "$2" "$3" <<EOF > "$3.bak"
local key=assert(arg[1])
local value=assert(arg[2])
local fn=assert(arg[3])
local file=assert(io.open(fn))
local made_change=false
for line in file:lines() do
  if line:match("^#?%s*"..key.."=.*$") then
    line=key.."="..value
    made_change=true
  end
  print(line)
end

if not made_change then
  print(key.."="..value)
end
EOF
mv "$3.bak" "$3"
}

echo "Enable ssh"
update-rc.d ssh enable &&
invoke-rc.d ssh start &&

echo "German Keyboard layout"
dpkg-reconfigure keyboard-configuration &&
printf "Reloading keymap. This may take a short while\n" &&
invoke-rc.d keyboard-setup start || return $?
udevadm trigger --subsystem-match=input --action=change

echo "enable SPI"
SETTING=on
STATUS=enabled
BLACKLIST=/etc/modprobe.d/raspi-blacklist.conf

set_config_var dtparam=spi $SETTING $CONFIG &&
if ! [ -e $BLACKLIST ]; then
  touch $BLACKLIST
fi
sed $BLACKLIST -i -e "s/^\(blacklist[[:space:]]*spi[-_]bcm2708\)/#\1/"
dtparam spi=$SETTING

#whiptail --msgbox "The SPI interface is $STATUS" 20 60 1



echo "set timezone"
if grep -Fxq Berlin "/etc/timezone" 
then
  echo "Timezone is already set to Berlin"
else
  dpkg-reconfigure tzdata
fi

 
whiptail --msgbox "\
Please note: RFCs mandate that a hostname's labels \
may contain only the ASCII letters 'a' through 'z' (case-insensitive),
the digits '0' through '9', and the hyphen.
Hostname labels cannot begin or end with a hyphen.
No other symbols, punctuation characters, or blank spaces are permitted.\
" 20 70 1


CURRENT_HOSTNAME=`cat /etc/hostname | tr -d " \t\n\r"`
NEW_HOSTNAME=$(whiptail --inputbox "Please enter a hostname" 20 60 "$CURRENT_HOSTNAME" 3>&1 1>&2 2>&3)
echo $NEW_HOSTNAME > /etc/hostname
sed -i "s/127.0.1.1.*$CURRENT_HOSTNAME/127.0.1.1\t$NEW_HOSTNAME/g" /etc/hosts




echo "please install ssh key manually"
if [ -f /home/pi/.ssh/authorized_keys ]; then
  echo "sshkeys have already been installed"
else
  mkdir /home/pi/.ssh
  touch /home/pi/.ssh/authorized_keys
  read -r -p "Please paste your sshkey now: " sshkey
  echo $sshkey > /home/pi/.ssh/authorized_keys
  chown -R pi:pi /home/pi/.ssh
fi

echo "Updating base system"
apt-get update 
apt-get -y upgrade
#doing upgrade twice as package chromium-rpi-mods fails the first time
apt-get -y upgrade
echo "installing packages" 
apt-get -y install vim x11vnc build-essential python-dev libffi-dev python-websocket python-gst-1.0 \
    gir1.2-gstreamer-1.0 gir1.2-gst-plugins-base-1.0 gstreamer1.0-plugins-good gstreamer1.0-plugins-ugly \
    gstreamer1.0-tools apt-transport-https lighttpd php-cgi sqlite3  php-sqlite3


#echo "Debug exit script now."
#exit 0

####install libspotify

echo "Installing Mopidy Repository"
wget -q -O - http://apt.mopidy.com/mopidy.gpg | sudo apt-key add -

# Mopidy APT archive
echo "deb http://apt.mopidy.com/ stable main contrib non-free" > /etc/apt/sources.list.d/mopidy.list
echo "deb-src http://apt.mopidy.com/ stable main contrib non-free" >> /etc/apt/sources.list.d/mopidy.list

apt-get update
apt-get -y install libspotify-dev

echo "##### configure x11vnc server"
x11vnc -storepasswd /home/pi/vncpasswd
chown pi:pi /home/pi/vncpasswd

echo "MANUAL WORK"
echo "copy .config directory"
cp -r /home/pi/rfidplayer/.config/* /home/pi/.config


echo "/home/pi/.config/autostart/x11vnc.desktop:"
if [ -f /home/pi/.config/autostart/x11vnc.desktop ]; then
   echo "x11vnc Autostart found [OK]"
else
   echo "x11vnc Autostart not found"
fi
echo "#####mopidy autostart"
echo "/home/pi/.config/autostart/mopidy1.desktop:"
if [ -f /home/pi/.config/autostart/mopidy1.desktop ]; then
   echo "mopidy Autostart found [OK]"
else
   echo "mopidy Autostart not found"
fi
 
 
 
 
echo "###pip needed packages"
pip install mopidy
pip install https://github.com/ismailof/mopidy-json-client/archive/master.zip
pip install mopidy-touchscreen mopidy-spotify


echo "###RFID Reader Packages"
#pip install pi-rc522 # this installs an older version of the library.
git clone https://github.com/ondryaso/pi-rc522.git
cd pi-rc522

#echo patching file
cd pirc522
patch < /home/pi/rfidplayer/rfid.patch
cd ..
echo "done patching"

python setup.py install
#git clone https://github.com/lthiery/SPI-Py.git
#cd SPI-Py/
#sudo python setup.py install
#cd ..

read -r -p "Do you want to continue? [y/N] " response
case "$response" in
    [yY][eE][sS]|[yY]) 
        echo "Continuing..."
        ;;
    *)
        exit 0
        ;;
esac

##### link to patched touchscreen source for easier access
#ln -s /usr/local/lib/python2.7/dist-packages/mopidy_touchscreen /home/pi/mopidy_touchscreen_source

 
echo "#####patched mopidy touchscreen files"
echo "in /usr/local/lib/python2.7/dist-packages/mopidy_touchscreen"
echo "-> replace patched files screen_manager.py screens/main_screen.py screen/menu_screen.py"

cp -r /home/pi/rfidplayer/mopidy_touchscreen/screen* /usr/local/lib/python2.7/dist-packages/mopidy_touchscreen


echo "### configurer lighttpd for web based management"
lighty-enable-mod fastcgi-php
service lighttpd start


#cd /var/www/html/

echo "-> Install config file and empty db"
touch /var/www/html/rfidplayer.sqlite
chmod 666 /var/www/html/rfidplayer.sqlite
chown www-data:www-data /var/www/html/rfidplayer.sqlite
sqlite3 /var/www/html/rfidplayer.sqlite < /home/pi/rfidplayer/rfidplayer.sql
chmod a+w /var/www/html

echo "#####sqlite Version Install tageditor"
cp /home/pi/rfidplayer/tag-editor/* /var/www/html

##### sqlite version: optional: Install phpliteadmin
#wget https://bitbucket.org/phpliteadmin/public/downloads/phpLiteAdmin_v1-9-7-1.zip
#unzip phpLiteAdmin_v1-9-7-1.zip
#sudo mv phpliteadmin.*.php /var/www/html

### install base files
#cp rfidplayer-sqlite.py /home/pi
#cp rfidplayer.sh /home/pi

chmod +x /home/pi/rfidplayer*

cp /home/pi/rfidplayer/service/rfidplayer.service /etc/systemd/system
echo "-> enable service"
systemctl enable rfidplayer.service

echo "#### finished!"
echo "please reboot now"


  