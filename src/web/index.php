<?php
const TRACKFOLDER = './tracks/';
$ROOTFOLDER = dirname($_SERVER['PHP_SELF']);
function joinPaths() {
  $args = func_get_args();
  $paths = array();
  foreach ($args as $arg) {
    $paths = array_merge($paths, (array)$arg);
  }

  $paths = array_map(create_function('$p', 'return trim($p, "/");'), $paths);
  $paths = array_filter($paths);
  return join('/', $paths);
}

function checkPwd($username, $hash) {
  $users = preg_split("/\r\n|\n|\r/", @file_get_contents('users.txt'));
  $username = strtolower($username);
  $hash = strtolower($hash);//hash('sha256', $pwd));
  foreach($users as $user) {
    $user = explode(':', $user);
    if (count($user)<2) continue;
    if (strtolower($user[0]) == $username) {
      return strtolower($user[1]) == $hash;
    }
  }
}
function get_users($u, $mints=-1, $users=null) {
  if (!is_dir(TRACKFOLDER)) {
    http_response_code(404);
    echo "Not found ";
    die();
  }
  if (!is_array($users)) $users = array();
  $jsonpath = joinPaths(array(TRACKFOLDER, "*.json"));
  $tracks = array();
  foreach(glob($jsonpath) as $file){
    if (!is_file($file)) continue;
    $pts = null;
    $uname = strtolower(pathinfo($file, PATHINFO_FILENAME));
    try
    {
      $pts = json_decode(file_get_contents($file));
      if ($mints>0 && in_array($uname, $users)) {
        $pts = array_filter($pts, function($pt){
          global $mints;
          return $pt->time > $mints;
        });
      }
    } catch(Exception $err) {
      continue;
    }
    if (strlen($u) == 0 || $u == $uname) {
      array_push($tracks, (object)['name'=>$uname, 'pts'=>$pts]);
    }
  }
  return $tracks;
}

function update_user($user, $hash, $points) {
  if (!checkPwd($user, $hash)) {
    http_response_code(403);
    echo "No access";
    die();
  }
  $points = explode(' ', $points);
  if (count($points)%4 != 0) {
    $points = array_slice($points, 0, count($points)-count($points)%4);
  }
  if (count($points) <= 0) {
    http_response_code(204);
    die();
  }
  $jsonpath = joinPaths(array(TRACKFOLDER, $user.".json"));
  $pts = null;
  if (file_exists($jsonpath)) {
    $pts = json_decode(file_get_contents($jsonpath));
  }
  if (!is_array($pts)) $pts=array(); 
  for ($i=0; $i<count($points); $i+=4) {
    $lat=@floatval($points[$i]);
    $lon=@floatval($points[$i+1]);
    $alt=@floatval($points[$i+2]);
    $time=@intval($points[$i+3]);
    array_unshift($pts, (object) ['time'=>$time, 'lat'=>$lat, 'lon'=>$lon, 'alt'=>$alt]);
  }
  // tri par temps
  function cmp($a, $b) {
    return $b->time-$a->time;
  }
  usort($pts, "cmp");
  // suppression des vieilles entrées
  $sliceind = -1;
  $curtime=time();
  for ($i=0; $i<count($pts); $i++) {
    if ($curtime-$pts[$i]->time > 31536000) { // 1 an
      $sliceind = $i;
      break;
    }
  }
  if ($sliceind>-1) {
    $pts = array_slice($pts, 0, $sliceind);
  }
  // suppression des doublons
  for ($i=count($pts)-1; $i>0; $i--) {
    if ($pts[$i]->time==$pts[$i-1]->time && $pts[$i]->lat==$pts[$i-1]->lat && $pts[$i]->lon==$pts[$i-1]->lon) {
      unset($pts[$i]);
    }
  }
  if (!file_exists(TRACKFOLDER)) {
    mkdir(TRACKFOLDER, 0777, true);
  }
  file_put_contents($jsonpath, json_encode(array_values($pts)));
  echo "OK";
}

