<?php
include_once(__DIR__.'/config/config.php');

$board_link = 'https://www.ptt.cc/bbs/Rent_apart/index.html';

$MySQL = new MySQL(DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME);
$MySQL->query("TRUNCATE rent_apart");
$MySQL->query("TRUNCATE rent_apart_ext");
$MySQL->query("TRUNCATE rent_apart_content");

$page = 0;
while(true) {
    $Board = new Board();
    $Board->init($board_link);
    $dom_list = $Board->getArticleList();

    //save each article
    foreach($dom_list as $dom) {
        $Article = new Article($MySQL);
        $Article->init($dom);
        $Article->saveDB();
    }
    $board_link = $Board->getPreviousPage();
    // save content of article
    while(true) {
        $Content = new Content($MySQL);
        if(!$Content->init()) { break; }
        $Content->saveDB();
    }
    // format content of article
    while(true) {
        $Format = new Format($MySQL);
        if(!$Format->init()) { break; }
        $Format->format();
        $Format->saveDB();
    }

    printf('Page %3d finished.'.PHP_EOL, ++$page);
    if($page >= 80) { break; }
}
