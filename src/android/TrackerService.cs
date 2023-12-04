using Android.App;
using Android.Content;
using Android.OS;
using Android.Runtime;
using Android.Util;
using Android.Views;
using Android.Widget;
using AndroidX.Core.App;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace SimpleTracker
{
  internal static class NotificationHelper
  {
    private static string foregroundChannelId = "9001";
    private static Context context = global::Android.App.Application.Context;


    public static Notification getnotification()
    {
      // Building intent
      var intent = new Intent(context, typeof(MainActivity));
      intent.AddFlags(ActivityFlags.SingleTop);
      intent.PutExtra("Title", "Message");

      var pendingIntent = PendingIntent.GetActivity(context, 0, intent, PendingIntentFlags.UpdateCurrent);

      var notifBuilder = new NotificationCompat.Builder(context, foregroundChannelId)
          .SetContentTitle("SimpleTracker")
          .SetContentText("Currently tracking...")
          .SetSmallIcon(Resource.Drawable.abc_ic_clear_material)
          .SetOngoing(true)
          .SetContentIntent(pendingIntent);

      // Building channel if API verion is 26 or above
      if (global::Android.OS.Build.VERSION.SdkInt >= BuildVersionCodes.O)
      {
        var notifManager = context.GetSystemService(Context.NotificationService) as NotificationManager;
        if (notifManager != null)
        {
          NotificationChannel notificationChannel = new NotificationChannel(foregroundChannelId, "Title", NotificationImportance.High);
          notificationChannel.Importance = NotificationImportance.High;
          notificationChannel.EnableLights(true);
          notificationChannel.EnableVibration(true);
          notificationChannel.SetShowBadge(true);
          notificationChannel.SetVibrationPattern(new long[] { 100, 200/*, 300, 400, 500, 400, 300, 200, 400*/ });

          notifBuilder.SetChannelId(foregroundChannelId);
          notifManager.CreateNotificationChannel(notificationChannel);
        }
      }

      return notifBuilder.Build();
    }
  }

  [Service]
  [IntentFilter(new String[] { "com.pasutto.TrackerService" })]
  class TrackerService : Service
  {
    static readonly string TAG = "SimpleTrackerLog";//typeof(TrackerService).FullName;
    static readonly int DELAY_BETWEEN_LOG_MESSAGES = 1000; // milliseconds
    static readonly int NOTIFICATION_ID = 10000;

    LocationUpdater updater;
    bool isStarted;
    Handler handler;
    Action runnable;
    bool inprogress = false;

    public override void OnCreate()
    {
      base.OnCreate();
      Log.Info(TAG, "OnCreate: the service is initializing.");

      updater = new LocationUpdater();// new UtcTimestamper();
      handler = new Handler(Looper.MainLooper);

      this.StartForeground(1, NotificationHelper.getnotification());
    }

    public override StartCommandResult OnStartCommand(Intent intent, [GeneratedEnum] StartCommandFlags flags, int startId)
    {
      string trackupdate_url = intent.GetStringExtra("trackupdate_url") ?? string.Empty;
      if (updater != null && trackupdate_url.Trim().ToLower().StartsWith("http"))
      {
        updater.trackupdate_url = trackupdate_url;
      }
      if (isStarted)
      {
        Log.Info(TAG, "OnStartCommand: This service has already been started.");
      }
      else
      {
        Log.Info(TAG, "OnStartCommand: The service is starting.");
        DispatchNotificationThatServiceIsRunning();

        // This Action is only for demonstration purposes.
        runnable = new Action(async () =>
        {
          if (updater != null && !inprogress)
          {
            inprogress = true;
            string result = await updater.update();
            inprogress = false;
            Log.Debug(TAG, result ?? "null result");
            handler.PostDelayed(runnable, DELAY_BETWEEN_LOG_MESSAGES);
          }
        });

        handler.PostDelayed(runnable, DELAY_BETWEEN_LOG_MESSAGES);
        isStarted = true;
      }

      // This tells Android not to restart the service if it is killed to reclaim resources.
      return StartCommandResult.NotSticky;
    }


    public override IBinder OnBind(Intent intent)
    {
      // Return null because this is a pure started service. A hybrid service would return a binder that would
      // allow access to the GetFormattedStamp() method.
      return null;
    }

    public override void OnDestroy()
    {
      // We need to shut things down.
      Log.Info(TAG, "OnDestroy: The started service is shutting down.");

      // Stop the handler.
      handler.RemoveCallbacks(runnable);

      // Remove the notification from the status bar.
      var notificationManager = (NotificationManager)GetSystemService(NotificationService);
      notificationManager.Cancel(NOTIFICATION_ID);

      updater = null;
      isStarted = false;
      base.OnDestroy();
    }

    void DispatchNotificationThatServiceIsRunning()
    {
      Notification.Builder notificationBuilder = new Notification.Builder(this)
        .SetSmallIcon(Resource.Drawable.abc_ic_clear_material)
        .SetContentTitle(Resources.GetString(Resource.String.app_name))
        .SetContentText(Resources.GetString(Resource.String.notification_text));

      var notificationManager = (NotificationManager)GetSystemService(NotificationService);
      notificationManager.Notify(NOTIFICATION_ID, notificationBuilder.Build());
    }
  }
}