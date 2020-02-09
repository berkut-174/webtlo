<?php

try {

    $starttime = microtime(true);

    include_once dirname(__FILE__) . '/../common.php';
    include_once dirname(__FILE__) . '/../torrenteditor.php';
    include_once dirname(__FILE__) . '/../classes/download.php';

    $result = "";

    // список выбранных раздач
    if (empty($_POST['topics_ids'])) {
        $result = "Выберите раздачи";
        throw new Exception();
    }

    // получение настроек
    $cfg = get_settings();

    // проверка настроек
    if (empty($cfg['api_key'])) {
        $result = "В настройках не указан хранительский ключ API";
        throw new Exception();
    }

    if (empty($cfg['user_id'])) {
        $result = "В настройках не указан хранительский ключ ID";
        throw new Exception();
    }

    // идентификатор подраздела
    $forum_id = isset($_POST['forum_id']) ? $_POST['forum_id'] : 0;

    // нужна ли замена passkey
    if (isset($_POST['replace_passkey'])) {
        $replace_passkey = $_POST['replace_passkey'];
    }

    // парсим список выбранных раздач
    parse_str($_POST['topics_ids'], $topics_ids);

    // выбор каталога
    $torrent_files_path = empty($replace_passkey) ? $cfg['save_dir'] : $cfg['dir_torrents'];

    if (empty($torrent_files_path)) {
        $result = "В настройках не указан каталог для скачивания торрент-файлов";
        throw new Exception();
    }

    // дополнительный слэш в конце каталога
    if (
        !empty($torrent_files_path)
        && !in_array(substr($torrent_files_path, -1), array('\\', '/'))
    ) {
        $torrent_files_path .= strpos($torrent_files_path, '/') === false ? '\\' : '/';
    }

    // создание подкаталога
    if (
        empty($replace_passkey)
        && $cfg['savesub_dir']
    ) {
        $torrent_files_path .= 'tfiles_' . $forum_id . '_' . time() . substr($torrent_files_path, -1);
    }

    // создание каталогов
    if (!mkdir_recursive($torrent_files_path)) {
        $result = "Не удалось создать каталог \"$torrent_files_path\": неверно указан путь или недостаточно прав";
        throw new Exception();
    }

    // шаблон для сохранения
    $torrent_files_path_pattern = "$torrent_files_path/[webtlo].t%s.torrent";
    if (PHP_OS == 'WINNT') {
        $torrent_files_path_pattern = mb_convert_encoding(
            $torrent_files_path_pattern,
            'Windows-1251',
            'UTF-8'
        );
    }

    Log::append($replace_passkey
        ? 'Выполняется скачивание торрент-файлов с заменой Passkey...'
        : 'Выполняется скачивание торрент-файлов...'
    );

    // скачивание торрент-файлов
    $download = new Download(
        $cfg['forum_url'],
        $cfg['api_key'],
        $cfg['user_id']
    );

    // применяем таймауты
    $download->curl_setopts($cfg['curl_setopt']['forum']);

    foreach ($topics_ids['topics_ids'] as $topic_id) {
        $data = $download->get_torrent_file($topic_id, $cfg['retracker']);
        if ($data === false) {
            continue;
        }
        // меняем пасскей
        if ($replace_passkey) {
            $torrent = new Torrent();
            if ($torrent->load($data) == false) {
                Log::append("Error: $torrent->error ($topic_id).");
                break;
            }
            $trackers = $torrent->getTrackers();
            foreach ($trackers as &$tracker) {
                $tracker = preg_replace('/(?<==)\w+$/', $cfg['user_passkey'], $tracker);
                if ($cfg['tor_for_user']) {
                    $tracker = preg_replace('/\w+(?==)/', 'pk', $tracker);
                }
            }
            unset($tracker);
            $torrent->setTrackers($trackers);
            $data = $torrent->bencode();
        }
        // сохранить в каталог
        $file_put_contents = file_put_contents(
            sprintf(
                $torrent_files_path_pattern,
                $topic_id
            ),
            $data
        );
        if ($file_put_contents === false) {
            Log::append("Произошла ошибка при сохранении торрент-файла ($topic_id)");
            continue;
        }
        $torrent_files_downloaded[] = $topic_id;
    }
    unset($topics_ids);

    $torrent_files_downloaded = count($torrent_files_downloaded);

    $endtime = microtime(true);

    $result = "Сохранено в каталоге \"$torrent_files_path\": $torrent_files_downloaded шт. за " . convert_seconds($endtime - $starttime);

    echo json_encode(array(
        'log' => Log::get(),
        'result' => $result,
    ));

} catch (Exception $e) {

    Log::append($e->getMessage());
    echo json_encode(array(
        'log' => Log::get(),
        'result' => $result,
    ));

}
