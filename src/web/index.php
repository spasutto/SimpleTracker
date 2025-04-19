<?php
//var_dump($_REQUEST);exit(0);
const TRACKFOLDER = './tracks/';
$gmints = time() - 14*24*3600;
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
function getExclusions($userinfos) {
  $exclusions = array();
  if (count($userinfos)<2) return $exclusions;
  $userinfos = explode('|', $userinfos[1]);
  foreach($userinfos as $userexclusion) {
    $userexclusion = explode(',', $userexclusion);
    if (count($userexclusion)<3) continue;
    $exclusions[] = array(floatval($userexclusion[0]), floatval($userexclusion[1]), floatval($userexclusion[2]));
  }
  return $exclusions;
}
function getUserInfos($username = null) {
  // user:pwdhash:latexcl1,lonexcl1,distexcl1|latexcl2,lonexcl2,distexcl2|...
  $allusers = $username==null || strlen(trim($username))==0;
  $retu = new stdClass;
  $users = preg_split("/\r\n|\n|\r/", @file_get_contents('users.txt'));
  $username = strtolower($username);
  foreach($users as $user) {
    $user = explode(':', $user);
    if (count($user)<2) continue;
    if ($allusers) {
      $username = $user[0];
      array_shift($user);
      $retu->{$username} = $user;
    }
    else if (strtolower($user[0]) == $username) {
      array_shift($user);
      return $user;
    }
  }
  return (array) $retu;
}

