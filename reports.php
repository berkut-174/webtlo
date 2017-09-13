<?php

function create_reports($subsections, $topics, $nick, $limit){
	$tmp = array();
	$max = 119000;
	$pattern = '[spoiler="№№ %%start%% — %%end%%"]<br />[list=1]<br />[*=%%start%%]%%list%%[/list]<br />[/spoiler]<br />';
	$update_time = Db::query_database( "SELECT ud FROM Other", array(), true, PDO::FETCH_COLUMN );
	$length = mb_strlen($pattern, 'UTF-8');
	foreach($topics as $topic){
		if($topic['dl'] == 1 && $topic['ss'] != 0 && $topic['avg'] <= $limit){
			if(!isset($tmp[$topic['ss']])){
				$tmp[$topic['ss']]['start'] = 1;
				$tmp[$topic['ss']]['lgth'] = 0;
				$tmp[$topic['ss']]['dlsi'] = 0;
				$tmp[$topic['ss']]['dlqt'] = 0;
				$tmp[$topic['ss']]['qt'] = 0 ;
			}
			$str = '[url=viewtopic.php?t='.$topic['id'].']'.$topic['na'].'[/url] '.convert_bytes($topic['si']);
			$lgth = mb_strlen($str, 'UTF-8');
			$current = $tmp[$topic['ss']]['lgth'] + $lgth;
			$available = $max - $length - ($tmp[$topic['ss']]['qt'] - $tmp[$topic['ss']]['start'] + 1) * 3;
			if($current > $available){
				$text = str_replace('%%start%%', $tmp[$topic['ss']]['start'], $pattern);
				$text = str_replace('%%end%%', $tmp[$topic['ss']]['qt'], $text);
				$tmp[$topic['ss']]['msg'][]['text'] = str_replace('%%list%%', implode('<br />[*]', $tmp[$topic['ss']]['str']), $text);
				$tmp[$topic['ss']]['start'] = $tmp[$topic['ss']]['qt'] + 1;
				$tmp[$topic['ss']]['lgth'] = 0;
				unset($tmp[$topic['ss']]['str']);
			}
			$tmp[$topic['ss']]['lgth'] += $lgth;
			$tmp[$topic['ss']]['str'][] = $str;
			$tmp[$topic['ss']]['qt']++;
			$tmp[$topic['ss']]['dlsi'] += $topic['si'];
			$tmp[$topic['ss']]['dlqt']++;
		}
	}
	$common = 'Актуально на: [b]' . date('d.m.Y', $update_time[0]) . '[/b][br][br]<br /><br />'.
			  'Общее количество хранимых раздач: [b]%%dlqt%%[/b] шт.[br]<br />'.
			  'Общий вес хранимых раздач: [b]%%dlsi%%<br />[hr]<br />';
	$dlqt = 0;
	$dlsi = 0;
	$exclude = explode( ',', TIniFileEx::read( 'reports', 'exclude', "" ) );
	foreach($subsections as &$subsection){
		if( !isset($tmp[$subsection['id']]) || in_array($subsection['id'], $exclude) ) continue;
		if($tmp[$subsection['id']]['lgth'] != 0){
			$text = str_replace('%%start%%', $tmp[$subsection['id']]['start'], $pattern);
			$text = str_replace('%%end%%', $tmp[$subsection['id']]['qt'], $text);
			$tmp[$subsection['id']]['msg'][]['text'] = str_replace('%%list%%', implode('<br />[*]', $tmp[$subsection['id']]['str']), $text);
		}
		$subsection['messages'] = $tmp[$subsection['id']]['msg'];
		$dlqt += $subsection['dlqt'] = $tmp[$subsection['id']]['dlqt'];
		$dlsi += $subsection['dlsi'] = $tmp[$subsection['id']]['dlsi'];
		$info = 'Актуально на: [color=darkblue]' . date('d.m.Y', $update_time[0]) . '[/color][br]<br />'.
				'Всего хранимых раздач в подразделе: ' . $subsection['dlqt'] . ' шт. / ' . convert_bytes($subsection['dlsi']) . '<br />';
		$subsection['messages'][0]['text'] = $info . $subsection['messages'][0]['text'];
		$header = '[url=viewforum.php?f='.$subsection['id'].'][u][color=#006699]'.preg_replace( '|.*» ?(.*)$|', '$1', $subsection['na'] ).'[/u][/color][/url] '.
				  '| [url=tracker.php?f='.$subsection['id'].'&tm=-1&o=10&s=1&oop=1][color=indigo][u]Проверка сидов[/u][/color][/url][br][br]<br /><br />'.
				  'Актуально на: [color=darkblue]'. date('d.m.Y', $update_time[0]) . '[/color][br]<br />'.
				  'Всего раздач в подразделе: ' . $subsection['qt'] .' шт. / ' . convert_bytes($subsection['si']) . '[br]<br />'.
				  'Всего хранимых раздач в подразделе: %%dlqt%% шт. / %%dlsi%%[br]<br />'.
				  'Количество хранителей: %%count%%<br />[hr]<br />'.
				  'Хранитель 1: [url=profile.php?mode=viewprofile&u='.urlencode( $nick ).'&name=1][u][color=#006699]'.$nick.'[/u][/color][/url] [color=gray]~>[/color] '. $subsection['dlqt'] .' шт. [color=gray]~>[/color] '. convert_bytes($subsection['dlsi']) .'[br]<br /><br />';
		$common .= '[url=viewtopic.php?p=%%ins' . $subsection['id'] . '%%#%%ins' .$subsection['id'] . '%%][u]'.$subsection['na'] . '[/u][/url] — ' .	$subsection['dlqt'] .' шт. ('. convert_bytes($subsection['dlsi']) . ')[br]<br />';
		$subsection['header'] = $header;
	}
	$common = str_replace('%%dlqt%%', empty($dlqt) ? 0 : $dlqt, $common);
	$common = str_replace('%%dlsi%%', empty($dlsi) ? 0 : preg_replace('/ (?!.* )/', '[/b] ', convert_bytes($dlsi)), $common);
	$subsections['common'] = $common;
	return $subsections;
}

