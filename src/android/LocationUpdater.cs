using Android.App;
using Android.Content;
using Android.OS;
using Android.Runtime;
using Android.Util;
using Android.Views;
using Android.Widget;
using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Net.Http;
using System.Text;
using System.Threading.Tasks;
using Xamarin.Essentials;

namespace SimpleTracker
{
  class LocationUpdater
  {
    static readonly string TAG = "SimpleTrackerLog";//typeof(TrackerService).FullName;
    public string trackupdate_url = string.Empty;
    public async Task<string> update()
    {
      if (!(trackupdate_url ?? string.Empty).Trim().ToLower().StartsWith("http"))
        return null;
      
      try
      {
        //var location = await Geolocation.GetLastKnownLocationAsync();
        var location = await Geolocation.GetLocationAsync();
        var time = ((DateTimeOffset)DateTime.Now).ToUnixTimeSeconds();

        if (location != null)
        {
          var client = new HttpClient();
          var uriupdate = new Uri(trackupdate_url);
          client.BaseAddress = new Uri(uriupdate.GetLeftPart(UriPartial.Authority));
          string updaterroot = new Uri(uriupdate, ".").LocalPath;
          NumberFormatInfo nfi = new NumberFormatInfo();
          nfi.NumberDecimalSeparator = ".";
          HttpResponseMessage response = await client.GetAsync($"{updaterroot}?time={time}&lat={location.Latitude.ToString(nfi)}&lon={location.Longitude.ToString(nfi)}&alt={(location.Altitude ?? -1).ToString(nfi)}");
          Log.Info(TAG, $"Latitude: {location.Latitude.ToString(nfi)}, Longitude: {location.Longitude.ToString(nfi)}, Altitude: {(location.Altitude ?? -1).ToString(nfi)}");
          return await response.Content.ReadAsStringAsync();
          //return $"Latitude: {location.Latitude}, Longitude: {location.Longitude}, Altitude: {location.Altitude}";
        }
      }
      catch (FeatureNotSupportedException fnsEx)
      {
        Log.Error(TAG, "LocationUpdater feature not supported error : " + fnsEx.Message);
      }
      catch (FeatureNotEnabledException fneEx)
      {
        Log.Error(TAG, "LocationUpdater feature not enabled error: " + fneEx.Message);
      }
      catch (PermissionException pEx)
      {
        Log.Error(TAG, "LocationUpdater permission error : " + pEx.Message);
      }
      catch (Exception ex)
      {
        // Unable to get location
        Log.Error(TAG, "LocationUpdater unknown error : " + ex.Message);
      }

      return "UNKNOWN LOCATION";
    }
  }
}