function geturl($user, $pwd) {
  $hash = md5($pwd);
  if (!checkPwd($user, $hash)) {
    http_response_code(403);
    echo "Wrong password";
    die();
  }
  echo (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://$_SERVER[HTTP_HOST]".dirname($_SERVER['PHP_SELF'])."/update/".$user."/".$hash;
}

if (isset($_REQUEST['operation']) && strlen($op = trim($_REQUEST['operation']))>0) {
  switch ($op) {
    case 'fetch':
      header('content-type:application/json');
      $mints = isset($_REQUEST['ts']) && filter_var($_REQUEST['ts'], FILTER_VALIDATE_INT) ? intval($_REQUEST['ts']) : -1;
      echo json_encode(get_users(trim($_REQUEST['user']), $mints, explode(',',$_REQUEST['users'])));
      //sleep(rand(0, 5));
      exit(0);
    case 'update':
      header('content-type:application/json');
      $points = $_REQUEST['points'];
      if (isset($_REQUEST['lat']) && isset($_REQUEST['lon']) && isset($_REQUEST['time']))
        $points = $_REQUEST['lat']." ".$_REQUEST['lon']." ".$_REQUEST['alt']." ".$_REQUEST['time'];//44.75884895771742 5.675952415913343 813.4426 1701452873
      update_user(trim($_REQUEST['user']), trim($_REQUEST['hash']), $points);
      //sleep(rand(0, 5));
      exit(0);
    case 'geturl':
      geturl(trim($_REQUEST['user']), trim($_REQUEST['pwd']));
      exit(0);
  }
}
?>
<html>
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1" />
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
 <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
 <style>
  html, body {
    margin:0px;
    padding:0px;
  }
   #map{
     position: absolute;
     top: 30px;
     bottom: 0;
     width: 100%;
   }
   label {margin-right:10px;}
   input[type='checkbox'] {margin-top:5px;}
 </style>
