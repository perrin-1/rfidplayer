#!/usr/bin/python
from mopidy_json_client import MopidyClient, SimpleListener
import time
from threading import Timer
from mopidy_json_client.formatting import print_nice
import logging
import logging.handlers

from pirc522 import RFID
import RPi.GPIO as GPIO 
import sys
import signal




# Global Functions for Timer Objects
def bl_dim():
    applog.info ("dimming backlight... ")
    sysfile = open("/sys/class/backlight/rpi_backlight/brightness","w")
    sysfile.write(str(20))
    sysfile.close()
    
def bl_off():
    applog.info ("turning off backlight ")
    sysfile = open("/sys/class/backlight/rpi_backlight/bl_power","w")
    sysfile.write(str(1))
    sysfile.close()

    
def bl_on():
    applog.info ("turning on backlight ")
    sysfile = open("/sys/class/backlight/rpi_backlight/bl_power","w")
    sysfile.write(str(0))
    sysfile.close()
    #wait some time for the previous SPI command to finish
    time.sleep (0.5)
    applog.info ("setting max brightness") 
    sysfile = open("/sys/class/backlight/rpi_backlight/brightness","w")
    sysfile.write(str(255))
    sysfile.close()


# Helper Functions
def readConfig ():
  configTracks = { }
  with open("rfidplayer.conf") as configfile:
    for line in configfile:
        line.rstrip('\n')
        #applog.debug ("Debug: Line "+line)
        if not line.startswith("#"):
          #applog.debug ("Debug: Line added")
          name, var = line.partition("=")[::2]
          configTracks[name.strip()] = var.rstrip('\n')
          
  return configTracks

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
    




class MopidyListenerClient(SimpleListener):
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
            event_handler=self.on_event,
            connection_handler=self.on_connection,
            autoconnect=False,
            retry_max=10,
            retry_secs=10
        )
        

        self.mopidy.debug_client(self.debug_flag)
        self.mopidy.connect()
        
        # Instantiate Timer Objects
        self.backlight_dim_timer = None
        self.backlight_off_timer = None

    def playback_state_changed(self, old_state, new_state):     
        self.state = new_state
        applog.info('Playback state changed to '+ str(self.state))
        self.backlight_timer_control()
    
    def backlight_timer_control(self):
        #try to stop existing timers before starting new timers
        applog.info ("stopping backlight timers ")
        if (self.backlight_dim_timer is not None):
          self.backlight_dim_timer.cancel()
        if (self.backlight_off_timer is not None):
          self.backlight_off_timer.cancel()
        #applog.debug ("turn on backlight")
        bl_on()
          
        if (self.state != 'playing'):
          applog.info ("starting backlight timers")
          self.backlight_dim_timer = Timer(30.0,bl_dim)
          self.backlight_off_timer = Timer(60.0,bl_off)
          self.backlight_dim_timer.start()
          self.backlight_off_timer.start()
           

      
    def on_connection(self, conn_state):
        if conn_state:
            # Initialize mopidy track and state
            self.state = self.mopidy.playback.get_state(timeout=5)
            tl_track = self.mopidy.playback.get_current_tl_track(timeout=15)
            self.track_playback_started(tl_track)
            applog.debug ("On Conn: Current state "+self.state)
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
      

if __name__ == '__main__':

    #Initialize RFID reader
    rdr = RFID()

    #Logging facilities - we will log to /var/log/rfidplayer.log since this app is build to run as a daemon
    
    
    
    applog = logging.getLogger()
    #mopidylog = logging.getLogger ()
    #handler = logging.handlers.SysLogHandler(address = '/dev/log')
    handler = logging.FileHandler("/var/log/rfidplayer.log")
    formatter = logging.Formatter('[%(threadName)s] %(module)s.%(funcName)s: %(levelname)s: %(message)s')
    handler.setFormatter(formatter)
    applog.addHandler(handler)
    #mopidylog.addHandler (handler)
    
    
    #set debug loglevel
    applog.setLevel(logging.DEBUG)

    mopidyClient = MopidyListenerClient(debug=False)
    
    #for debugging: uncomment the following line to log every message to STDERR
    #logging.basicConfig()

    #handle SIGTERM correctly
    signal.signal(signal.SIGTERM, signal_term_handler)
    
    
    while not mopidyClient.mopidy.is_connected():
      applog.debug ("Waiting for server connection ")
      time.sleep (0.5)
    #Array holding all configured Tags with their respective tracks/playlists/albums
    configTracks = None

    configTracks = readConfig()
    
    applog.info("RFIDplayer ready for requests...") 
    
    lastTag = ""
    try:
      while True:
        (error, tag_type) = rdr.request()
        if not error:
          applog.debug("RFID Tag detected")
          (error, uid) = rdr.anticoll()
          if not error:
            # turn on backlight
            bl_on()
            tagUID = str(uid[0])+"-"+str(uid[1])+"-"+str(uid[2])+"-"+str(uid[3])+"-"+str(uid[4])                                                              
            
            if (lastTag != tagUID) or ((lastTag == tagUID) and (mopidyClient.state!='playing')) :
              # set lastTag
              lastTag = tagUID
              
              applog.info ("Tag UID2: " +tagUID)
              #try to find UID in Tracklist
              if tagUID in configTracks:
                applog.info ("Found Track. Playing "+configTracks[tagUID])
                mopidyClient.tracklist_tune(configTracks[tagUID])
              else:
                applog.warning ("Tag UID "+tagUID+" not in config file")
                
                if (mopidyClient.state == 'stopped'):
                  mopidyClient.backlight_timer_control()
                else:
                  mopidyClient.playback_stop()
            else:
              applog.info ("Duplicate Tag reading and still playing, skipped.")
          else:
              applog.warning ("Tag reading error")
          #sleep to prevent multiple tag reads
          time.sleep (1)

        time.sleep (0.5) 

    except KeyboardInterrupt:
      applog.info ('ending RFIDPlayer') 
    finally:
      # disconnect from mopidy      
      mopidyClient.mopidy.disconnect()
      # turn the backlight on
      bl_on()
      # Calls GPIO cleanup
      rdr.cleanup()
      
      
      




   