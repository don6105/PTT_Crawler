<?php
class Helper {
    public static function getAbsoluteUrl($baseUrl, $url) 
    {
        if(preg_match('/^https?:\/\/[^\/]+/i', $url) > 0) { return $url; }
        if(preg_match('/^https?:\/\/[^\/]+/i', $baseUrl) === 0) { return $url; }

        $absolute_url = '';

        if(preg_match('/^\//i', $url) > 0) {
            if(preg_match('/https?:\/\/[^\/]+/i', $baseUrl, $m) > 0) {
                $absolute_url = $m[0].$url;
            }
        } else {
            $scheme   = parse_url($baseUrl, PHP_URL_SCHEME);
            $base_url = preg_replace('/\/[^\/]+$/i', '', $baseUrl);
            $base_url = preg_replace('/^https?:\/\//i', '', $base_url);

            $base = explode('/', $base_url);
            $url  = explode('/', $url);
            $url  = array_merge($base, $url);
            $url  = array_intersect_key($url, array_unique(array_map('strtolower', $url)));
            $url  = array_filter($url);

            $absolute_url = $scheme.'://'.implode('/', $url);
        }
        return $absolute_url;
    }

    public static function chineseToDigit($str)
    {
        $chinese = ['二十', '一', '二', '兩', '三', '四', '五', '六', '七', '八', '九', '零', '十'];
        $digit   = ['B', '1', '2', '2', '3', '4', '5', '6', '7', '8', '9', '0', 'A'];
        $str = str_replace($chinese, $digit, $str);
        $str = preg_replace('/B(\D)/i', '20$1', $str);
        $str = preg_replace('/B(\D)/i', '10$1', $str);
        $str = preg_replace('/B(\d)/i', '2$1',  $str);
        $str = preg_replace('/A(\d)/i', '1$1',  $str);
        return $str;
    }
}
?>