</head>
<body>
  <input type="checkbox" name="cboldtracks" id="cboldtracks"><label for="cboldtracks">> 24H</label>
  <input type="checkbox" name="cbholetracks" id="cbholetracks"><label for="cbholetracks">> 1km</label>
  <input type="checkbox" name="cbfollow" id="cbfollow" onclick="if (this.checked) cbcadrer.checked=false;" checked><label for="cbfollow">suivre</label>
  <input type="checkbox" name="cbcadrer" id="cbcadrer" onclick="if (this.checked) cbfollow.checked=false;"><label for="cbcadrer">cadrer</label><BR>
  <div id="map"></div>
  <script>
  var rootfolder = '<?php echo $ROOTFOLDER;?>';
  var user = '<?php echo $_REQUEST['user'];?>';
  var ucolors = {};
  var umarkers = {};
  var plines = [];
  var loadtracksdone = true;
  var controller = null;
  var signal = null;
  var zoomed = false;
  var mints = -1
  var tracks = [];
  function loadMap() {
    window.map = L.map('map').setView([45.1696, 5.724637], 12);
    var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    var opentopomap = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      maxZoom: 17,
      attribution: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'
    });
  
    var baseMaps = {};
    baseMaps["Street Map"] = osm;
    baseMaps["Topographique"] = opentopomap;
    for (let basemap in baseMaps) {
      baseMaps[basemap].addTo(map);
    }
    L.control.layers(baseMaps, null, {position: 'topleft'}).addTo(map);
  }
  function fetchTracks() {
    if (!loadtracksdone) controller.abort();
    controller = new AbortController();
    signal = controller.signal;
    loadtracksdone = false;
    fetch(rootfolder+"/fetch/"+user+"?ts="+mints+"&users="+encodeURIComponent(tracks.map(t => t.name).join(',')), { signal })
    .then(r=>r.json())
    .then(updateTracks)
    .catch((err) => {
      console.error(`fetchTracks error: ${err.message}`);
    });
  }
  function updateTracks(data) {
    loadtracksdone = true;
    data.forEach(t => {
      if (t.pts?.constructor !== Array) {t.pts = []; return;}
      if (typeof t.name !== 'string') {t.name='unknown';return;}
      let cible = window.tracks.find(tc => tc.name==t.name);
      if (!cible) {
        window.tracks.push(t);
      } else if (t.pts.length>0) {
        t.pts.forEach(pt => cible.pts.push(pt));
      }
    });
    // suppression des traces qui n'existent plus
    window.tracks = window.tracks.filter(t => data.some(t2 => t2.name == t.name));
    loadTracks();
  }
  function loadTracks() {
    //Object.values(umarkers).forEach(m => map.removeLayer(m));
    //umarkers = {};
    plines.forEach(l => map.removeLayer(l));
    plines = [];
    tracks.forEach(t => {
      if (!ucolors.hasOwnProperty(t.name)) ucolors[t.name] = "#" + ((1 << 24) * Math.random() | 0).toString(16).padStart(6, "0");
      let now = Date.now()/1000;
      if (!cboldtracks.checked) {
        t.pts = t.pts.filter(pt => (now-pt.time)<86400);//86400s==24h
      }
      // tri par temps
      t.pts = t.pts.sort((a,b) => b.time-a.time);
      // suppression des points identiques
      t.pts = t.pts.filter((pt,i) => i==0 || JSON.stringify(t.pts[i-1]) != JSON.stringify(pt));
      if (!cbholetracks.checked) {
        let i = t.pts.findIndex((pt, i) => i>0 && distance(t.pts[i-1].lat, t.pts[i-1].lon, t.pts[i].lat, t.pts[i].lon) > 1);
        if (i>=0) t.pts.length = i;
      }
      if (t.pts.length > 0) {
        if (t.pts[0].time > mints) mints = t.pts[0].time;
        let latlngs = t.pts.map(pt => [pt.lat, pt.lon]);
        plines.push(L.polyline(latlngs, {color: ucolors[t.name]}).addTo(map));
        let alt = t.pts[0].alt;
        if (typeof alt !== 'number') alt = '';
        else alt = `<BR>${Math.round(alt*10)/10}m`;
        let date = new Date(t.pts[0].time*1000);
        date = `${date.toLocaleString()} (${Math.round((now-date.getTime()/1000)/60)}min)`;
        if (Object.hasOwn(umarkers, t.name)) umarkers[t.name].setLatLng(latlngs[0]).setPopupContent(`${t.name} @ ${date}${alt}`);
        else {
          umarkers[t.name] = L.marker(latlngs[0]).addTo(map).bindPopup(`${t.name} @ ${date}${alt}`);
          if (cbfollow.checked || cbcadrer.checked) umarkers[t.name].openPopup();
        }
      }
    });
    // suppression des traces qui n'existent plus
    Object.keys(umarkers).filter(kt => !tracks.some(t => t.name == kt)).forEach(kt => {
      map.removeLayer(umarkers[kt]);
      delete umarkers[kt];
    });
    if (Array.isArray(plines) && plines.length) {
      if (cbfollow.checked) {
        let tmpmarkers = Object.values(umarkers);
        if (tmpmarkers.length > 1) {
          map.fitBounds(new L.featureGroup(tmpmarkers).getBounds());
        } else {
          map.setView(tmpmarkers[0].getLatLng()/*, 14*/);
        }
      }
      else if (cbcadrer.checked) {
        map.fitBounds(new L.featureGroup(plines).getBounds());
      }
    }
  }
  function distance(lat1,lon1,lat2,lon2) {
    var R = 6371; // Radius of the earth in km
    var dLat = deg2rad(lat2-lat1);  // deg2rad below
    var dLon = deg2rad(lon2-lon1); 
    var a = 
      Math.sin(dLat/2) * Math.sin(dLat/2) +
      Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
      Math.sin(dLon/2) * Math.sin(dLon/2)
      ; 
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
    var d = R * c; // Distance in km
    return d;
  }
  function deg2rad(deg) {
    return deg * (Math.PI/180)
  }
  //console.log(distance(44.79271 , 5.612266, 44.800738 , 5.580474));//2.66km
  loadMap();
  
  window.setInterval(fetchTracks, 2000);
  </script>
</body>
</html>