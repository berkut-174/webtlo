<?php

/**
 * Class Rtorrent
 * Supported by rTorrent 0.9.7 and later
 * Added by: advers222@ya.ru
 */
class Rtorrent extends TorrentClient
{

    protected static $base = 'http://%s/RPC2';

    /**
     * получение имени сеанса
     * @return bool true в случе успеха, false в случае неудачи
     */
    protected function getSID()
    {
        return $this->makeRequest('session.name') ? true : false;
    }

    /**
     * выполнение запроса
     * @param $command
     * @param $params
     * @return bool|mixed
     */
    public function makeRequest($command, $params = '')
    {
        $request = xmlrpc_encode_request($command, $params, array('encoding' => 'UTF-8'));
        $request = str_replace('&#10;', '', $request); // ???
        $header = array(
            'Content-type: text/xml',
            'Content-length: ' . strlen($request)
        );
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => sprintf(self::$base, $this->host),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_POSTFIELDS => $request,
        ));
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            Log::append('CURL ошибка: ' . curl_error($ch));
            return false;
        }
        curl_close($ch);
        return xmlrpc_decode(str_replace('i8>', 'i4>', $response));
    }

    public function getTorrents()
    {
        $response = $this->makeRequest(
            'd.multicall2',
            array('', 'main', 'd.hash=', 'd.state=', 'd.complete=', 'd.message=')
        );
        if (empty($response)) {
            return false;
        }
        $torrents = array();
        foreach ($response as $torrent) {
            if (empty($torrent[3])) {
                if ($torrent[2]) {
                    $status = $torrent[1] ? 1 : -1;
                } else {
                    $status = 0;
                }
                $torrents[$torrent[0]] = $status;
            }
        }
        return $torrents;
    }

    public function addTorrent($torrentFilePath, $savePath = '')
    {
        // if (!empty($savePath)) {
        //     $this->makeRequest('directory.default.set', array('', rawurlencode($savePath)));
        // }
        $torrentFile = file_get_contents($torrentFilePath);
        if ($torrentFile === false) {
            Log::append('Error: не удалось загрузить файл ' . $torrentFilePath);
            return false;
        }
        $torrentFile = base64_encode($torrentFile);
        xmlrpc_set_type($torrentFile, 'base64');
        return $this->makeRequest(
            'load.raw_start_verbose',
            array(
                '',
                $torrentFile,
                'd.delete_tied=',
                'd.directory.set=' . rawurlencode($savePath)
            )
        );
    }

    public function setLabel($hashes, $label = '')
    {
        if (empty($label)) {
            return false;
        }
        $label = rawurlencode($label);
        foreach ($hashes as $hash) {
            $this->makeRequest('d.custom1.set', array($hash, $label));
        }
    }

    public function startTorrents($hashes, $forceStart = false)
    {
        foreach ($hashes as $hash) {
            $this->makeRequest('d.start', $hash);
        }
    }

    public function stopTorrents($hashes)
    {
        foreach ($hashes as $hash) {
            $this->makeRequest('d.stop', $hash);
        }
    }

    public function removeTorrents($hashes, $deleteLocalData = false)
    {
        foreach ($hashes as $hash) {
            $this->makeRequest(
                'system.multicall',
                array(
                    array(
                        array(
                            'methodName' => 'd.custom5.set',
                            'params' => array($hash, 1),
                        ),
                        array(
                            'methodName' => 'd.delete_tied',
                            'params' => array($hash),
                        ),
                        array(
                            'methodName' => 'd.erase',
                            'params' => array($hash)
                        )
                    )
                )
            );
        }
    }

    public function recheckTorrents($hashes)
    {
        foreach ($hashes as $hash) {
            $this->makeRequest('d.check_hash', $hash);
        }
    }
}