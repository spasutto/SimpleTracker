# SimpleTracker
**Simple Android+PHP GPS Tracker** Usable both with provided Android app or with [OruxMaps](https://play.google.com/store/apps/details?id=com.orux.oruxmapsDonate&pcampaignid=web_share)

## Installation
### Server Side PHP script
 - Put /web folder online (the ``index.php`` script can be put anywhere). **Don't forget the .htaccess file !!!**
 - Create a "users.txt" file in the same folder that ``index.php``. Inside put users : each line represents an username and a md5 hash of password separated by a colon :
```
user:5f4dcc3b5aa765d61d8327deb882cf99
```

### Android Client
 - Compile/package/sign android app
 - Start the app and go to settings, fill in the username/password fields and click save. To start the livetracking click on the bottom "play" button
 - In the Android app settings, turn off the battery optimisation and set the positionning to high accuracy

## OruxMaps usage
First, read the section above to install [SimpleTracker PHP script](#Server-Side-PHP-script), then in OruxMaps :

Goto settings->integration->mapmytracks and provide url :
```
  https://URL.TO/FOLDER/update/USER/HASH/
```
 - **URL.TO/FOLDER** must be the folder where you put the ``index.php`` script.
 - **/update/** portion is mandatory !
 - **USER** and **HASH** can be found in the `users.txt` file

Example : http://example.net/my_little_tracker/update/bobby/5f4dcc3b5aa765d61d8327deb882cf99

To use livetracking with OruxMaps you must first start the recording of a track, then in the left menu "livetracking" check the "mapsmytrack" checkbox. You can also in the settings turn on the automatic livetracking.

## Exclusions Circles
If you don't want to share your position inside certains geographical areas, you can by specifying positions/radius groups in the ``users.txt`` file, after the password and a colon.

The format is **LATITUDE**,**LONGITUDE**,**RADIUS** (in meters) :
```
user:5f4dcc3b5aa765d61d8327deb882cf99:48.34545,7.345345,200|48.77833,8.453354,200|43.12313,4.3738,200
```
Each position/radius group must be separated by a pipe '|'. In the previous example, 3 zone are defined :
 - Latitude : 48.34545, Longitude : 7.345345, Radius : 200 m
 - Latitude : 48.77833, Longitude : 8.453354, Radius : 200 m
 - Latitude : 43.12313, Longitude : 4.3738, Radius : 200 m