class Reports {
	
	protected $ch;
	protected $login;
	protected $forum_url;
	
	private $months = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
	private $months_ru = array( 'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек' );
	
	public function __construct($forum_url, $login, $paswd){
		include_once dirname(__FILE__) . '/php/phpQuery.php';
		$this->login = $login;
		$this->forum_url = $forum_url;
		UserDetails::$forum_url = $forum_url;
		UserDetails::get_cookie( $login, $paswd );
		$this->ch = curl_init();
	}
	
	private function make_request($url, $fields = array(), $options = array()){
		curl_setopt_array($this->ch, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_URL => $url,
			CURLOPT_COOKIE => UserDetails::$cookie,
			CURLOPT_POSTFIELDS => http_build_query($fields),
			CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36",
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 10

		));
		curl_setopt_array($this->ch, Proxy::$proxy);
		curl_setopt_array($this->ch, $options);

		$n = 1;
		$max_n = 50;
		$data = '';

		while($n <= $max_n){
			$data = curl_exec($this->ch);
			if($data === false){
				if($n <= $max_n){
					Log::append ( 'CURL ошибка: ' . curl_error($this->ch) . ' Повторная попытка ' . $n . '/' . $max_n . ' получить данные.' );
					$n++;
					continue;
				}
				else {
					throw new Exception( 'CURL ошибка: ' . curl_error($this->ch) );
				}
			}
			return $data;
		}
	}
	
	// поиск темы со списком
	public function search_topic_id( $title = "" ) {
		$title = html_entity_decode( $title );
		$search = preg_replace( '|.*» ?(.*)$|', '$1', $title );
		if( mb_strlen( $search, 'UTF-8' ) < 3 ) return false;
		$title = explode( ' » ', $title );
		$i = 0;
		$page = 1;
		$page_id = "";
		while( $page > 0 ) {
			$data = $this->make_request(
				$this->forum_url . "/forum/search.php?id=$page_id",
				array(
					'nm' => mb_convert_encoding( "$search", 'Windows-1251', 'UTF-8' ),
					'start' => $i,
					'f' => 1584
				)
			);
			$html = phpQuery::newDocumentHTML($data, 'UTF-8');
			$topic_main = $html->find('table.forum > tbody:first');
			$pages = $html->find('a.pg:last')->prev();
			if( !empty( $pages ) && $i == 0 ) {
				$page = $html->find('a.pg:last')->prev()->text();
				$page_id = $html->find('a.pg:last')->attr('href');
				$page_id = preg_replace( '|.*id=([^\&]*).*|', '$1', $page_id );
			}
			unset( $html );
			if( !empty( $topic_main ) ) {
				$topic_main = pq($topic_main);
				foreach( $topic_main->find('tr.tCenter') as $row ) {
					$row = pq($row);
					$topic_title = $row->find( 'a.topictitle' )->text();
					if( !empty( $topic_title ) ) {
						$topic_title = explode( '»', str_replace( '[Список] ', '', $topic_title ) );
						$topic_title = array_map( 'trim', $topic_title );
						$diff = array_diff( $title, $topic_title );
						if( empty( $diff ) ) {
							$topic_id = $row->find('a.topictitle')->attr('href');
							return preg_replace( '/.*?([0-9]*)$/', '$1', $topic_id );
						}
					}
				}
			}
			$page--;
			$i += 50;
		}
		return false;
	}
	
	public function search_all_stored_topics( $topic_id ) {
		$i = 0; // +30
		$page = 1; // количество страниц
		$now_date = new DateTime('now');
		$keepers = array();
		// получение данных со страниц
		while( $page > 0 ) {
			$data = $this->make_request( $this->forum_url . "/forum/viewtopic.php?t=$topic_id&start=$i" );
			$html = phpQuery::newDocumentHTML( $data, 'UTF-8' );
			$topic_main = $html->find('table#topic_main');
			$pages = $html->find('a.pg:last')->prev();
			if( !empty($pages) && $i == 0 )
				$page = $html->find('a.pg:last')->prev()->text();
			unset($html);
			if( !empty($topic_main) ) {
				$topic_main = pq($topic_main);
				foreach( $topic_main->find('tbody') as $row ) {
					$row = pq($row);
					$post_id = $row->attr('id');
					if( empty($post_id) ) continue;
					$posted = $row->find('.p-link')->text();
					$posted_since = $row->find('.posted_since')->text();
					if( preg_match('|(\d{2})-(\D{1,})-(\d{2,4}) (\d{1,2}):(\d{1,2})|', $posted_since, $since) )
						$posted = $since[0];
					$posted = str_replace( $this->months_ru, $this->months, $posted );
					$topic_date = DateTime::createFromFormat('d-M-y H:i', $posted);
					// пропускаем сообщение, если оно старше 30 дней
					if( $now_date->diff($topic_date)->format('%a') > 30 ) continue;
					// получаем id раздач хранимых другими хранителями
					foreach( $row->find('a.postLink') as $topic ) {
						$topic = pq($topic);
						if( preg_match('/viewtopic.php\?t=[0-9]+$/', $topic->attr('href')) ) {
							$keepers[] = preg_replace('/.*?([0-9]*)$/', '$1', $topic->attr('href'));
						}
					}
				}
			}
			$page--;
			$i += 30;
		}
		return $keepers;
	}
	
	private function send_message($mode, $message, $topic_id, $post_id = "", $subject = ""){
		$message = str_replace('<br />', '', $message);
		$message = str_replace('[br]', "\n", $message);
		// получение form_token
		if( empty(UserDetails::$form_token) ) UserDetails::get_form_token();
		$data = $this->make_request(
			$this->forum_url . '/forum/posting.php',
			array(
				't' => $topic_id,
				'mode' => $mode,
				'p' => $post_id,
				'subject' => mb_convert_encoding("$subject", 'Windows-1251', 'UTF-8'),
				'submit_mode' => "submit",
				'form_token' => UserDetails::$form_token,
				'message' => mb_convert_encoding("$message", 'Windows-1251', 'UTF-8')
			)
		);
		$html = phpQuery::newDocumentHTML($data, 'UTF-8');
		$msg = $html->find('div.msg')->text();
		if( !empty( $msg ) ) {
			Log::append ( "Error: $msg ($topic_id)." );
			return;
		}
		$post_id = $html->find('div.mrg_16 > a')->attr('href');
		if ( empty( $post_id ) ) {
			$msg = $html->find('div.mrg_16')->text();
			if ( empty( $msg ) ) {
				$msg = $html->find('h2')->text();
				if ( empty( $msg ) ) {
					$msg = 'Неизвестная ошибка';
				}
			}
			Log::append ( "Error: $msg ($topic_id)." );
			return;
		}
		$post_id = preg_replace('/.*?([0-9]*)$/', '$1', $post_id);
		return $post_id;
	}
	
	public function send_reports($api_key, $api_url, $subsections, $data = array()){
		Log::append ( 'Выполняется отправка отчётов на форум...' );
		$common = $subsections['common'];
		unset($subsections['common']);
		// получаем ссылки на списки
		foreach($data as $data){
			$links[$data['id']] = preg_replace('/.*?([0-9]*)$/', '$1', $data['ln']);
		}
		$send_exclude = explode( ',', TIniFileEx::read( 'reports', 'exclude', "" ) );
		// отправка отчётов по каждому подразделу
		foreach($subsections as &$subsection){
			if( !isset($subsection['messages']) || in_array($subsection['id'], $send_exclude) ) continue;
			if(empty($links[$subsection['id']])){
				Log::append( 'Для подраздела № ' . $subsection['id'] . ' не указана ссылка на список, выполняется автоматический поиск темы...' );
				$links[$subsection['id']] = $this->search_topic_id( $subsection['na'] );
				if( !$links[$subsection['id']] ) {
					Log::append ( 'Для подраздела № ' . $subsection['id'] . ' не удалось найти тему со списком, пропускаем...' );
					continue;
				}
				TIniFileEx::write( $subsection['id'], 'link', $links[$subsection['id']] );
			}
			$i = 0; // +30
			$j = 0; // количество своих сообщений
			$page = 1; // количество страниц
			// получение данных со страниц
			Log::append ( 'Поиск своих сообщений в теме для подраздела № ' . $subsection['id'] . '...' );
			while($page > 0){
				$data = $this->make_request(
					$this->forum_url . '/forum/viewtopic.php?t=' . $links[$subsection['id']]. '&start=' . $i
				);
				$html = phpQuery::newDocumentHTML($data, 'UTF-8');
				$topic_main = $html->find('table#topic_main');
				$pages = $html->find('a.pg:last')->prev();
				if(!empty($pages) && $i == 0)
					$page = $html->find('a.pg:last')->prev()->text();
				unset($html);
				if(!empty($topic_main)){
					$topic_main = pq($topic_main);
					if( $i == 0 ) {
						$nick_author = $topic_main->find('p.nick-author:first > a')->text();
						$post_author = str_replace('post_', '', $topic_main->find('tbody')->eq(1)->attr('id'));
					}
					foreach($topic_main->find('tbody') as $row){
						$row = pq($row);
						$post = str_replace('post_', '', $row->attr('id'));
						if($post_author != $post && !empty($post)){
							$nick = $row->find('p.nick > a')->text();
							if($nick == $this->login){
								$subsection['messages'][$j]['id'] = $post;
								$j++;
							} else {
								// получаем id раздач хранимых другими хранителями
								foreach($row->find('a.postLink') as $topic){
									$topic = pq($topic);
									if(preg_match('/viewtopic.php\?t=[0-9]+$/', $topic->attr('href'))){
										$keepers[$nick][] = preg_replace('/.*?([0-9]*)$/', '$1', $topic->attr('href'));
									}
								}
							}
						}
					}
				}
				$page--;
				$i += 30;
			}
			Log::append ( 'Найдено сообщений: ' . $j . '.' );
			// отправка шапки
			if($nick_author == $this->login){
				// получение данных о раздачах хранимых другими хранителями
				if(isset($keepers)){
					Log::append ( 'Сканирование сообщений других хранителей для подраздела № ' . $subsection['id'] . '...' );
					foreach($keepers as $nick => $ids){
						$webtlo = new Webtlo($api_url, $api_key);
						$topics = $webtlo->get_tor_topic_data($ids);
						$stored[$nick]['dlsi'] = 0;
						$stored[$nick]['dlqt'] = 0;
						foreach($topics as $topic){
							if($topic['forum_id'] == $subsection['id']){
								$stored[$nick]['dlsi'] += $topic['size'];
								$stored[$nick]['dlqt'] += 1;
							}
						}
						unset($topics);
					}
					// вставка в отчёты данных о других хранителях
					$q = 2;
					foreach($stored as $nick => $data){
						$subsection['header'] .= 'Хранитель '.$q.': [url=profile.php?mode=viewprofile&u='.urlencode( $nick ).'&name=1][u][color=#006699]'.$nick.'[/u][/color][/url] [color=gray]~>[/color] '. $data['dlqt'] .' шт. [color=gray]~>[/color] '. convert_bytes($data['dlsi']) .'[br]';
						$q++;
					}
				}
				Log::append ( 'Отправка шапки для подраздела № ' . $subsection['id'] . '...' );
				$count = isset($keepers) ? count($keepers) + 1 : 1;
				$dlqt = isset($stored) ? array_sum(array_column_common($stored, 'dlqt')) + $subsection['dlqt'] : $subsection['dlqt'];
				$dlsi = convert_bytes(isset($stored) ? array_sum(array_column_common($stored, 'dlsi')) + $subsection['dlsi'] : $subsection['dlsi']);
				$subsection['header'] = str_replace(
					array( '%%count%%', '%%dlqt%%', '%%dlsi%%' ),
					array( $count, $dlqt, $dlsi ),
					$subsection['header']
				);
				// отправка сообщения с шапкой
				$this->send_message(
					'editpost', $subsection['header'], $links[$subsection['id']], $post_author, '[Список] ' . $subsection['na']
				);
			}
			unset($keepers);
			unset($stored);
			// вставка дополнительных сообщений
			$q = 1;
			foreach($subsection['messages'] as &$message){
				if(empty($message['id'])){
					Log::append ( 'Вставка дополнительного ' . $q . '-ого сообщения для подраздела № ' . $subsection['id'] . '...' );
					$message['id'] = $this->send_message(
						'reply', '[spoiler]' . $q . str_repeat('?', 119981 - count($q)) . '[/spoiler]', $links[$subsection['id']]
					);
					$q++;
					usleep(1500);
				}
			}
			unset($message);
			// вставка ссылок в сводном отчёте на список
			$common = str_replace('%%ins' . $subsection['id'] . '%%', $subsection['messages'][0]['id'], $common);
			// редактирование сообщений
			foreach($subsection['messages'] as $message){
				if(!empty($message['id'])){
					Log::append ( 'Редактирование сообщения № ' . $message['id'] . ' для подраздела № ' . $subsection['id'] . '...' );
					$this->send_message(
						'editpost',	empty($message['text']) ? 'резерв' : $message['text'], $links[$subsection['id']], $message['id']
					);
				}
			}
		}
		TIniFileEx::updateFile();
		unset($subsection);
		// отправка сводного отчёта
		$send_common = TIniFileEx::read( 'reports', 'common', 1 );
		if( $send_common ) {
			Log::append ( 'Отправка сводного отчёта...' );
			$data = $this->make_request(
				$this->forum_url . '/forum/search.php',
				array('uid' => UserDetails::$uid, 't' => 4275633, 'dm' => 1)
			);
			$html = phpQuery::newDocumentHTML($data, 'UTF-8');
			$common_post = $html->find('.row1:first');
			unset($html);
			$post_id = empty($common_post) ? "" : preg_replace('/.*?([0-9]*)$/', '$1', pq($common_post)->find('.txtb')->attr('href'));
			$this->send_message(
				empty($post_id) ? 'reply' : 'editpost', $common, 4275633, $post_id
			);
		}
	}
	
	public function search_keepers ( $subsections ){
		Log::append ( 'Получение списка раздач хранимых другими хранителями...' );
		$keepers = array();
		foreach ( $subsections as &$subsection ) {
			if ( empty( $subsection['ln'] ) ) {
				$subsection['ln'] = $this->search_topic_id( $subsection['na'] );
				if( !$subsection['ln'] ) {
					Log::append( 'Не удалось найти тему со списком для подраздела № ' . $subsection['id'] );
					continue;
				}
				TIniFileEx::write( $subsection['id'], 'link', $subsection['ln'] );
			}
			$ln = preg_replace('/.*?([0-9]*)$/', '$1', $subsection['ln']);
			$i = 0;
			$page = 1;
			// получение данных со страниц
			while($page > 0){
				$data = $this->make_request(
					$this->forum_url . '/forum/viewtopic.php?t=' . $ln. '&start=' . $i
				);
				$html = phpQuery::newDocumentHTML($data, 'UTF-8');
				$topic_main = $html->find('table#topic_main');
				$pages = $html->find('a.pg:last')->prev();
				if(!empty($pages) && $i == 0)
					$page = $html->find('a.pg:last')->prev()->text();
				unset($html);
				if(!empty($topic_main)){
					$topic_main = pq($topic_main);
					foreach($topic_main->find('tbody') as $row){
						$row = pq($row);
						$nick = $row->find('p.nick > a')->text();
						if($nick != $this->login && !empty($nick)){
							// получаем id раздач хранимых другими хранителями
							foreach($row->find('a.postLink') as $topic){
								$topic = pq($topic);
								if(preg_match('/viewtopic.php\?t=[0-9]+$/', $topic->attr('href'))){
									$topic_id = preg_replace('/.*?([0-9]*)$/', '$1', $topic->attr('href'));
									$keepers[$topic_id][] = $nick;
								}
							}
						}
					}
				}
				$page--;
				$i += 30;
			}
		}
		TIniFileEx::updateFile();
		return $keepers;
	}
	
	public function __destruct(){
		curl_close($this->ch);
	}
	
}

?>
