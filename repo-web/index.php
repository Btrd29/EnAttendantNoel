<?php

/**
 *  Developed with love by Nicolas Devenet <nicolas[at]devenet.info>
 *  Code hosted on https://github.com/Devenet/AdventCalendar
 *
 *  Upgraded with passion by Benoit Richard to fit my content and get pages through POST methods
 *  New code hosted on https://github.com/Btrd29/EnAttendantNoel
 */

error_reporting(0);

// constants to be used
define('VERSION', '1.4.0');
define('ADVENT_CALENDAR', 'Advent Calendar');
define('URL_DAY', 'day');
define('URL_PHOTO', 'photo');
define('URL_ABOUT', 'about');
define('URL_RSS', 'rss');
define('PRIVATE_FOLDER', './private');
define('SETTINGS_FILE', PRIVATE_FOLDER.'/settings.json');
define('CALENDAR_FILE', PRIVATE_FOLDER.'/calendar.json');
define('RSS_CACHE_FILE', PRIVATE_FOLDER.'/rss_cache.xml');

// load settings from file
if (file_exists(SETTINGS_FILE)) {
	$settings = json_decode(file_get_contents(SETTINGS_FILE));

	define('TITLE', $settings->title);
	define('YEAR', $settings->year);

	// is it an other month?
	if (isset($settings->month) && !empty($settings->month) && $settings->month > 1 && $settings->month <= 12) { define('MONTH', date('m', mktime(0, 0, 0, $settings->month+0))); }
	else { define('MONTH', 12); }
	// is it an other begin day?
	if (isset($settings->first_day) && !empty($settings->first_day) && $settings->first_day > 0 && $settings->first_day <= 31) { define('FIRST_DAY', date('d', mktime(0, 0, 0, MONTH, $settings->first_day))); }
	else { define('FIRST_DAY', '01'); }
	// is it an other last day?
	if (isset($settings->last_day) && !empty($settings->last_day) && $settings->last_day > FIRST_DAY && $settings->last_day <= 31) { define('LAST_DAY', date('d', mktime(0, 0, 0, MONTH, $settings->last_day))); }
	else { define('LAST_DAY', '24'); }

	// is it a private calendar?
	if (isset($settings->passkey) && !empty($settings->passkey)) { define('PASSKEY', $settings->passkey); }

	// do the user want an other background?
	if (isset($settings->background) && $settings->background == 'alternate') { define('ALTERNATE_BACKGROUND', TRUE); }

	// do the user want a custom disclaimer?
	if (isset($settings->disclaimer) && !empty($settings->disclaimer)) {
		define('DISCLAIMER', $settings->disclaimer == 'none' ? NULL : $settings->disclaimer);
	} else {
		define('DISCLAIMER', 'Content has been added by the site owner.');
	}

	// want to add disqus thread?
	if (isset($settings->disqus_shortname) && !empty($settings->disqus_shortname)) {
		AddOns::Register(AddOns::AddOn('disqus', $settings->disqus_shortname));
		AddOns::JavaScriptRegistred();
	}
	// want to add google analytics?
	if (isset($settings->google_analytics) && !empty($settings->google_analytics) && isset($settings->google_analytics->tracking_id) && isset($settings->google_analytics->domain) ) {
		AddOns::Register(AddOns::AddOn('ga', AddOns::JsonToArray($settings->google_analytics)));
		AddOns::JavaScriptRegistred();
	}
	// want to add piwik?
	if (isset($settings->piwik) && !empty($settings->piwik) && isset($settings->piwik->piwik_url) && isset($settings->piwik->site_id) ) {
		AddOns::Register(AddOns::AddOn('piwik', AddOns::JsonToArray($settings->piwik)));
		AddOns::JavaScriptRegistred();
	}
}
else { die('<!doctype html><html><head><title>'.ADVENT_CALENDAR.'</title><style>body{width:600px;margin:50px auto 20px;}</style></head><body><div style="font-size:30px;"><strong>Oups!</strong> Settings file not found.</div><div><p>Edit <code>private/settings.example.json</code> to personnalize title and year and rename it <code>settings.json</code>.</p><p>If it is not already done, put your photos in the <code>private/</code> folder, and name them with the number of the day you want to illustrate.</p></div></body></html>'); }

