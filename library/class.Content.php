<?php
class Content {
    private $table_main    = 'rent_apart';
    private $table_content = 'rent_apart_content';

    private $id      = 0;
    private $link    = '';
    private $content = [];

    public function __construct($MySQL)
    {
        $this->MySQL   = $MySQL;
        $this->content = [
            'FormalPostDate' => null,
            'Header'         => '',
            'Content'        => ''
        ];
    }

    public function init()
    {
        if(!$this->getTodoData()) { return false; }

        $Request = new Request();

        $r = $Request->run($this->link);
        if(isset($r['http_code'], $r['response']) && $r['http_code'] == '200') {
            $html = $r['response'];

            $dom = new DOMDocument();
            @$dom->loadHTML($html);

            $finder = new DOMXPath($dom);
            $element = $finder->query("//*[contains(@class, 'bbs-screen bbs-content')]");
            $this->setHeader($element);
            $this->setContent($element);
        }
        return true;
    }

    public function saveDB()
    {
        if(empty($this->id)) {
            echo basename(__FILE__).'Empty ArticleID.'.PHP_EOL;
            exit;
        }
        $this->updateFormalPostDate();
        $this->insertContent();
        $this->finishProcess();
    }



    private function getTodoData()
    {
        $sql  = "SELECT ID, Link FROM {$this->table_main} WHERE IsProcess = 0 ORDER BY ID ASC LIMIT 1";
        $data = $this->MySQL->row($sql);

        if(empty($data)) { return false; }

        $this->id   = $data['ID'];
        $this->link = $data['Link'];
        return true;
    }

    private function setHeader($element)
    {
        $content = trim($element[0]->textContent);
        $pos     = stripos($content, "\n");
        $content = $pos !== false? substr($content, 0, $pos) : $content;

        $pos_1 = stripos($content, '標題');
        $pos_2 = strripos($content, '時間');

        $header    = substr($content, $pos_1, $pos_2 - $pos_1);
        $header    = str_replace('標題', '', $header);
        $post_date = substr($content, $pos_2);
        $post_date = str_replace('時間', '', trim($post_date));
        $post_date = empty($post_date)? '' : date('Y-m-d H:i:s', strtotime($post_date));

        $this->content['Header']         = trim($header);
        $this->content['FormalPostDate'] = $post_date;
    }

    private function setContent($element)
    {
        $content = trim($element[0]->textContent);
        $pos     = stripos($content, "\n");
        $content = $pos !== false? substr($content, $pos + 1) : $content;
        $content = preg_replace('/：\n/', '：', $content);

        $pos = stripos($content, 'ctrl + y 刪除！)');
        $content = ($pos !== false)? substr($content, $pos + strlen('ctrl + y 刪除！)') + 1) : $content;

        $pos = stripos($content, '※ 發信站: 批踢踢實業坊');
        $content = ($pos !== false)? substr($content, 0, $pos) : $content;

        $pos = stripos($content, '※ 編輯: ');
        $content = ($pos !== false)? substr($content, 0, $pos) : $content;

        $content = str_replace('【', '', $content);
        $content = str_replace(['】', '：'], ':', $content);

        $this->content['Content'] = addslashes(trim($content));
    }

    private function updateFormalPostDate()
    {
        $post_date = $this->content['FormalPostDate'];
        $sql = "UPDATE {$this->table_main} SET FormalPostDate = '$post_date' WHERE ID = {$this->id}";
        return $this->MySQL->executeSQL($sql);
    }

    private function insertContent()
    {
        $data = $this->content;
        $data['ID'] = $this->id;
        unset($data['FormalPostDate']);
        $colums = implode(',', array_keys($data));
        $values = array_reduce($data, function($c, $d) {
            $d = isset($d)? "'$d'" : 'NULL';
            return empty($c)? $d : $c.','.$d;
        }, '');
        $sql = "INSERT IGNORE INTO {$this->table_content}($colums) VALUES($values)";
        return $this->MySQL->executeSQL($sql);
    }

    private function finishProcess()
    {
        $sql = "UPDATE {$this->table_main} SET IsProcess = 1 WHERE ID = {$this->id}";
        return $this->MySQL->executeSQL($sql);
    }
}
?>