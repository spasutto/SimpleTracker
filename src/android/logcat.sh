adb logcat | grep -F "`adb shell ps | grep com.pasutto.simpletracker  | tr -s [:space:] ' ' | cut -d' ' -f2`"
#adb logcat | findstr com.pasutto.simpletracker > logcat.txt
#adb logcat | findstr SimpleTrackerLog > logcat.txt
#adb logcat | findstr pasutto | findstr racker > logcat.txt
#adb logcat | findstr OnStartCommand > logcat.txt