// is the directory writable ?
if (!is_writable(realpath(dirname(__FILE__)))) die('<div><strong>Oups!</strong> Application does not have the right to write in its own directory <code>'.realpath(dirname(__FILE__)).'</code>.</div>');
// is the private folder already created? yes, with a .htaccess file
/*if (!is_dir(PRIVATE_FOLDER)) { mkdir(PRIVATE_FOLDER,0705); chmod($_CONFIG['data'],0705); }*/
// are photos deny from web access? [just in case]
if (!is_file(PRIVATE_FOLDER.'/.htaccess')) { file_put_contents(PRIVATE_FOLDER.'/.htaccess', 'Deny from all'); }
if (!is_file(PRIVATE_FOLDER.'/.htaccess')) die('<div><strong>Oups!</strong> Application does not have the right to write in its own directory <code>'.realpath(dirname(__FILE__)).'</code>.</div>');

/*
 *  Core classes
 */
abstract class AddOns {
	const Data = 'data';
	const Name = 'name';

	static private $addons = Array();

	static function Register(Array $addon) {
		if (empty($addon[self::Data])) { $addon[self::Data] = TRUE; }
		self::$addons[$addon['name']] = $addon[self::Data];
	}

	static function AddOn($name, $data = TRUE) {
		return array(self::Name => $name, self::Data => $data);
	}

	static function Found($name) {
		return isset(self::$addons[$name]);
	}

	static function Get($name) {
		if (! self::Found($name)) { return; }
		return self::$addons[$name];
	}

	static function JavaScriptRegistred() {
		self::Register(self::AddOn('js'));
	}

	static function JsonToArray($json) {
		return json_decode(json_encode($json), TRUE);
	}
}

abstract class Image {
	static function get($day) {

		$img = self::getInfo($day);

		if (!empty($img)) {
			header('Content-type: '.$img['type']);
			header('Content-disposition: filename="AdventCalendar-'.$day.'.'.$img['extension'].'"');
			exit(file_get_contents($img['path']));
		}

		header('Location: ./');
		exit();
	}

	static function getInfo($day) {
		// check if we can display the request photo
		if (Advent::acceptDay($day) && Advent::isActiveDay($day)) {
			$result['url'] = '?'.URL_PHOTO.'='.$day;

			$extensions = ['jpg', 'jpeg', 'png', 'gif'];
			foreach ($extensions as $extension) {
				$file = PRIVATE_FOLDER.'/'.$day.'.'.$extension;
				if (file_exists($file)) {
					$result['type'] = self::getMimeType($extension);
					$result['path'] = $file;
					$result['extension'] = $extension;
					return $result;
				}
			}

			// nothing found, default image
			$result['type'] = 'image/png';
			$result['path'] = './assets/img/404.png';
			$result['extension'] = 'png';
			return $result;
		}

		return NULL;
	}

	static private function getMimeType($extension) {
		switch($extension) {
			case 'jpg':
			case 'jpeg':
				return 'image/jpeg';
			case 'png':
				return 'image/png';
			case 'gif':
				return 'image/gif';
			default:
				return NULL;
		}
	}
}

class Day {
	public $day;
	public $active;
	public $url;
	public $title = NULL;
	public $legend = NULL;
	public $text = NULL;
	public $colorOrder;

	public function __default($day) {
		$this->day = $day;
		$this->active = Advent::isActiveDay($day);
		$this->url = '?'. URL_DAY .'='. ($this->day);
		$this->title = 'Day '.$day;
	}
	public function __construct($day, $title = NULL, $legend = NULL, $text = NULL) {
		$this->__default($day);
		if (!empty($title)) { $this->title = $title; }
		$this->legend = $legend;
		$this->text = $text;
	}
}

abstract class Advent {
	const BEFORE_ADVENT = -1;
	const CURRENT_ADVENT = 0;
	const AFTER_ADVENT = 1;

	static function state() {
		$now = date('Ymd');

		// if we are before the advent
		if ($now < YEAR.MONTH.FIRST_DAY) { return self::BEFORE_ADVENT; }
		// if we are after
		if ($now > YEAR.MONTH.LAST_DAY) { return self::AFTER_ADVENT; }
		// else we are currently in advent \o/
		return self::CURRENT_ADVENT;
	}

	static function acceptDay($day) {
		return $day >= FIRST_DAY && $day <= LAST_DAY;
	}

	static function isActiveDay($day) {
		$state = self::state();
		return ($state == self::CURRENT_ADVENT && $day <= date('d')) || $state == self::AFTER_ADVENT;
	}

