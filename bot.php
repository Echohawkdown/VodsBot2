<?php
 
Class Bot {
	public function __construct() {
		require 'config.php';
		require 'lib/googl.php';
		require 'lib/snoopy.php';
		require 'lib/countries.php';
		$this->limit = 6;
		$this->snoopy = new \Snoopy;
		$this->user = Config::$User;
		$this->API = Config::$API;
		$this->Subreddit = Config::$Subreddit;
	}
 
	public function update() {
		$data = file_get_contents($this->API['gg']);
		$data = json_decode($data);
		$events['gg'] = $data->matches;
		$events = json_encode($events);
		$this->sort(json_decode($events, true));
	}
 
	public function login() {
			$this->snoopy->submit("http://reddit.com/api/login/".$this->User['user'], $this->User);
			$login = json_decode($this->snoopy->results);
			$this->snoopy->cookies['reddit_session'] = $login->json->data->cookie;
			$this->uh = $login->json->data->modhash;
	}
	public function sort($events = NULL) {
		if (!is_null($events)) {
			foreach ($events['gg'] as $key => $value) $events['gg'][$key]['match_time'] = strtotime($value['datetime']);
 
			// jD's API often returns NULL
			if (isset($events['jd']) && !is_null($events['jd']) && $events['jd'] != 'NULL') {
				$matches = array_merge($events['jd'], $events['gg']);
 
				usort($matches, function($key1, $key2) {
					$value1 = $key1['match_time'];
					$value2 = $key2['match_time'];
					return $value2 - $value1;
				});
			} else {
				usort($events['gg'], function($key1, $key2) {
					$value1 = $key1['match_time'];
					$value2 = $key2['match_time'];
					return $value1 - $value2;
				});
				$matches = $events['gg'];
			}
 
			$this->parse($matches);
		}
	}
 
	public function parse($matches) {
		$i = 0;
		foreach ($matches as $match) {
			if ($i < $this->limit) {
				// Time
				$time = "";
				$ticker[$i]['timestamp'] = $match['match_time'];
				$total_time = time() - $match['match_time'];
				if ($total_time < 0) $total_time = $match['match_time'] - time();
				$days       = floor($total_time / 86400);
				$hours      = floor(($total_time % 86400) / 3600);
				$minutes    = intval(($total_time % 3600) / 60);
				if($days > 0) $time .= $days . 'd ';
				if ($hours > 0) $time .= $hours.'h ';
				if ($minutes > 0) $time .= $minutes.'m';
 
				if ($match['isLive']) $time = 'live';
 
				$ticker[$i]['time'] = $time;
 
				// tournament name
				if (isset($match['coverage_title'])) $ticker[$i]['tournament'] = $match['coverage_title'];
				else if (isset($match['tournament']['name'])) $ticker[$i]['tournament'] = $match['tournament']['name'];
 
				// Teams
				if (isset($match['firstOpponent']['name']) && isset($match['secondOpponent']['name']))  $ticker[$i]['teams'] = $match['firstOpponent']['name'].' vs '.$match['secondOpponent']['name'];
 
				// URLs
				if (isset($match['pageUrl'])) $ticker[$i]['url']['gg'] = $match['pageUrl'];
				if (isset($match['coverage_url'])) $ticker[$i]['url']['jd'] = $match['coverage_url'];
 
				$i++;
			} else break;
		}
 
		$this->format($ticker);
	}
 
	public function format($ticker) {
		$tock = "";
		foreach ($ticker as $tick) {
			$url = $this->shortenUrl($tick['url']['gg']);
			// $url = $tick['url']['gg'];
			if ($tick['time'] == 'live') $tock .= "#\n [**LIVE - " . $tick['tournament'] . '**]('.$url.' "'.date('M d H:m T', $tick['timestamp']).'")  ';
			else $tock .= "#\n [".$tick['time'].' - ' . $tick['tournament'] . ']('.$url.' "'.date('M d H:m T', $tick['timestamp']).'")  ';
 
			$tock .= "\n[".$tick['teams']."](/hidden)\n\n";
		}
		$this->prepare($tock);
	}
 
	public function prepare($text) {
		$this->login();
		$this->snoopy->fetch('http://www.reddit.com/r/vodsbeta/wiki/sidebar.json');
		$description = json_decode($this->snoopy->results);
		$description = $description->data->content_md;
		$description = str_replace("&gt;", ">", $description);
		$description = str_replace('%%STATUS%%', '', $description);
		$description = str_replace("%%EVENTS%%", $text, $description);
		$this->dump($description);
		$this->post($description);
	}
 
	protected function post($description) {
		$this->snoopy->fetch('http://reddit.com/r/vodsbeta/about/edit/.json');
		$this->dump($this->snoopy->results);
		$about = json_decode($this->snoopy->results);
		$data = $about->data;
 
		$parameters['sr'] = 't5_2xu9u';
		$parameters['title'] = $data->title;
		$parameters['public_description'] = $data->public_description;
		$parameters['lang'] = $data->language;
		$parameters['type'] = $data->subreddit_type;
		$parameters['link_type'] = $data->content_options;
		$parameters['wikimode'] = $data->wikimode;
		$parameters['wiki_edit_karma'] = $data->wiki_edit_karma;
		$parameters['wiki_edit_age'] = $data->wiki_edit_age;
		$parameters['allow_top'] = 'on';
		$parameters['header-title'] = '';
		$parameters['id'] = '#sr-form';
		$parameters['r'] = 'vodsbeta';
		$parameters['renderstyle'] = 'html';
		$parameters['comment_score_hide_mins'] = $data->comment_score_hide_mins;
		$parameters['public_traffic'] = 'on';
		$parameters['spam_comments'] = 'low';
		$parameters['spam_links'] = 'low';
		$parameters['spam_selfposts'] = 'low';
		$parameters['link_type'] = 'any';
		$parameters['description'] = $description;
		$parameters['uh'] = $this->uh;
 
		$this->snoopy->submit("http://www.reddit.com/api/site_admin?api_type=json", $parameters);
		$this->dump($this->snoopy->results);
	}
 
	protected function gosugamers() {
		$data = file_get_contents($this->API['gg']);
		$data = json_decode($data);
		return $data->matches;
	}
	private function shortenUrl($url){
		$googl = new \Googl($this->API['googl']);
	    $cacheFile = 'cache' . DIRECTORY_SEPARATOR . md5($url);
	    if (file_exists($cacheFile)) {
	        $fh = fopen($cacheFile, 'r');
	        $cacheTime = trim(fgets($fh));
	        // if data was cached recently, return cached data
	        if ($cacheTime > strtotime('-15 minutes')) {
	            return = fread($fh,filesize($cacheFile));
	        }
	        // else delete cache file
	        fclose($fh);
	        unlink($cacheFile);
	    }
	    $short = $googl->shorten($url);
	    $fh = fopen($cacheFile, 'w');
	    fwrite($fh, time() . "\n");
	    fwrite($fh, $json);
	    fclose($fh);
        return $short;
	}
 
	private function dump($str) {
		echo "<code>$str</code>";
	}
}
?>