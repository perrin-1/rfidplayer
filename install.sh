
#use raspian minimal with pixel
#!/bin/bash

#####BASE CONFIG
echo "boot/config.txt -> add line lcd_rotate=2"
sudo echo "lcd_rotate=2" >> /boot/config.txt
echo "raspi-config -> ssh enable, keyboard-layout german, advanced enable spi, timezone Europe/Berlin"

echo "network-connection wifi via gui"
echo "install ssh key"


 
sudo apt-get update 
sudo apt-get upgrade
echo "installing packages" 
sudo apt-get install vim x11vnc build-essential python-dev libffi-dev python-websocket python-gst-1.0 \
    gir1.2-gstreamer-1.0 gir1.2-gst-plugins-base-1.0 gstreamer1.0-plugins-good gstreamer1.0-plugins-ugly \
    gstreamer1.0-tools apt-transport-https lighttpd php-cgi sqlite3  php-sqlite3


####install libspotify

wget -q -O - http://apt.mopidy.com/mopidy.gpg | sudo apt-key add -

# Mopidy APT archive
echo "deb http://apt.mopidy.com/ stable main contrib non-free" > /etc/apt/sources.list.d/mopidy.list
echo "deb-src http://apt.mopidy.com/ stable main contrib non-free" >> /etc/apt/sources.list.d/mopidy.list


sudo apt-get update
sudo apt-get install libspotify-dev

echo "##### configure x11vnc server"
x11vnc -storepasswd /home/pi/vncpasswd
chown pi:pi /home/pi/vncpasswd

echo "MANUAL WORK"
echo "copy .config directory"
echo /home/pi/.config/autostart/x11vnc.desktop:
  
echo #####mopidy autostart
echo /home/pi/.config/autostart/mopidy1.desktop:

 
echo "###pip needed packages"
sudo pip install mopidy
sudo pip install https://github.com/ismailof/mopidy-json-client/archive/master.zip
sudo pip install mopidy-touchscreen mopidy-spotify


echo "###RFID Reader Packages"
sudo pip install pi-rc522
git clone https://github.com/lthiery/SPI-Py.git
cd SPI-Py/
sudo python setup.py install
cd ..

##### link to patched touchscreen source for easier access
#ln -s /usr/local/lib/python2.7/dist-packages/mopidy_touchscreen /home/pi/mopidy_touchscreen_source

 
echo "#####patched mopidy touchscreen files"
echo "in /usr/local/lib/python2.7/dist-packages/mopidy_touchscreen"
echo "-> replace patched files screen_manager.py screens/main_screen.py screen/menu_screen.py"

sudo cp -r mopidy_touchscreen/screen* /usr/local/lib/python2.7/dist-packages/mopidy_touchscreen


echo "### configurer lighttpd for web based management"
sudo lighty-enable-mod fastcgi-php
sudo service lighttpd start


#cd /var/www/html/

echo "-> Install config file and empty db"
sudo touch /var/www/html/rfidplayer.sqlite
sudo chmod 666 /var/www/html/rfidplayer.sqlite
sudo chown www-data:www-data rfidplayer.sqlite
sudo chmod a+w /var/www/html

echo "#####sqlite Version Install tageditor"
cp tag-editor/* /var/www/html

##### sqlite version: optional: Install phpliteadmin
#wget https://bitbucket.org/phpliteadmin/public/downloads/phpLiteAdmin_v1-9-7-1.zip
#unzip phpLiteAdmin_v1-9-7-1.zip
#sudo mv phpliteadmin.*.php /var/www/html

### install base files
#cp rfidplayer-sqlite.py /home/pi
#cp rfidplayer.sh /home/pi

chmod +x /home/pi/rfidplayer*

sudo cp /home/pi/service/rfidplayer.service /etc/systemd/system
echo "-> enable service"
sudo systemctl enable rfidplayer.service

read -r -p "Reboot now? [y/N] " response
case "$response" in
    [yY][eE][sS]|[yY]) 
        sudo reboot
        ;;
    *)
        echo "Reboot cancelled"
        ;;
esac



echo "#### finished!""


  