	static private function getDayColorClass($colorOrder, $day, $active = FALSE) {
		$result = '';
		// is the day active ?
		if ($active) { $result .= 'active '; }
		// set a color for the background

		$result .= 'day-color-'.($colorOrder[$day%4]);

		return $result;
	}

	static function getDays() {
		$result = array();
		for ($i=FIRST_DAY+0; $i<=LAST_DAY; $i++) {
			$result[] = new Day($i);
		}
		return $result;
	}

	static function getFullDays() {
		$result = array();
		for ($i=FIRST_DAY+0; $i<=LAST_DAY; $i++) {
			$result[] = self::getDay($i);
		}
		return $result;
	}

	static function getDaysHtml() {

		$result = '
		<div class="container text-center">
			<div class="page-header"><h1>Des énigmes en attendant Noël !</h1></div>
			<p>Chaque jour, une nouvelle <a class="hide-this-please tip" data-placement="top" title="Elles t\'aideront à trouver l\'URL du jour">image</a>, derrière laquelle se cache une nouvelle <a class="hide-this-please tip" data-placement="bottom" title="Exemple d\'URL : dax-olotl.ovh/exemple/">énigme</a>.
			<br>À vous de les trouver puis de les résoudre !</p>
		</div>';

		$result .= '<div class="container days">';

		$colorOrderList = array("1", "2", "3", "4");
		shuffle($colorOrderList);

		foreach (self::getDays() as $d) {
			$d->colorOrder = $colorOrderList;
			if ($d->active) {
				$result .= '<a href="#" onclick="document.getElementById(\'post' . ($d->day) .'\').submit()" title="Day '. ($d->day) .'"';
			}
			else { $result .= '<div'; }
			$result .= ' class="day-row '. self::getDayColorClass($d->colorOrder, $d->day, $d->active) .'"><span>'. ($d->day) .'</span>';
			if ($d->active) { $result .= '</a>'; }
			else { $result .= '</div>'; }

			$result .= '<form class="calendar-form" id="post'. ($d->day) .'" action="./" method="post"> <input type="hidden" name="day" value="'. ($d->day) .'"/> </form>';
		}

		$result .= '
					</div>
					<br>
					<br>
					<div class="container text-center">
						<div class="panel panel-info">
							<div class="panel-body">
								<p>
									Pas assez de challenge pour vous ?
									<br>
									Allez donc faire un tour ici : <a href="https://gate-remote-access.dax-olotl.ovh/">G.R.A</a> !
									<br>
									Il s\'agit d\'un ancien projet sans aucun rapport avec ce calendrier.
									<br>
									Il contient toute une histoire et des énigmes bien plus corsées.
								</p>
							</div>
						</div>
					</div>';

		return $result;
	}

	static function getDay($day) {
		$title = NULL;
		$legend = NULL;
		$text = NULL;
		// check if we have info to display
		if (file_exists(CALENDAR_FILE)) {
			$file = json_decode(file_get_contents(CALENDAR_FILE));
			$day = $day == FIRST_DAY ? ($day+0) : $day;
			if (!empty($file->{$day})) {
				if (!empty($file->{$day}->title)) { $title = htmlspecialchars($file->{$day}->title); }
				if (!empty($file->{$day}->legend)) { $legend = htmlspecialchars($file->{$day}->legend); }
				if (!empty($file->{$day}->text)) { $text = $file->{$day}->text; }
			}
		}
		return new Day($day, $title, $legend, $text);
	}

