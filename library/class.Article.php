<?php
class Article {
    private $table_main = 'rent_apart';
    private $article    = [];

    public function __construct($MySQL)
    {
        $this->MySQL   = $MySQL;
        $this->article = [
            'Title'    => '',
            'PostDate' => '',
            'Link'     => '',
            'Author'   => '',
            'IsReply'  => 'No'
        ];
    }

    public function init($dom)
    {
        $board_link = $dom->board_link;
        $div_list   = $dom->getElementsByTagName('div');
        foreach($div_list as $div) {
            switch(strtolower($div->getAttribute('class'))) {
                case 'title':
                    $this->setTitle($div);
                    $this->setDetailLink($div, $board_link);
                    $this->setIsReply($div);
                    break;
                case 'author':
                    $this->setAuthor($div);
                    break;
                case 'date':
                    $this->setPostDate($div);
                    break;
                default:
                    break;
            }
        }
    }

    public function saveDB()
    {
        if($this->needIgnore() !== true) {
            $colums = implode(',', array_keys($this->article));
            $values = "'".implode("','", $this->article)."'";
            $sql    = "INSERT INTO {$this->table_main} ($colums) VALUES($values)";
            $this->MySQL->executeSQL($sql);
        }
    }



    private function setTitle($element)
    {
        $e     = $element->getElementsByTagName('a');
        $title = trim($e[0]->textContent);
        $this->article['Title'] = $title;
    }

    private function setDetailLink($element, $baseUrl)
    {
        $e    = $element->getElementsByTagName('a');
        $link = $e[0]->getAttribute('href');
        $link = Helper::getAbsoluteUrl($baseUrl, $link);
        $this->article['Link'] = $link;
    }

    private function setPostDate($element)
    {
        $this->article['PostDate'] = trim($element->textContent);
    }

    private function setAuthor($element)
    {
        $this->article['Author'] = trim($element->textContent);
    }

    private function setIsReply($element)
    {
        $e     = $element->getElementsByTagName('a');
        $title = trim($e[0]->textContent);
        $this->article['IsReply'] = (preg_match('/Re:/i', $title) > 0)? 'Yes' : 'No';
    }

    private function needIgnore()
    {
        if(preg_match('/\[公告|發文教學|刪文\]/i', $this->article['Title']) > 0) {
            return true;
        }
        if(preg_match('/^((Re:)|(Fw:)|(出租))/i', $this->article['Title']) > 0) {
            return true;
        }
        if(preg_match('/徵|(車位)|(已租出)|(已出租)|(已收訂)/i', $this->article['Title']) > 0) {
            return true;
        }
        if(preg_match('/\[[^\/]*(\/[^\/]*){2,}\]/i', $this->article['Title']) === 0) {
            return true;
        }
        return false;
    }
}
?>