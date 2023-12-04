using System;
using Android.App;
using Android.OS;
using Android.Runtime;
using Android.Views;
using AndroidX.AppCompat.Widget;
using AndroidX.AppCompat.App;
using Google.Android.Material.FloatingActionButton;
using Google.Android.Material.Snackbar;
using Android.Content;
using Android.Util;
using Xamarin.Essentials;
using AlertDialog = Android.App.AlertDialog;

namespace SimpleTracker
{
  [Activity(Label = "@string/app_name", Theme = "@style/AppTheme.NoActionBar", MainLauncher = true)]
  public class MainActivity : AppCompatActivity
  {
    static readonly string TAG = "SimpleTrackerLog";//typeof(MainActivity).FullName;
    static readonly int LAUNCH_SETTINGS_ACTIVITY = 1;
    string trackupdate_url, trackroot_url;
    Intent serviceToStart;
    bool isStarted = false;
    Android.Widget.TextView textview;
    FloatingActionButton fab;
    protected override void OnCreate(Bundle savedInstanceState)
    {
      base.OnCreate(savedInstanceState);
      Xamarin.Essentials.Platform.Init(this, savedInstanceState);
      SetContentView(Resource.Layout.activity_main);

      trackroot_url = Preferences.Get("trackroot_url", "https://tracker.example.net/");
      trackupdate_url = Preferences.Get("trackupdate_url", string.Empty);

      /*if (savedInstanceState != null)
      {
        isStarted = savedInstanceState.GetBoolean(SERVICE_STARTED_KEY, false);
      }*/
      serviceToStart = new Intent(this, typeof(TrackerService));

      Toolbar toolbar = FindViewById<Toolbar>(Resource.Id.toolbar);
      SetSupportActionBar(toolbar);
      textview = FindViewById<Android.Widget.TextView>(Resource.Id.tvMain);
      //Google.Android.Material.tex
      //Resource.Id.tvMain

      fab = FindViewById<FloatingActionButton>(Resource.Id.fab);
      fab.Click += FabOnClick;
    }

    protected override void OnResume()
    {
      isStarted = isMyServiceRunning(typeof(TrackerService));
      base.OnResume();
    }

    protected override void OnDestroy()
    {
      Log.Info(TAG, "Activity is being destroyed");
      //Log.Info(TAG, "Activity is being destroyed; stop the service.");
      //StopService(serviceToStart);
      base.OnDestroy();
    }

    protected override void OnSaveInstanceState(Bundle outState)
    {
      //outState.PutBoolean(SERVICE_STARTED_KEY, isStarted);
      base.OnSaveInstanceState(outState);
    }

    public override void OnRequestPermissionsResult(int requestCode, string[] permissions, Android.Content.PM.Permission[] grantResults)
    {
      Xamarin.Essentials.Platform.OnRequestPermissionsResult(requestCode, permissions, grantResults);

      base.OnRequestPermissionsResult(requestCode, permissions, grantResults);
    }

    public override bool OnCreateOptionsMenu(IMenu menu)
    {
      MenuInflater.Inflate(Resource.Menu.menu_main, menu);
      return true;
    }

    public override bool OnOptionsItemSelected(IMenuItem item)
    {
      int id = item.ItemId;
      if (id == Resource.Id.action_settings)
      {
        OpenSettings();
        return true;
      }

      return base.OnOptionsItemSelected(item);
    }

    void OpenSettings()
    {
      Intent i = new Intent(this, typeof(SettingsActivity));
      i.PutExtra("trackroot_url", trackroot_url);
      StartActivityForResult(i, LAUNCH_SETTINGS_ACTIVITY);
    }

    protected override void OnActivityResult(int requestCode, [GeneratedEnum] Result resultCode, Intent data)
    {
      base.OnActivityResult(requestCode, resultCode, data);

      if (requestCode == LAUNCH_SETTINGS_ACTIVITY)
      {
        if (resultCode == Result.Ok)
        {
          trackupdate_url = data.GetStringExtra("trackupdate_url");
          Preferences.Set("trackupdate_url", trackupdate_url);
          trackroot_url = data.GetStringExtra("trackroot_url");
          Preferences.Set("trackroot_url", trackroot_url);
        }
        /*else if (resultCode==Result.Canceled)
        {

        }*/
      }
    }

    private void FabOnClick(object sender, EventArgs eventArgs)
    {
      if (!(trackupdate_url ?? string.Empty).Trim().ToLower().StartsWith("http"))
      {
        Error(this, "Avant de pouvoir utiliser le tracking, merci de mettre à jour les paramètres", OpenSettings);
        return;
      }
      Log.Info(TAG, "FabOnClick");
      if (!isStarted)
      {
        Log.Info(TAG, "User requested that the service be started.");
        //StartService(serviceToStart);
        serviceToStart.PutExtra("trackroot_url", trackroot_url);
        serviceToStart.PutExtra("trackupdate_url", trackupdate_url);
        StartForegroundService(serviceToStart);
        isStarted = true;
      }
      else
      {
        Log.Info(TAG, "User requested that the service be stopped.");
        StopService(serviceToStart);
        isStarted = false;
      }

      bool isReallyRunning = isMyServiceRunning(typeof(TrackerService));

      textview.Text = isReallyRunning ? "Currently tracking..." : "";
      fab.SetImageResource(isReallyRunning ? Android.Resource.Drawable.IcMediaPause : Android.Resource.Drawable.IcMediaPlay);

      View view = (View)sender;
      Snackbar.Make(view, "Service " + (isStarted ? "started" + (isReallyRunning?" for real":" not really") : "stopped"), Snackbar.LengthLong)
          .SetAction("Action", (View.IOnClickListener)null).Show();

      /*
      Intent trackerIntent = new Intent(this, typeof(TrackerService));
      //downloadIntent.data = Uri.Parse(fileToDownload);
      //trackerIntent.Data
      StopService(new Intent(this, typeof(TrackerService)));*/

    }

    private bool isMyServiceRunning(Type cls)
    {
      ActivityManager manager = (ActivityManager)GetSystemService(Context.ActivityService);

      foreach (var service in manager.GetRunningServices(int.MaxValue))
      {
        if (service.Service.ClassName.Equals(Java.Lang.Class.FromType(cls).CanonicalName))
        {
          return true;
        }
      }
      return false;
    }

    public static void Error(Context context, string message = "", Action onok=null)
    {
      message = message.Trim().Length > 0 ? message : "Unknown error";
      AlertDialog.Builder dialog = new AlertDialog.Builder(context);
      AlertDialog alert = dialog.Create();
      alert.SetTitle("Error");
      alert.SetMessage(message);
      alert.SetButton("OK", (c, ev) =>
      {
        if (onok != null) onok();
      });
      alert.Show();
    }
  }
}
