<?php
class Request {
    private $expire_time = 0;
    private $cache_path  = PROJECT_ROOT.'cache/';

    public function __construct()
    {
        $this->expire_time = 60 * 60 * 12; // 12 hours
    }

    public function run($url, $timeout = 30, $data = null)
    {
        $cache = $this->readCache($url);
        if(isset($cache)) { return $cache; }

        $cookie_name = __DIR__;
        $user_agent  = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)';
        $header = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US;q=0.8,en;q=0.7',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests:1'
        ];
        $option = [
            CURLOPT_VERBOSE          => 0,
            CURLOPT_RETURNTRANSFER   => 1,
            CURLOPT_FOLLOWLOCATION   => true,
            CURLOPT_HEADER           => false,
            CURLOPT_HTTPHEADER       => $header,
            CURLOPT_NOBODY           => false,
            CURLOPT_CUSTOMREQUEST    => empty($data)? 'GET' : 'POST',
            CURLOPT_POSTFIELDS       => empty($data)? ''    : http_build_query($data),
            CURLOPT_SSL_VERIFYPEER   => false,
            CURLOPT_SSL_VERIFYHOST   => 2,
            CURLOPT_COOKIEFILE       => $cookie_name,
            CURLOPT_COOKIEJAR        => $cookie_name,
            CURLOPT_NOPROGRESS       => true,
            CURLOPT_IPRESOLVE        => CURL_IPRESOLVE_V4,
            CURLOPT_USERAGENT        => $user_agent,
            CURLOPT_TIMEOUT          => $timeout
        ];

        for($req_index = 0; $req_index < 3; ++$req_index) {
            // init curl
            $ch = curl_init();
            curl_setopt_array($ch, $option);
            curl_setopt($ch, CURLOPT_URL, $url);
            $r = curl_exec($ch);
            // get result from curl
            $result = [];
            $result['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result['final_url'] = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $result['fail']      = curl_error($ch);
            $result['response']  = $r; // put response at end of result.
            curl_close($ch);
            if($result['http_code'] == '200' && !empty($result['response'])) { break; }
        }

        is_file($cookie_name) && @unlink($cookie_name);
        $this->saveCache($url, $result);
        return $result;
    }

    private function saveCache($url, $data)
    {
        $file_name = md5($url);
        $content   = serialize($data);
        if(!is_dir($this->cache_path)) {
            mkdir($this->cache_path, 0777, true) && chmod($this->cache_path, 0777);
        }
        file_put_contents($this->cache_path.$file_name.'.cache', $content);
    }

    private function readCache($url)
    {
        $file_name = md5($url);
        $content   = null;
        if(file_exists($this->cache_path.$file_name.'.cache')) {
            $add_time = filemtime($this->cache_path.$file_name.'.cache');
            if($add_time > 0 && ($add_time + $this->expire_time) > time()) {
                $content = file_get_contents($this->cache_path.$file_name.'.cache');
                $content = unserialize($content);
            }
        }
        return $content;
    }

    private function cleanCache()
    {
        if(file_exists($this->cache_path)) {
            $check_time = time() - $this->expire_time;
            foreach(scandir($this->cache_path) as $cache) {
                if(stripos($cache, '.cache') === false) { continue; }
                if(!file_exists($this->cache_path.$cache)) { continue; }
                $add_time = filemtime($this->cache_path.$cache);
                if($add_time > 0 && $add_time < $check_time) {
                    @unlink($this->cache_path.$cache);
                }
            }
        }
    }
}
?>