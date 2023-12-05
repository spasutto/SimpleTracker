# SimpleTracker
**Simple Android+PHP GPS Tracker** Usable both with provided Android app or with [OruxMaps](https://play.google.com/store/apps/details?id=com.orux.oruxmapsDonate&pcampaignid=web_share)

## Installation

 - Compile/package/sign android app
 - Put /web folder online.
 - Create a "users.txt" file with users : each line represents an username and a md5 hash of password separated by a colon :
```
user:5f4dcc3b5aa765d61d8327deb882cf99
```
 - Start the app and go to settings, fill in the username/password fields and click save. To start the livetracking click on the bottom "play" button
 - In the Android app settings, turn off the battery optimisation and set the positionning to high accuracy

## OruxMaps usage
Install first the web version, then in OruxMaps :

Goto settings->integration->mapmytracks and provide url :
```
  https://URL.TO/FOLDER/USER/HASH/
```
**USER** and **HASH** can be found in the `users.txt` file

To use livetracking first you must start the track recording, then in the left menu "livetracking" check the "mapsmytrack" checkbox. You can also in the settings turn on the automatic livetracking.