	static function getDayHtml($day) {
		$result = '<div class="container day">';

		$d = self::getDay($day);
		$title = $d->title;
		$legend = $d->legend;
		$text = $d->text;
		$d->colorOrder = array("1", "2", "3", "4");
		shuffle($d->colorOrder);

		// set the day number block
		$result .= '<a href="#" onclick="document.getElementById(\'post' . ($day) .'\').submit()" class="day-row '. self::getDayColorClass($d->colorOrder, $day, TRUE) .'"><span>'. $day .'</span></a>';
		$result .= '<form class="calendar-form" id="post'. ($day) .'" action="./" method="post"> <input type="hidden" name="day" value="'. ($day) .'"/> </form>';
		// set the title
		$result .= '<h1><span>';
		if (!empty($title)) { $result .= $title; }
		else { $result .= 'Day '.$day; }
		$result .= '</span></h1>';
		// clearfix
		$result .= '<div class="clearfix"></div>';

		// display image
		$result .= '<div class="text-center"><img src="./?'.URL_PHOTO.'='. $day .'" class="img-responsive img-thumbnail" alt="Day '. $day .'" />';
		// do we have a legend?
		if (!empty($legend)) { $result .= '<p class="legend">&mdash; '.$legend.'</p>'; }
		$result .= '</div>';
		// clearfix
		$result .= '<div class="clearfix"></div>';

		// do we have a text?
		if (!empty($text)) { $result .= '<div class="text panel panel-default"><div class="panel-body">'.$text.'</div></div>'; }

		// we do not forget the pagination
		$result .= '<ul class="pager"><li class="previous';
		if (self::isActiveDay($day-1) && ($day-1)>=FIRST_DAY) {
			$result .= '"><a href="#" onclick="document.getElementById(\'post' . ($day-1) .'\').submit()" title="Day '. ($day-1) .'">';
			$result .= '<form class="calendar-form" id="post'. ($day-1) .'" action="./" method="post"> <input type="hidden" name="day" value="'. ($day-1) .'"/> </form>';
		}
		else { $result .= ' disabled"><a>'; }
		$result .= '<i class="glyphicon glyphicon-hand-left"></i></a></li><li class="next';
		if (self::isActiveDay($day+1) && ($day+1)<=LAST_DAY) {
			$result .= '"><a href="#" onclick="document.getElementById(\'post' . ($day+1) .'\').submit()" title="Day '. ($day+1) .'">';
			$result .= '<form class="calendar-form" id="post'. ($day+1) .'" action="./" method="post"> <input type="hidden" name="day" value="'. ($day+1) .'"/> </form>';
		}
		else { $result .= ' disabled"><a>'; }
		$result .= '<i class="glyphicon glyphicon-hand-right"></i></a></li></ul>';

		// we add disqus thread if supported
		if (AddOns::Found('disqus')) { $result .= '<div id="disqus_thread"></div>'; }

		return $result.'</div>';
	}

	function bePatient($day) {
		return '<div class="container error"><div class="panel panel-info"><div class="panel-heading"><h3 class="panel-title">Espèce de petit malin, on est pas encore le '. $day .' !</h3></div><div class="panel-body">Tu as l\'air d\'être pressé , mais <strong>soit patient</strong>, ça arrive bientôt. <a href="./" class="illustration text-center tip" title="home"><i class="glyphicon glyphicon-home"></i></a></div></div></div>';
	}

}

/*
 * Load template
 */
$template = NULL;
$template_title = NULL;

// need to display log form?
if (defined('PASSKEY') && isset($loginRequested)) {
	$template = '
	<div class="container text-center">
		<div class="page-header"><h1 class="text-danger">This is a private area!</h1></div>
		<p>Please sign in with your <span class="font-normal">passkey</span> to continue.</p>
		<form method="post" role="form" class="espace-lg form-inline">
			<div class="form-group"><input type="password" name="credential" id="credential" class="form-control input-lg" autofocus required /></div>
			<button type="submit" class="btn btn-default btn-lg tip" data-placement="right" data-title="sign in"><i class="glyphicon glyphicon-user"></i></button>
		</form>
	</div>';
}
// want to see a photo ?
else if (isset($_GET[URL_PHOTO])) { Image::get($_GET[URL_PHOTO]+0); }
// nothing asked, display homepage
else if (empty($_POST)) {
	$template = Advent::getDaysHtml();
}
// want to display a day
else if (isset($_POST['day'])) {
	$day = $_POST['day'] + 0;
	if (! Advent::acceptDay($day)) { header('Location: ./'); exit(); }
	if (Advent::isActiveDay($day)) {
		$template_title = Advent::getDay($day)->title;
		$template = Advent::getDayHtml($day);
	}
	else {
		$template_title = 'Soit patient !';
		$template = Advent::bePatient($day);
	}
}

// default template is 404
if (empty($template)) {
	$template = '<div class="container error"><div class="panel panel-danger"><div class="panel-heading"><h3 class="panel-title">404 Not Found</h3></div><div class="panel-body">The requested URL was not found on this server. <a href="./" class="illustration illustration-danger text-center tip" title="home"><i class="glyphicon glyphicon-home"></i></a></div></div></div>';
	$template_title = 'Not found';
	header('HTTP/1.1 404 Not Found', true, 404);
}

