<?php
class Board {
    private $board_link   = '';
    private $prev_page    = '';
    private $article_list = [];

    public function __construct($Request)
    {
        $this->Request = $Request;
    }

    public function init($boardLink)
    {
        $Request = new Request();
        $this->board_link = $boardLink;

        $r = $this->Request->run($this->board_link);
        if(isset($r['http_code'], $r['response']) && $r['http_code'] == '200') {
            $html = $r['response'];
            $this->setPreviousPage($html);
            $this->setArticleList($html);
        }
    }

    public function getPreviousPage()
    {
        return $this->prev_page;
    }

    public function getArticleList()
    {
        return $this->article_list;
    }



    private function setPreviousPage($html)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        $finder    = new DOMXPath($dom);
        $classname = 'btn-group-paging';
        $node_list = $finder->query("//*[contains(@class, '$classname')]//a");

        $link = '';
        for($i = 0; $i < $node_list->length; ++$i) {
            $node = $node_list->item($i);
            if(stripos($node->textContent, '上頁') !== false) {
                $link = $node->getAttribute('href');
                $link = Helper::getAbsoluteUrl($this->board_link, $link);
                $this->prev_page = $link;
                break;
            }
        }
    }

    private function setArticleList($html)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        $finder   = new DOMXPath($dom);
        $row_list = $finder->query("//*[contains(@class, 'r-ent')]");
        foreach($row_list as $row_dom){
            $row_dom->board_link  = $this->board_link;
            $this->article_list[] = $row_dom;
        }
        // revert sort.
        krsort($this->article_list);
    }
}
?>