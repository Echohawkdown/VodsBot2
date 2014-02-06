<?php
 
Class Bot {
	public function __construct() {
		require 'config.php';
		require 'lib/googl.php';
		require 'lib/snoopy.php';
		require 'lib/countries.php';
		$this->limit = 6;
		$this->snoopy = new \Snoopy;
		$this->User = Config::$User;
		$this->API = Config::$API;	
	}
 	public function run(){
 		$events = $this->update();
 		$sorted_events = $this->sort($events);
 		$parsed_events = $this->parse($sorted_events);
 		$formatted_events = $this->format($parsed_events);
 		$description = $this->prepare($formatted_events);
 		$this->post($description);
 		echo "Update complete. Complete description code generated: \n<pre>$description</pre>";
 	}
	public function update() {
		$data = file_get_contents($this->API['gg']);
		$data = json_decode($data);
		$events['gg'] = $data->matches;
		$events = json_encode($events);
		return json_decode($events, true);
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
 
			return $matches;
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
				if (isset($match['firstOpponent']['shortName']) && isset($match['secondOpponent']['shortName']))  $ticker[$i]['teams'] = '**'.$match['firstOpponent']['shortName'].'** vs **'.$match['secondOpponent']['shortName'].'**';
 
				// URLs
				if (isset($match['pageUrl'])) $ticker[$i]['url']['gg'] = $match['pageUrl'];
				//if (isset($match['coverage_url'])) $ticker[$i]['url']['jd'] = $match['coverage_url'];
				if(strpos($ticker[$i]['url']['gg'], "playoff") !== false) $ticker[$i]['spoiler'] = true;
				else if (strpos($ticker[$i]['url']['gg'], "top") !== false) $ticker[$i]['spoiler'] = true;
				else if (strpos($ticker[$i]['url']['gg'], "Ro8") !== false) $ticker[$i]['spoiler'] = true;
				else if (strpos($ticker[$i]['url']['gg'], "final") !== false) $ticker[$i]['spoiler'] = true;
				else if (strpos($ticker[$i]['url']['gg'], "Final") !== false) $ticker[$i]['spoiler'] = true;
 				else $ticker[$i]['spoiler'] = false;
				$i++;
			} else break;
		}
 
		return $ticker;
	}
 
	public function format($ticker) {
		$tock = "";
		foreach ($ticker as $tick) {
			$url = $this->shortenUrl($tick['url']['gg']);
			// $url = $tick['url']['gg'];
			if ($tick['time'] == 'live') $tock .= "#\n [**LIVE - " . $tick['tournament'] . '**]('.$url.' "'.date('M d H:m T', $tick['timestamp']).'")  ';
			else $tock .= "#\n [**".$tick['time'].'** - ' . $tick['tournament'] . ']('.$url.' "'.date('M d H:m T', $tick['timestamp']).'")  ';
 			if($tick['spoiler'] == true)	$tock .= "\n[".$tick['teams']."](/hidden)\n\n";
			else $tock .= "\n".$tick['teams']."\n\n";
		}
		return $tock;
	}
 
	public function prepare($text) {
		$this->login();
		$this->snoopy->fetch('http://www.reddit.com/r/loleventvods/wiki/sidebar.json');
		$description = json_decode($this->snoopy->results);
		$description = $description->data->content_md;
		$description = str_replace("&gt;", ">", $description);
		$description = str_replace('%%STATUS%%', '', $description);
		$description = str_replace("%%EVENTS%%", $text, $description);
		return $description;
	}
 
	protected function post($description) {
		$this->snoopy->fetch('http://reddit.com/r/loleventvods/about/edit/.json');
		$about = json_decode($this->snoopy->results);
		$data = $about->data;
		$parameters['sr'] = 't5_2ux5s';
		$parameters['title'] = $data->title;
		$parameters['public_description'] = $data->public_description;
		$parameters['lang'] = $data->language;
		$parameters['type'] = $data->subreddit_type;
		$parameters['link_type'] = 'self';
		$parameters['wikimode'] = $data->wikimode;
		$parameters['wiki_edit_karma'] = $data->wiki_edit_karma;
		$parameters['wiki_edit_age'] = $data->wiki_edit_age;
		$parameters['allow_top'] = 'on';
		$parameters['header-title'] = '';
		$parameters['id'] = '#sr-form';
		$parameters['r'] = 'loleventvods';
		$parameters['renderstyle'] = 'html';
		$parameters['comment_score_hide_mins'] = $data->comment_score_hide_mins;
		$parameters['public_traffic'] = 'on';
		$parameters['spam_comments'] = 'low';
		$parameters['spam_links'] = 'low';
		$parameters['spam_selfposts'] = 'low';
		$parameters['description'] = $description;
		$parameters['uh'] = $this->uh;
 		$parameters['show_media'] = 'on';
		$this->snoopy->submit("http://www.reddit.com/api/site_admin?api_type=json", $parameters);
	}
 
	protected function gosugamers() {
		$data = file_get_contents($this->API['gg']);
		$data = json_decode($data);
		return $data->matches;
	}
	private function shortenUrl($url){
		$googl = new \Googl($this->API['googl']);
	    $short = $googl->shorten($url);
	    return $short;
	}
}
?>
