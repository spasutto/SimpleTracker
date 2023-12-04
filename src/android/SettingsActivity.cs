using Android.App;
using Android.Content;
using Android.OS;
using Android.Runtime;
using Android.Util;
using Android.Widget;
using System;
using System.Net.Http;
using System.Text;
using System.Threading.Tasks;

namespace SimpleTracker
{
  [Activity(Label = "SettingsActivity")]
  public class SettingsActivity : Activity
  {
    static readonly string TAG = "SimpleTrackerLog";//typeof(MainActivity).FullName;
    protected override void OnCreate(Bundle savedInstanceState)
    {
      base.OnCreate(savedInstanceState);

      SetContentView(Resource.Layout.settings_layout);

      var btnCancel = FindViewById<Button>(Resource.Id.btnSettingsCancel);
      var btnSave = FindViewById<Button>(Resource.Id.btnSettingsSave);
      var txtUsername = FindViewById<EditText>(Resource.Id.txtUsername);
      var txtPassword = FindViewById<EditText>(Resource.Id.txtPassword);
      var txtRootUrl = FindViewById<EditText>(Resource.Id.txtRootUrl);
      txtRootUrl.Text = Intent.GetStringExtra("trackroot_url");

      /*txtUsername.KeyPress += (s, e) =>
      {
        Log.Info(TAG, "txtUsername : " + txtUsername);
        Log.Info(TAG, "txtPassword : " + txtPassword);
        btnSave.Enabled = (txtUsername.Text.Trim().Length > 0) && (txtPassword.Text.Trim().Length > 0);
      };*/

      btnCancel.Click += (s, e) =>
      {
        Intent returnIntent = new Intent();
        SetResult(Result.Canceled, returnIntent);
        Finish();
      };
      btnSave.Click += async (s, e) =>
      {
        btnCancel.Enabled = btnSave.Enabled = false;
        string url = string.Empty;
        string trackroot_url = txtRootUrl.Text;
        try
        {
          url = await GetUrl(trackroot_url, txtUsername.Text.Trim(), txtPassword.Text.Trim());
        }
        catch(Exception) { }
        btnCancel.Enabled = btnSave.Enabled = true;
        if (!url.StartsWith("http")) MainActivity.Error(this, url);
        else
        {
          Intent returnIntent = new Intent();
          returnIntent.PutExtra("trackupdate_url", url);
          returnIntent.PutExtra("trackroot_url", trackroot_url);
          SetResult(Result.Ok, returnIntent);
          Finish();
        }
      };
    }

    async Task<string> GetUrl(string rooturl, string user, string pwd)
    {
      rooturl = rooturl.Trim();
      if (!rooturl.EndsWith('/')) rooturl += '/';
      var client = new HttpClient();
      var uriroot = new Uri(rooturl);
      client.BaseAddress = new Uri(uriroot.GetLeftPart(UriPartial.Authority));
      string directory = new Uri(uriroot, ".").LocalPath;
      string data = @"pwd=" + pwd;
      var content = new StringContent(data, Encoding.UTF8, "application/x-www-form-urlencoded");
      HttpResponseMessage response = await client.PostAsync($"{directory}geturl/" + user, content);
      var url =  (await response.Content.ReadAsStringAsync()).Trim();
      if (!url.EndsWith('/')) url += '/';
      return url;
    }
  }
}