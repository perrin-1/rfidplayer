#!/usr/bin/python
from mopidy_json_client import MopidyClient
import time
from threading import Timer
from mopidy_json_client.formatting import print_nice
import logging
import logging.handlers

from pirc522 import RFID
#import RPi.GPIO as GPIO 
import sys
import signal
import sqlite3 

defaultFileUnknownTag="file:///home/pi/Music/EntschuldigeDieseKarteKenneIchNicht.mp3"
defaultFileTagReadError="file:///home/pi/Music/EntschuldigeIchKonnteDieseKarteNichtLesen.mp3"
rfidplayerDB="/var/www/html/rfidplayer.sqlite"




# Find a tag in Database
def lookupUriInDB(tagUID):
    applog.debug ("Looking up tagUID %s"%tagUID)
    conn = sqlite3.connect(rfidplayerDB)
    c = conn.cursor()
    #convert tagUID into tuple
    t = (tagUID,)
    
    #Insert Statistic Data into tagstats table
    c.execute('INSERT INTO tagstats (tag_id, timestamp) VALUES (?, datetime(CURRENT_TIMESTAMP, \'localtime\'))',t) 
    conn.commit()
        
    #Search the URI for the tag
    c.execute('SELECT tag_uri FROM tagconfig WHERE tag_id = ?', t)
    URI = c.fetchone()
    if URI is None:
        applog.debug('There is no URI for tag UID %s'%tagUID)
        return None
    else:
        applog.debug('tagUID '+tagUID+' links to URI '+URI[0])
        return URI[0]
    # We can also close the connection if we are done with it.
    # Just be sure any changes have been committed or they will be lost.
    conn.close()


# Helper Functions
def signal_term_handler(signal, frame):
    applog.info ('got SIGTERM')
    applog.info ('ending RFIDPlayer') 
    #cleanup
    # disconnect from mopidy      
    mopidyClient.mopidy.disconnect()
    # turn the backlight on
    bl_on()
    # Calls GPIO cleanup
    rdr.cleanup()
    sys.exit(0)
    




class MopidyListenerClient(MopidyClient):
    """A simple mopidy Client class using the JSON WS interface of mopidy"""
    
    def __init__(self, debug=False):
        applog.debug ('Initializing MopidyClient ...')
       
        # Init variables
        self.state = 'stopped'
        self.uri = None
        self.save_results = False
        self.debug_flag = debug
        

        # Instantiate Mopidy Client
        self.mopidy = MopidyClient(
            ws_url='ws://localhost:6680/mopidy/ws',
            #event_handler=self.on_event,
            connection_handler=self.on_connection,
            autoconnect=False,
            retry_max=None,
            retry_secs=10
        )
        

        self.mopidy.debug_client(self.debug_flag)
        self.mopidy.bind_event('playback_state_changed', self.playback_state_changed)
        self.mopidy.connect()
        
        # Instantiate Timer Objects
        self.backlight_dim_timer = None
        self.backlight_off_timer = None
    
        # Functions for Timer Objects
    def bl_dim(self):
        applog.info ("dimming backlight... ")
        sysfile = open("/sys/class/backlight/rpi_backlight/brightness","w")
        sysfile.write(str(20))
        sysfile.close()
        
    def bl_off(self):
        applog.info ("turning off backlight ")
        sysfile = open("/sys/class/backlight/rpi_backlight/bl_power","w")
        sysfile.write(str(1))
        sysfile.close()
    
        
    def bl_on(self):
        applog.info ("turning on backlight ")
        sysfile = open("/sys/class/backlight/rpi_backlight/bl_power","w")
        sysfile.write(str(0))
        sysfile.close()
        #wait some time for the previous SPI command to finish
        time.sleep (0.5)
        #applog.info ("setting max brightness") 
        sysfile = open("/sys/class/backlight/rpi_backlight/brightness","w")
        sysfile.write(str(255))
        sysfile.close()
    
    
        
     
    def playback_state_changed(self, old_state, new_state):     
        self.state = new_state
        applog.info('State changed: '+str(old_state)+ ' => '+ str(self.state))
        self.backlight_timer_control()
    
    def backlight_timer_control(self):
        #Handle backlight timers
        #try to stop existing timers before starting new timers
        applog.debug ("stopping backlight timers ")
        
        if (self.backlight_dim_timer is not None):
          self.backlight_dim_timer.cancel()
        if (self.backlight_off_timer is not None):
          self.backlight_off_timer.cancel()
        #applog.debug ("turn on backlight")
        self.bl_on()
          
        if (self.state != 'playing'):
          applog.debug ("starting backlight timers")
          self.backlight_dim_timer = Timer(30.0,self.bl_dim)
          self.backlight_off_timer = Timer(60.0,self.bl_off)
          self.backlight_dim_timer.start()
          self.backlight_off_timer.start()
           

      
    def on_connection(self, conn_state):
        if conn_state:
            # Initialize mopidy track and state
            self.state = self.mopidy.playback.get_state(timeout=5)
            tl_track = self.mopidy.playback.get_current_tl_track(timeout=15)
            #self.track_playback_started(tl_track)
            #applog.debug ("On Conn: Current state "+self.state)
            self.backlight_timer_control()
        else:
            self.state = 'stopped'
            self.uri = None
            

    def playback_play (self):
        self.mopidy.playback.play()
    
    def playback_pause (self):
        self.mopidy.playback.pause()
        
    def playback_stop (self):
        self.mopidy.playback.stop()
        
    def tracklist_clear (self):
        self.mopidy.tracklist.clear()
    
    def tracklist_tune (self, uri):
        self.mopidy.playback.stop()
        self.mopidy.tracklist.clear()
        self.mopidy.tracklist.add(at_position=1,uri=uri)
        self.mopidy.mixer.set_volume(100)
        self.mopidy.playback.play()
      

