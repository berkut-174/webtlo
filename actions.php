<?php

include dirname(__FILE__) . '/common.php';
include dirname(__FILE__) . '/api.php';
include dirname(__FILE__) . '/clients.php';
include dirname(__FILE__) . '/gui.php';
include dirname(__FILE__) . '/reports.php';

// разбираем настройки

// общие с формы #config
if(isset($_POST['cfg'])) {
	parse_str($_POST['cfg']); 
	$savesubdir = isset($savesubdir) ? 1 : 0;
	$retracker = isset($retracker) ? 1 : 0;
	$avg_seeders = isset($avg_seeders) ? 1 : 0;
	$avg_seeders_period = $avg_seeders_period == 0 ? 1 : ($avg_seeders_period > 30 ? 30 : $avg_seeders_period); // жёсткое ограничение на 30 дн.
	$active = isset($proxy_activate) ? 1 : 0;
	$proxy_address = $proxy_hostname . ':' . $proxy_port;
	$proxy_auth = $proxy_login . ':' . $proxy_paswd;
	Proxy::options ( $active, $proxy_type, $proxy_address, $proxy_auth );
}

// подразделы
if(isset($_POST['subsec'])){
	$TT_subsections = $_POST['subsec'];
}

// торрент-клиенты
if(isset($_POST['tcs'])) {
	$tcs = $_POST['tcs'];
}

// замена passkey
if(isset($_POST['edit'])) {
	$edit = $_POST['edit'];
}

/*
 * 'savecfg' - сохранение настроек,
 * 'reports' - формирование отчётов,
 * 'send' - отправка отчётов на форум
 * 'topics' - формирование списка раздач для хранения,
 * 'update' - обновление сведений о раздачах
 * 'download' - скачивание т.-файлов
*/

switch($_POST['m'])
{
	//------------------------------------------------------------------
	case 'savecfg':
		Log::clean();
		write_config(dirname(__FILE__) . '/config.ini', $_POST['cfg'], $TT_subsections, $tcs);
		echo Log::get();
		break;
	//------------------------------------------------------------------
	case 'reports':
		try {
			Log::clean();
			$db = new Database();
			$subsections = $db->get_forums_details($TT_subsections);
			$topics = $db->get_topics($TT_subsections, 1, $avg_seeders, $avg_seeders_period, 'na');
			$reports = create_reports($subsections, $topics, $TT_login, $TT_rule_reports); unset($topics);
			output_reports($reports, $TT_login);
		} catch (Exception $e) {
			Log::append ( $e->getMessage() );
			echo json_encode(array('log' => Log::get(),
				'report' => '<br /><div>Нет или недостаточно данных для
				отображения.<br />Проверьте настройки и выполните обновление сведений.</div><br />'
			));
		}
		break;
	//------------------------------------------------------------------
	case 'send':
		try {
			$db = new Database();
			$subsec = array_column_common($TT_subsections, 'id');
			$subsections = $db->get_forums_details($subsec);
			$topics = $db->get_topics($subsec, 1, $avg_seeders, $avg_seeders_period, 'na');
			$reports = create_reports($subsections, $topics, $TT_login, $TT_rule_reports); unset($topics);
			$send = new Reports($forum_url, $TT_login, $TT_password);
			$send->send_reports($api_key, $api_url, $reports, $TT_subsections);
			echo Log::get();
		} catch (Exception $e) {
			Log::append ( $e->getMessage() );
			echo Log::get();
		}
		break;
	//------------------------------------------------------------------
	case 'download':
		try {
			$topics = $_POST['topics']; // массив из идентификаторов топиков для скачивания
			$dl = new Download($api_key);
			$dl->create_directories($savedir, $savesubdir, $TT_subsections, $TT_rule_topics, $dir_torrents, $edit, $dl_log);
			$dl->download_torrent_files($forum_url, $user_id, $topics, $retracker, $dl_log, $passkey, $edit);
			echo json_encode(array('log' => Log::get(), 'dl_log' => $dl_log));
		} catch (Exception $e) {
			Log::append ( $e->getMessage() );
			echo json_encode(array('log' => Log::get(), 'dl_log' => $dl_log));
		}
		break;
	//------------------------------------------------------------------
	case 'update':
		try {
			$subsec = array_column_common($TT_subsections, 'id');
			$tc_topics = get_tor_client_data($tcs); /* обновляем сведения от т.-клиентов */
			$reports = new Reports($forum_url, $TT_login, $TT_password);
			$keepers = $reports->search_keepers($TT_subsections);
			$db = new Database();
			$db->set_keepers($keepers);
			unset($keepers);
			$webtlo = new Webtlo($api_url, $api_key);
			$subsections = $webtlo->get_cat_forum_tree($subsec); /* обновляем дерево разделов */
			$ids = $webtlo->get_subsection_data($subsections, $topics_status);
			$webtlo->prepare_topics($ids, $tc_topics, $TT_rule_topics, $subsec, $avg_seeders);
			echo json_encode( array('log' => Log::get() ) );
		} catch (Exception $e) {
			Log::append ( $e->getMessage() );
			echo json_encode(array('log' => Log::get(),
				'topics' => '<br />В процессе обновления сведений
				были ошибки.<br />Для получения подробностей
				обратитесь к журналу событий.<br />'
			));
		}
		break;
	//------------------------------------------------------------------
}

?>