// helper
$authentificated = defined('PASSKEY') && isset($_SESSION['welcome']);

?><!doctype html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<title><?php echo (!empty($template_title) ? $template_title.' &middot; ' : '' ), TITLE; ?></title>

		<link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico" />
		<link rel="icon" type="image/png" href="assets/favicon.png" />

		<link href="assets/css/bootstrap.min.css" rel="stylesheet">
		<link href="assets/css/adventcalendar.css" rel="stylesheet">
		<link href="//fonts.googleapis.com/css?family=Lato:300,400,700" rel="stylesheet" type="text/css">

		<script type="text/javascript">
			var snow = {

				wind : 0,
				maxXrange : 100,
				minXrange : 10,
				maxSpeed : 2,
				minSpeed : 1,
				color : "#efeff7",
				char : "*",
				maxSize : 20,
				minSize : 8,

				flakes : [],
				WIDTH : 0,
				HEIGHT : 0,

				init : function(nb){
					var o = this,
					frag = document.createDocumentFragment();
					o.getSize();



					for(var i = 0; i < nb; i++){
						var flake = {
							x : o.random(o.WIDTH),
							y : - o.maxSize,
							xrange : o.minXrange + o.random(o.maxXrange - o.minXrange),
							yspeed : o.minSpeed + o.random(o.maxSpeed - o.minSpeed, 100),
							life : 0,
							size : o.minSize + o.random(o.maxSize - o.minSize),
							html : document.createElement("span")
						};

						flake.html.style.position = "absolute";
						flake.html.style.top = flake.y + "px";
						flake.html.style.left = flake.x + "px";
						flake.html.style.fontSize = flake.size + "px";
						flake.html.style.color = o.color;
						flake.html.appendChild(document.createTextNode(o.char));

						frag.appendChild(flake.html);
						o.flakes.push(flake);
					}

					document.body.appendChild(frag);
					o.animate();
				},

				animate : function(){
					var o = this;
					for(var i = 0, c = o.flakes.length; i < c; i++){
						var flake = o.flakes[i],
						top = flake.y + flake.yspeed,
						left = flake.x + Math.sin(flake.life) * flake.xrange + o.wind;
						if(top < o.HEIGHT - flake.size - 10 && left < o.WIDTH - flake.size && left > 0){
							flake.html.style.top = top + "px";
							flake.html.style.left = left + "px";
							flake.y = top;
							flake.x += o.wind;
							flake.life+= .01;
						}
						else {
							flake.html.style.top = -o.maxSize + "px";
							flake.x = o.random(o.WIDTH);
							flake.y = -o.maxSize;
							flake.html.style.left = flake.x + "px";
							flake.life = 0;
						}
					}
					setTimeout(function(){
						o.animate();
					},20);
				},

				random : function(range, num){
					var num = num?num:1;
					return Math.floor(Math.random() * (range + 1) * num) / num;
				},

				getSize : function(){
					this.WIDTH = document.body.clientWidth || window.innerWidth;
					this.HEIGHT = document.body.clientHeight || window.innerHeight;
				}

			};
		</script>
		<script type="text/javascript">
			window.onload = function(){
				snow.init(50);
			};
		</script>
		<script src="https://apis.google.com/js/platform.js"></script>
	</head>

	<body>

		<nav class="navbar navbar-default navbar-static-top" role="navigation">
		<div class="container">
			<a class="navbar-brand tip" href="./" title="Menu principal" data-placement="right"><i class="glyphicon glyphicon-home"></i> <?php echo TITLE; ?></a>

			<div class="navbar-right">
				<p class="nine-years-old-army-txt">Are you doing your part ?</p>
				<div class="nine-years-old-army">
					<span>SUBSCRIBE TO PEWDIEPIE !</span><span class="g-ytsubscribe" data-channel="PewDiePie" data-layout="default" data-theme="dark" data-count="hidden"></span>
				</div>
			</div>
		</div>
		</nav>

		<div class="background<?php if(defined('ALTERNATE_BACKGROUND')) { echo ' alternate-background'; } ?>">
			<?php
				echo $template;
			?>
		</div>

		<script src="assets/js/jquery.min.js"></script>
		<script src="assets/js/bootstrap.min.js"></script>
		<script src="assets/js/adventcalendar.js"></script>
	</body>
</html>
