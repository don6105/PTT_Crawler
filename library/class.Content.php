<?php
class Content {
    private $table_main    = 'rent_apart';
    private $table_content = 'rent_apart_content';

    private $id      = 0;
    private $link    = '';
    private $content = [];

    public function __construct($MySQL, $Request)
    {
        $this->MySQL   = $MySQL;
        $this->Request = $Request;
        $this->content = [
            'FormalPostDate' => null,
            'Header'         => '',
            'Content'        => ''
        ];
    }

    public function init()
    {
        $data = $this->getTodoData();
        if(empty($data)) { return false; }

        $this->id   = $data['ID'];
        $this->link = $data['Link'];
        return $this->lockTodoData() > 0;
    }

    public function http()
    {
        $r = $this->Request->run($this->link);
        if(isset($r['http_code'], $r['response']) && $r['http_code'] == '200') {
            $html = $r['response'];

            $dom = new DOMDocument();
            @$dom->loadHTML($html);

            $finder = new DOMXPath($dom);
            $element = $finder->query("//*[contains(@class, 'bbs-screen bbs-content')]");
            $this->setHeader($element);
            $this->setContent($element);
        }
    }

    public function saveDB()
    {
        if(empty($this->id)) {
            echo basename(__FILE__).'Empty ArticleID.'.PHP_EOL;
            exit;
        }
        $this->getLock();
        $this->updateFormalPostDate();
        $this->insertContent();
        $this->finishProcess();
        $rate = $this->getFinishRate();
        return $this->unLock($rate);
    }



    private function getTodoData()
    {
        $expire = date('Y-m-d H:i:s', strtotime('-3 mins'));
        $sql = "SELECT ID, Link 
                FROM {$this->table_main}
                WHERE IsProcess = 0 AND (LockTime IS NULL OR LockTime < '$expire')
                ORDER BY ID ASC LIMIT 1";
        return $this->MySQL->row($sql);
    }

    private function lockTodoData()
    {
        if(empty($this->id)) { return false; }
        $sql = "UPDATE {$this->table_main} SET LockTime=NOW() WHERE ID = {$this->id}";
        return $this->MySQL->executeSQL($sql);
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
        if(empty($this->id)) { return false; }

        $post_date = $this->content['FormalPostDate'];
        if(preg_match('/^\d{4}(\-\d{2}){2} \d{2}(:\d{2}){2}&/i', $post_date) > 0) {
            $sql = "UPDATE {$this->table_main} 
                    SET FormalPostDate = '$post_date' 
                    WHERE ID = {$this->id}";
            return @$this->MySQL->executeSQL($sql);
        }
        return false;
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
        return @$this->MySQL->executeSQL($sql);
    }

    private function finishProcess()
    {
        $sql = "UPDATE {$this->table_main} SET IsProcess = 1 WHERE ID = {$this->id}";
        return $this->MySQL->executeSQL($sql);
    }

    private function getFinishRate()
    {
        $sql = "SELECT 
                    SUM(CASE WHEN IsProcess > 0 THEN 1 ELSE 0 END) AS Done, 
                    COUNT(1) AS Total
                FROM {$this->table_main}";
        $r = $this->MySQL->row($sql);
        $done  = floatval($r['Done']);
        $total = floatval($r['Total']);
        $rate  = ($total === 0.0)? 0.0 : ($done / $total * 100.0);
        return sprintf('%03.1f%%', $rate);
    }

    private function getLock()
    {
        $this->key = ftok(dirname(__DIR__), 'a');
        $this->sem = sem_get($this->key);
        while(true) {
            if(sem_acquire($this->sem, false)) { 
                return true;
            }
            usleep(2000);
        }
    }

    private function unLock($rate)
    {
        $shm = shm_attach($this->key, 10240, 0666);
        shm_put_var($shm, 1, $rate);
        return sem_release($this->sem);
    }
}
?>