function checkPwd($username, $hash) {
  $hash = strtolower($hash);//hash('sha256', $pwd));
  $uinfos = getUserInfos($username);
  return !!$uinfos && strtolower($uinfos[0]) == $hash;
}
function get_users($u, $mints=-1, $users=null) {
  if (!is_dir(TRACKFOLDER)) {
    http_response_code(404);
    echo "Not found ";
    die();
  }
  $u = strtolower(trim($u));
  if (strlen($u)<=0) $u = '*';
  if (!is_array($users)) $users = array();
  $userfilepath = joinPaths(array(TRACKFOLDER, $u.".txt"));
  $tracks = array();
  $files = glob($userfilepath);
  if ($u != '*' && count($files) <= 0) {
    http_response_code(404);
    echo "Not found ";
    die();
  }
  $usersinfos = getUserInfos();
  foreach($files as $file){
    if (!is_file($file)) continue;
    $pts = array();
    $uname = strtolower(pathinfo($file, PATHINFO_FILENAME));
    if ($u != '*' && $u != $uname) continue;
    $exclusions = getExclusions($usersinfos[$uname]);
    $isexclusions = count($exclusions)>0;
    try
    {
      if ($file = fopen($file, "r")) {
        while(!feof($file)) {
          $line = fgets($file);
          $pt = explode(',', $line);
          if (count($pt) != 4) continue;
          $pt = (object) [
            'time' => intval($pt[0]),
            'lat' => floatval($pt[1]),
            'lon' => floatval($pt[2]),
            'alt' => floatval($pt[3])
          ];
          if (($mints>0 && in_array($uname, $users)) || $isexclusions) {
            if (($pt->time <= $mints) || ($isexclusions && isExcluded($pt, $exclusions))) continue;
          }
          /*if ($pt->time <= time() - 14*24*3600) {
            continue;
          }*/
          $pts[] = $pt;
        }
        fclose($file);
      }
    } catch(Exception $err) {}
    array_push($tracks, (object)['name'=>$uname, 'pts'=>$pts]);
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
  // réponse avant la fin du traitement
  header("Content-type: text/xml");
  //ignore_user_abort(true);//not required
  set_time_limit(0);
  ob_start();
  echo "<"."?xml version=\"1.0\" encoding=\"UTF-8\"?><message><type>activity_updated</type></message>";
  header('Connection: close');
  header('Content-Length: '.ob_get_length());
  ob_end_flush();
  @ob_flush();
  flush();
  //fastcgi_finish_request();//required for PHP-FPM (PHP > 5.3.3)
  // fin réponse avant la fin du traitement

  $userfilepath = joinPaths(array(TRACKFOLDER, $user.".txt"));
  $pts="";
  for ($i=0; $i<count($points); $i++) {
    if (substr_count($points[$i], ',') > 1 || (substr_count($points[$i], ',') > 0 && substr_count($points[$i], '.') > 0)) str_replace(',', '', $points[$i]);
    else str_replace(',', '.', $points[$i]);
    preg_replace('/[^\d.]/g', '', $points[$i]);
  }
  for ($i=0; $i<count($points); $i+=4) {
    $pts .= $points[$i+3].",".$points[$i].",".$points[$i+1].",".$points[$i+2].PHP_EOL;
  }
  if (!file_exists(TRACKFOLDER)) {
    mkdir(TRACKFOLDER, 0777, true);
  }
  file_put_contents($userfilepath, $pts , FILE_APPEND | LOCK_EX);
  echo "OK";
  die();
}
function clear_user($user, $hash, $time = 0) {
  if (!checkPwd($user, $hash)) {
    http_response_code(403);
    echo "No access";
    die();
  }
  if ($time == 0) {
    echo "OK";
    return;
  }
  $userfilepath = joinPaths(array(TRACKFOLDER, $user.".txt"));
  $pts = array();
  if (file_exists($userfilepath)) {
    if ($file = fopen($file, "r")) {
      while(!feof($file)) {
        $line = fgets($file);
        $pt = explode(',', $line);
        $pt = (object) [
          'time' => intval($pt[0]),
          'lat' => floatval($pt[1]),
          'lon' => floatval($pt[2]),
          'alt' => floatval($pt[3])
        ];
        if ($time > 0 && $pt->time >= $time) 
          continue;
        $pts[] = $pt;
      }
      fclose($file);
    }
  }
  if (time < 0) { // time < 0 ==> delta du point le plus récent
    // tri par temps
    function cmp($a, $b) {
      return $b->time-$a->time;
    }
    usort($pts, "cmp");
    $time *= 60;//le detlta est en minutes
    $time += $pts[0]->time;
    $pts = array_filter($pts, function($pt) use($time) {
      return $pt->time < $time;
    });
  }
  $file = fopen($userfilepath,"w");
  foreach ($pts as $pt) {
    fwrite($file,$pt->time.",".$pt->lat.",".$pt->lon.",".$pt->alt.PHP_EOL);
  }
  fclose($file);
  echo "OK";
}

function geturl($user, $pwd) {
  $hash = md5($pwd);
  if (!checkPwd($user, $hash)) {
    http_response_code(403);
    echo "Mauvais password";
    die();
  }
  echo (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://$_SERVER[HTTP_HOST]".dirname($_SERVER['PHP_SELF'])."/update/".$user."/".$hash;
}
function isExcluded($pt, $exclusions) {
  $nbrexclusions = count($exclusions);
  for ($i=0; $i<$nbrexclusions; $i+=3) {
    if (distance($pt->lat, $pt->lon, $exclusions[$i][0], $exclusions[$i][1]) < $exclusions[$i][2]) {
      //echo distance($pt->lat, $pt->lon, $exclusions[$i][0], $exclusions[$i][1]);
      //var_dump($exclusions);
      //var_dump($pt);
      return true;
    }
  }
  return false;
}
function distance($lat1, $lon1, $lat2, $lon2) 
{
  if (($lat1 == $lat2) && ($lon1 == $lon2)) {
    return 0;
  }
  else {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    
    //echo round($miles * 1609.344)."\n";

    return ($miles * 1609.344);
  }
}
if (isset($_REQUEST['operation']) && strlen($op = trim($_REQUEST['operation']))>0) {
  switch ($op) {
    case 'fetch':
      header('content-type:application/json');
      $mints = isset($_REQUEST['ts']) && filter_var($_REQUEST['ts'], FILTER_VALIDATE_INT) ? intval($_REQUEST['ts']) : $gmints;
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
    case 'clear':
      $time = 0;
      if (isset($_REQUEST['time']) && preg_match("/^-?\d+$/", $_REQUEST['time'])) {
        $time = @intval($_REQUEST['time']);
      }
      clear_user(trim($_REQUEST['user']), trim($_REQUEST['hash']), $time);
      //sleep(rand(0, 5));
      exit(0);
    case 'geturl':
      geturl(trim($_REQUEST['user']), trim($_REQUEST['pwd']));
      exit(0);
  }
}
?>
<!DOCTYPE html>
<html>
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1" />
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
 <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
 <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
 <style>
  html, body {
    margin:0px;
    padding:0px;
    font-family: 'Lato', sans-serif;
  }
   #map{
     position: absolute;
     top: 30px;
     bottom: 0;
     width: 100%;
   }
   label {margin-right:10px;}
   input[type='checkbox'] {margin-top:5px;}
   #qr {
     position: absolute;
     cursor:pointer;
   }
   