if __name__ == "__main__":

    #Logging facilities - we will log to /var/log/rfidplayer.log since this app is build to run as a daemon
    applog = logging.getLogger()
   
    formatter = logging.Formatter('<%(asctime)s> [%(threadName)s] %(module)s.%(funcName)s: %(levelname)s: %(message)s')
    
    
    #Handler for daemon logfile
    logFileHandler = logging.FileHandler("/var/log/rfidplayer.log")
    logFileHandler.setFormatter(formatter)
    applog.addHandler(logFileHandler)
    
    
    # Handler for stdout - enable four lines for foreground debugging
    stdOutHandler = logging.StreamHandler(sys.stdout)
    stdOutHandler.setFormatter(formatter)
    applog.addHandler(stdOutHandler)
    #applog.setLevel(logging.DEBUG)
    #set global loglevel [default DEBUG]
    applog.setLevel(logging.INFO)
    
    #Initialize RFID reader
    rdr = RFID()
    # set antenna gain
    #rdr.dev_write(0x26, (0x06<<4))
    applog.debug ("Antenna gain "+str(rdr.dev_read(0x26)))
    rdr.set_antenna(False)
    rdr.reset()
    rdr.dev_write(0x2A, 0x8D)
    rdr.dev_write(0x2B, 0x3E)
    rdr.dev_write(0x2D, 30)
    rdr.dev_write(0x2C, 0)
    rdr.dev_write(0x15, 0x40)
    rdr.dev_write(0x11, 0x3D)
    rdr.dev_write(0x26, (0x07<<4))
    rdr.set_antenna(True)
    
    applog.debug ("Antenna gain "+str(rdr.dev_read(0x26)))
    
    mopidyClient = MopidyListenerClient(debug=False)
    
    while not mopidyClient.mopidy.is_connected():
      applog.warn ("Waiting for server connection ")
      time.sleep (0.5)
    
    
    applog.info("RFIDplayer ready for requests...") 
    
    lastTag = ""
    try:
      while True:
        #wait for new tag - only works if IRQ line is connected to RPi PIN GPIO24!
        #Otherwise use device polling
        #applog.debug ("Waiting for tag ")
        rdr.wait_for_tag()
        (error, tag_type) = rdr.request()
        if not error:
          applog.debug("RFID Tag detected")
          (error, uid) = rdr.anticoll()
          if not error:
            tagUID = str(uid[0])+"-"+str(uid[1])+"-"+str(uid[2])+"-"+str(uid[3])+"-"+str(uid[4])                                                              
            if (lastTag != tagUID) or ((lastTag == tagUID) and (mopidyClient.state!='playing')) :
              # set lastTag
              lastTag = tagUID
              applog.debug ("Tag UID: " +tagUID)
              #try to find UID in TrackDB
              tagURI = lookupUriInDB (tagUID)
              #check mopidy connection
              while not mopidyClient.mopidy.is_connected():
                applog.warn ("Waiting for server connection ")
                time.sleep (0.5)
              
              if tagURI is None:
                applog.warning ("Tag UID "+tagUID+" not in config file")
                applog.warning ("Playing default file")
                mopidyClient.tracklist_tune(defaultFileUnknownTag)
              else: 
                applog.info ("Found Track for UID "+tagUID+". Playing "+tagURI)
                mopidyClient.tracklist_tune(tagURI)
            else:
              applog.info ("Duplicate Tag reading and still playing, skipped.")
          else:
              applog.warning ("Tag reading error")
              applog.warning ("Playing default file")
              mopidyClient.tracklist_tune(defaultFileTagReadError)
          #sleep to prevent multiple tag reads
          time.sleep (1)
        time.sleep (0.5) 

    except KeyboardInterrupt:
      applog.info ('ending RFIDPlayer') 
    finally:
      # turn the backlight on
      mopidyClient.bl_on()
      # disconnect from mopidy      
      mopidyClient.mopidy.disconnect()
      # Calls GPIO cleanup
      rdr.cleanup()
      
      