.overlay {
  height: 100%;
  width: 0;
  position: fixed;
  z-index: 1;
  top: 0;
  left: 0;
  background-color: rgb(0,0,0);
  background-color: rgba(0,0,0, 0.9);
  overflow-x: hidden;
}
.overlay-content {
  position: relative;
  top: 25%;
  width: 100%;
  text-align: center;
  margin-top: 30px;
}
.overlay a {
  padding: 8px;
  text-decoration: none;
  font-size: 36px;
  color: #818181;
  display: block;
  transition: 0.3s;
}
.overlay img {
  display: initial !important;
}
.overlay a:hover, .overlay a:focus {
  color: #f1f1f1;
}
.overlay .closebtn {
  position: absolute;
  top: 20px;
  right: 45px;
  font-size: 60px;
}
@media screen and (max-height: 450px) {
  .overlay a {font-size: 20px}
  .overlay .closebtn {
  font-size: 40px;
  top: 15px;
  right: 35px;
  }
}
 </style>
</head>
<body>
  <div id="ovl" class="overlay">
    <a href="javascript:void(0)" class="closebtn" onclick="closePopup()">&times;</a>
    <div id="popupcont" class="overlay-content">
      <form id="formqr" onsubmit="return false;" style="display:none;">
        <input type="text" id="qruser" onfocus="this.select();" onmouseup="return false;" value="username" style="width:330px;"><BR>
        <input type="password" id="qrpwd" onfocus="this.select();" onmouseup="return false;" value="password" style="width:330px;"><BR>
        <input type="submit" onclick="genQR(qruser.value, qrpwd.value);return false;" value="submit" style="width:330px;">
      </form>
      <div id="qrres" style="display:none;">
        <input type="text" id="qrtext" onfocus="this.select();" onmouseup="return false;" style="width:330px;"><BR>
        <div id="qrcode"></div>
      </div>
    </div>
  </div>
  <input type="checkbox" name="cboldtracks" id="cboldtracks" onclick="resetmints();"><label for="cboldtracks" onclick="resetmints();" title="afficher les traces anciennes">&gt; 24H</label>
  <input type="checkbox" name="cbholetracks" id="cbholetracks" onclick="resetmints();"><label for="cbholetracks" onclick="resetmints();" title="afficher les trous dans la trace">&gt; 1km</label>
  <input type="checkbox" name="cbfollow" id="cbfollow" onclick="if (this.checked) cbcadrer.checked=false; else map.closePopup();" checked><label for="cbfollow">suivre</label>
  <input type="checkbox" name="cbcadrer" id="cbcadrer" onclick="if (this.checked) cbfollow.checked=false;else map.closePopup();"><label for="cbcadrer">cadrer</label>
  <img id="qr" onclick="openQR()" title="Obtenir l'URL du tracker" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAMAAADXqc3KAAADAFBMVEX+/v7d3d1GRkYMDAwKCgoLCwsJCQkxMTFBQUEAAABTU1NVVVVUVFR2dnYICAh+fn4wMDApKSklJSUqKio4ODiwsLAvLy+NjY2QkJCKiooaGhpzc3OLi4usrKxQUFB1dXUCAgJNTU0uLi6IiIimpqbOzs5PT08yMjKJiYnT09MbGxtYWFhaWlpXV1cFBQVycnJ0dHQGBgZeXl5cXFwcHBwzMzOurq5vb29sbGxoaGjMzMzFxcWnp6fExMTW1tbZ2dlqamqPj4+7u7uVlZVlZWWYmJjLy8vGxsbR0dGamprV1dV3d3ddXV3Nzc2enp5RUVFjY2O+vr7AwMDY2NiTk5PBwcGoqKicnJzU1NRWVlZSUlIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAgAAAAAAAAAAAAAAAAAD56wD56wAAAGgAAAD55jD55jAAAFgAAAAAEAAAAAAAAAAAAAAAAAD56JD558AAADQAAAD55mT55mQAACQAAAAAAQAAAAAAAAAAAAAAA0AAABNBfMAAAAAAAAD55pj55pgAAMAAAAAAAAAAAAAAAAAAEAAAAAD57Dj56PgAAJwAAAD55sz55swAAIwAAAAAAAAAAAAAAAAAACAAAAD54qz56jAAAGgAAAD55wD55wAAACQAAAAAAAAAAAAAAIAAAAAAADQAABNBfMAAAAAAAAD55zT55zQAACQAAAAAAAAAAAAAAAAAABAABKwAABNBfMAAAAAAAAD552j552gAACQAAAAAAAAAAAAAAABAAAAABOAAABNBfMAAAAAAAAD555z555wAASgAAAAAAAAAAAAAAAAAAAAAAAD558D558AAAQQAAAD559D559AAACQAAAAABAAAAAAAAAAAAAAAADQAABNBfMBhhVbYAAAAAXRSTlMAQObYZgAAAAlwSFlzAAALEgAACxIB0t1+/AAAAR5JREFUeNqdkelSwlAMhQ9wy+X2tqxlLbJTEFtRcSmoiDuCFnfF938QSytOq6Mzmj85k28mOUmAf0QgGCJECNOFpmGBkFAw4IAIE7nERXmhZUeyiAOi0tceUtRJhCMWTyRTSjqtpJKJeAycuEBERsnm8gVVLeRzWSUD0QUCBy1iBaVyuWSnIgUXHFCpolZvsKamqlqTNeo1VCsOoDJabaxCk2XNTu0WZLq0QTtrurHe3djUt3rbOx5/u3tm3+wO9s2epPcPvoFDDG195N3IbTUasmOcsNPP6sdw8QznF5fjqwmm1GdXvAZumGVXZhXfgvwWd2NrcA8Ygu8kndEDe8STPWNG/Ed8hjUBXl4xJ7+f3fuo6dx4my8f9eNr/xjv4IMgs1Yai0cAAAAASUVORK5CYII=" alt="" />
  <BR>
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
  var mints = <?php echo $gmints;?>;
  var tracks = [];
  var QRCode = new QRCode("qrcode");
  function openPopup() {
    ovl.style.width = "100%";
    document.getElementById("map").style.display='none';
  }
  function closePopup() {
    ovl.style.width = "0%";
    document.getElementById("map").style.display='block';
  }
  function loadMap() {
    window.map = L.map('map').setView([45.1696, 5.724637], 12);
    map.on('mousemove', function(e) {
      setCursorFromMap(e.latlng);
    });
    map.on('zoomend', function() {
      if (window.nearcircle) {
        const radius = getNearCircleRadius(nearcircle.getLatLng().lat);
        nearcircle.setRadius(radius)
      }
    });
    let osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    let opentopomap = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      maxZoom: 17,
      attribution: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'
    });
    
    let googleSat = L.tileLayer('https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',{
      maxZoom: 20,
      subdomains:['mt0','mt1','mt2','mt3']
    });

    /*let esriSat = L.tileLayer(
        'http://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; <a href="http://www.esri.com/">Esri</a>, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        maxZoom: 18,
        });*/
  
    var baseMaps = {};
    baseMaps["Satellite"] = googleSat;
    baseMaps["Open Street Map"] = osm;
    baseMaps["Topographique"] = opentopomap;
    //baseMaps["ESRI"] = esriSat;
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
      if (err?.message != 'The user aborted a request.')
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
        t.pts.sort((a,b) => b.time-a.time);
        window.tracks.push(t);
      } else if (t.pts.length>0) {
        t.pts.forEach(pt => cible.pts.push(pt));
        cible.pts.sort((a,b) => b.time-a.time);
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
      if (!ucolors.hasOwnProperty(t.name)) {
        ucolors[t.name] = selectColor(window.index??0);
        window.index = (window.index??0)+5;
      }
      if (t.pts.length>0 && t.pts[0].time > mints) mints = t.pts[0].time;
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
      if (t.pts.length <= 0) {
        if (Object.hasOwn(umarkers, t.name)) {
          map.removeLayer(umarkers[t.name]);
          delete umarkers[t.name];
          removeNearCircle(t.name);
        }
      } else {
        let segm = [t.pts];
        if (cbholetracks.checked) {
          segm = [];
          let lastind=0;
          let i=1;
          for (; i<t.pts.length; i++) {
            if (distance(t.pts[i-1].lat, t.pts[i-1].lon, t.pts[i].lat, t.pts[i].lon) > 1) {
              if (i-lastind > 1)
                segm.push(t.pts.slice(lastind, i-1));
              lastind=i;
            }
          }
          if (i-lastind > 1)
            segm.push(t.pts.slice(lastind, i-1));
        }
        segm.forEach(seg => {
          let latlngs = seg.map(pt => [pt.lat, pt.lon]);
          plines.push(L.polyline(latlngs, {color: ucolors[t.name]}).addTo(map));
        });
        let alt = t.pts[0].alt;
        if (typeof alt !== 'number') alt = '';
        else alt = ` à ${Math.round(alt*10)/10}m`;
        let date = new Date(t.pts[0].time*1000);
        //date = `${date.toLocaleString()} (${Math.round((now-date.getTime()/1000)/60)}min)`;
        date = `${date.toLocaleString()} (il y a ${secondsToString(now-date.getTime()/1000)})`;
        let shareicon = '<'+'?xml version="1.0" encoding="iso-8859-1"?><svg fill="#000000" height="12px" width="12px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"  viewBox="0 0 458.624 458.624" xml:space="preserve"><g><g><path d="M339.588,314.529c-14.215,0-27.456,4.133-38.621,11.239l-112.682-78.67c1.809-6.315,2.798-12.976,2.798-19.871 c0-6.896-0.989-13.557-2.798-19.871l109.64-76.547c11.764,8.356,26.133,13.286,41.662,13.286c39.79,0,72.047-32.257,72.047-72.047 C411.634,32.258,379.378,0,339.588,0c-39.79,0-72.047,32.257-72.047,72.047c0,5.255,0.578,10.373,1.646,15.308l-112.424,78.491 c-10.974-6.759-23.892-10.666-37.727-10.666c-39.79,0-72.047,32.257-72.047,72.047s32.256,72.047,72.047,72.047 c13.834,0,26.753-3.907,37.727-10.666l113.292,79.097c-1.629,6.017-2.514,12.34-2.514,18.872c0,39.79,32.257,72.047,72.047,72.047 c39.79,0,72.047-32.257,72.047-72.047C411.635,346.787,379.378,314.529,339.588,314.529z"/></g></g></svg>';
        let pptext = `Dernière position pour <a href="${rootfolder}/filter/${t.name}" title="filtrer pour cet utilisateur">${t.name}</a> @ ${date}<BR>position : <a href="#" onclick="sharePos('${t.name}')" title="partager la position">${Math.round(t.pts[0].lat*10000)/10000} / ${Math.round(t.pts[0].lon*10000)/10000}${alt}</a><BR> <a href="#" onclick="downloadGPX('${t.name}')">GPX</a>&nbsp;&nbsp;&nbsp;<a href="#" onclick="sharePos('${t.name}')" title="partager la position">${shareicon}</a>`;
        if (user == t.name) pptext += '<BR><a href="<?php echo $ROOTFOLDER;?>">supprimer le filtre sur cet utilisateur</a>';
        if (Object.hasOwn(umarkers, t.name)) umarkers[t.name].setLatLng(segm[0][0]).setPopupContent(pptext);
        else {
          umarkers[t.name] = L.marker(segm[0][0]).addTo(map).bindPopup(pptext);
          if (cbfollow.checked || cbcadrer.checked) umarkers[t.name].openPopup();
        }
      }
    });
    // suppression des traces qui n'existent plus
    Object.keys(umarkers).filter(kt => !tracks.some(t => t.name == kt)).forEach(kt => {
      map.removeLayer(umarkers[kt]);
      delete umarkers[kt];
      delete ucolors[kt];
      removeNearCircle(kt);
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
  function selectColor(colorNum = 0){
    colors = 25;
    return "hsl(" + (colorNum * (360 / colors) % 360) + ",100%,50%)";
  }
  function distance(lat1,lon1,lat2,lon2) {
    var R = 6371; // Radius of the earth in km
    var dLat = deg2rad(lat2-lat1);  // deg2rad below
    var dLon = deg2rad(lon2-lon1); 
    var a = 
      Math.sin(dLat/2) * Math.sin(dLat/2) +
      Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
      Math.sin(dLon/2) * Math.sin(dLon/2); 
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
    var d = R * c; // Distance in km
    return d;
    let c1 = L.latLng(lat1, lon1);
    let c2 = L.latLng(lat2, lon2);
    return c1.distanceTo(c2)/1000;
  }
  function deg2rad(deg) {
    return deg * (Math.PI/180)
  }
  function timestampToDate(ts) {
    let time = new Date(ts*1000).toISOString();
    const regtime = /(.*)\.\d+Z$/gm;
    if (regtime.test(time)) {
      time = time.replaceAll(regtime, '$1Z');
    }
    return time;
  }
  function secondsToString(secs) {
    if (secs<60)
      return secs + ' seconde(s)';
    if (secs<3600)
      return Math.round(secs/60) + ' minute(s)';
    if (secs<86400) // 24h
      return Math.round(secs/3600) + ' heure(s)';
    return Math.round(secs/86400) + ' jour(s)';
  }
  function downloadFile(fileName, bytes, mime) {
    let blob = new Blob([bytes], { type: mime });
    let link = document.createElement('a');
    link.href = window.URL.createObjectURL(blob);
    link.download = fileName;
    link.click();
  }
  function downloadGPX(user) {
    let prefix = '<'+'?xml version="1.0"?><gpx creator="SimpleTracker" version="1.1" xmlns="http://www.topografix.com/GPX/1/1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd" xmlns:ns3="http://www.garmin.com/xmlschemas/TrackPointExtension/v1"><trk><trkseg>';
    let suffix = '</trkseg></trk></gpx>';
    let pts = tracks.find(t => t.name == user)?.pts;
    if (!pts) return;
    let data = prefix+pts.map(pt => `<trkpt lat="${pt.lat}" lon="${pt.lon}"><time>${timestampToDate(pt.time)}</time><ele>${pt.alt}</ele></trkpt>`)+suffix;
    downloadFile(user+'.gpx', data, 'application/gpx+xml');
  }
  function sharePos(user) {
    let pts = tracks.find(t => t.name == user)?.pts;
    if (pts && pts.length <= 0) return;
    let pt = pts[0];
    //https://www.google.com/maps/search/?api=1&query=lat,lon
    //console.log(JSON.stringify(pt));
    //{"time":1704730814,"lat":44.94515645317733,"lon":5.6191750802099705,"alt":824.1051}
    let urlggmaps = `https://www.google.com/maps/search/?api=1&query=${pt.lat},${pt.lon}`;
    let ggmaps = () => {
      try {
        window.open(urlggmaps, '_blank').focus();
      } catch(err) {
        console.log(err);
        window.location = urlggmaps;
      }
    }
    navigator.permissions.query({ name: "clipboard-write" }).then((result) => {
      if (result.state === "granted" || result.state === "prompt") {
        navigator.clipboard.writeText(`latitude : ${pt.lat}\nlongitude : ${pt.lon}\n\n`+urlggmaps).then(null, ggmaps);
      } else ggmaps();
    });
  }
  function openQR() {
    qrres.style.display = 'none';
    formqr.style.display = 'initial';
    openPopup();
  }
  function genQR(user = null, pwd = null) {
    if ((user = user ?? prompt('entrez le nom d\'utilisateur')) && (pwd = pwd ?? prompt('entrez le mot de passe'))) {
      const headers = new Headers({
        "Content-Type": "application/x-www-form-urlencoded"
      });
      const urlencoded = new URLSearchParams({
        "pwd": pwd
      });
      fetch(rootfolder+"/geturl/"+user, {method: "POST", headers: headers, body: urlencoded})
      .then(response => {
        return response.text();
      })
      .then(data => {
        if (data.startsWith('http')) {
          qruser.value = 'username';
          qrpwd.value = 'password';
          qrtext.value = data;
          QRCode.makeCode(data);
          formqr.style.display = 'none';
          qrres.style.display = 'initial';
        }
        else alert(data);
      })
      .catch((err) => {
        alert(`fetchTracks error: ${err.message}`);
      });
    }
  }
  function removeNearCircle(name) {
    if (window.nearest && nearest.name == name && window.nearcircle) {
      map.removeLayer(nearcircle);
      nearcircle = nearest = null;
    }
  }
  function getNearCircleRadius(lat) {
    const zoom = map.getZoom();
    const metersPerPixel = 156543.03392 * Math.cos(lat * Math.PI / 180) / Math.pow(2, zoom)
    // Multiply that by a factor based on how large the circle should appear on the screen
    return metersPerPixel * 5;
  }
  function setCursorFromMap(latlng) {
    if (typeof window.tracks === 'undefined' || !Array.isArray(window.tracks)) return;
    let nearest = tracks.filter(t => t.pts.length).map(t => {return {'name': t.name, 'nearpt': t.pts.reduce(function(a, b) {
      return !!a.lat && distance(latlng.lat, latlng.lng, a.lat , a.lon) < distance(latlng.lat, latlng.lng, b.lat, b.lon) ? a : b;
    }, [])};}).map(pt => {pt.distance=distance(latlng.lat, latlng.lng, pt.nearpt.lat , pt.nearpt.lon);return pt;}).reduce(function(a, b) {
      return a.distance < b.distance ? a : b;
    }, []);
    /*let nearest = tracks.filter(t => t.pts.length).map(t => {return {'name': t.name, 'nearpt': t.pts.slice().reduce(function(a, b) {
      if (typeof a?.lat === 'number') a.distance = distance(latlng.lat, latlng.lng, a.lat , a.lon);
      b.distance = distance(latlng.lat, latlng.lng, b.lat , b.lon);
      return !!a?.lat && a.distance < b.distance ? a : b;
    }, null)};}).reduce(function(a, b) {
      return (typeof a?.nearpt?.distance === 'number') && a.nearpt.distance < b.nearpt.distance ? a : b;
    }, null);*/
    if (typeof nearest?.distance !== 'number') return;
    window.nearest = nearest;
    let c1 = map.latLngToLayerPoint(latlng);
    let c2 = map.latLngToLayerPoint(L.latLng(nearest.nearpt.lat, nearest.nearpt.lon));
    let dist = Math.sqrt(Math.pow(c2.x-c1.x, 2) + Math.pow(c2.y-c1.y, 2));
    if (dist>100) {
      removeNearCircle(nearest.name);
      return;
    }
    //if (L.latLng(nearest.lat, nearest.lon).distanceTo(latlng)>5000) return;
    let ptdate = new Date(nearest.nearpt.time*1000).toLocaleString('fr-FR', { /*timeZone: 'UTC'*/ })/*.substr(-8, 8)*/;
    let mktxt = `${nearest.name}@${ptdate}`;
    //mktxt+='&nbsp;:&nbsp;'+nearest.alt+'m';
    //mktxt+='<BR>' + nearest.vz+'m/s&nbsp;&nbsp;&nbsp;'+nearest.vx+'km/h';
    //marker.bindPopup(mktxt).setLatLng([nearest.lat, nearest.lon]).update();
    //graph.setPos(nearest);
    //updateIcon(nearest);
    const radius = getNearCircleRadius(nearest.nearpt.lat);
    if (!window.nearcircle) {
      window.nearcircle = L.circle([nearest.nearpt.lat,nearest.nearpt.lon], {radius: radius})
      .bindTooltip(mktxt, {permanent: true, className: "my-label", offset: [0, 0] })
      .addTo(map);
    } else {
      nearcircle.setLatLng([nearest.nearpt.lat,nearest.nearpt.lon]);
      nearcircle.setRadius(radius);
      nearcircle.setTooltipContent(mktxt);
    }
  }
  function resetmints() {
    if (window.controller) controller.abort();
    launchFetchLoop();
    mints=<?php echo $gmints;?>;
  }
  function launchFetchLoop() {
    if (window.fetchtimer) window.clearTimeout(fetchtimer);
    if (window.controller) controller.abort();
    fetchTracks();
    window.fetchtimer = window.setInterval(fetchTracks, 5000);
  }

  //console.log(distance(44.79271 , 5.612266, 44.800738 , 5.580474));//2.66km
  loadMap();
  launchFetchLoop();
  </script>
</body>
</html>
