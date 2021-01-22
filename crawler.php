<?php
include_once(__DIR__.'/config/config.php');

$board_link = 'https://www.ptt.cc/bbs/Rent_apart/index.html';

$Request = new Request();
$MySQL   = new MySQL(DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME);
$MySQL->query("TRUNCATE rent_apart");
$MySQL->query("TRUNCATE rent_apart_ext");
$MySQL->query("TRUNCATE rent_apart_content");

$max_page = 80;

$page = 0;
while(true) {
    $Board = new Board($Request);
    $Board->init($board_link);
    $dom_list = $Board->getArticleList();

    //save each article
    foreach($dom_list as $dom) {
        $Article = new Article($MySQL);
        $Article->init($dom);
        $Article->saveDB();
    }
    $board_link = $Board->getPreviousPage();

    echo chr(27)."[0G";
    printf('Page %d / %d finished.', ++$page, $max_page);
    if($page >= $max_page) {
        echo PHP_EOL.PHP_EOL;
        break;
    }
}

// save content of article
$todo_list = [];
while(true) {
    $Content = new Content($MySQL, $Request);
    if(!$Content->init()) { break; }
    $todo_list[] = $Content;
}

if(function_exists('pcntl_fork')) {
    // get article content in parallel.
    $worker_num = 10;
    $size_limit = ceil(count($todo_list)/$worker_num);
    $todo_list  = array_chunk($todo_list, $size_limit);
    $worker_bit = strlen(strval($worker_num));

    $pid     = null;
    $workers = [];
    for($wid = 0; $wid < $worker_num ; ++$wid) {
        $pid = pcntl_fork();
        if($pid === -1) { // @fail
            die('Fork failed'.PHP_EOL);
        } elseif($pid === 0) { // @child
            foreach($todo_list[$wid] as $content) {
                $content->http();
                $content->saveDB();
            }
            exit;
        } else { // @parent
            $workers[] = $pid;
        }
    }

    declare(ticks = 1);
    pcntl_signal(SIGINT, function($signo) {
        // ctrl + c
        if($signo === SIGINT) { exit; }
    });
    register_shutdown_function(function() {
        $key = ftok(__DIR__, 'a');
        $shm = shm_attach($key, 10240, 0666);
        @sem_remove($shm);
    });

    $key = ftok(__DIR__,'a');
    while(true) {
        // check child process exit.
        foreach($workers as $wid => $pid) {
            if(empty($pid)) { continue; }
            $res = pcntl_waitpid($pid, $status, WNOHANG);
            // If the process has already exited
            if($res == -1 || $res > 0) {
                $workers[$wid] = 0;
            }
        }
        showWorkerStatus($workers);
        // show progress rate
        $shm = shm_attach($key, 10240, 0666);
        if(shm_has_var($shm, 1)) {
            $rate = shm_get_var($shm, 1);
            echo 'Content '.$rate;
        }
        usleep(100000);
        if(array_sum($workers) == 0) { break; }
        echo chr(27)."[6A".chr(27)."[0G";
    }
} else {
    $total_num = count($todo_list);
    foreach($todo_list as $key => $content) {
        $content->http();
        $content->saveDB();
        echo chr(27)."[1A".chr(27)."[0G";
        printf('Content %3.2f%%'.PHP_EOL, ($key+1) / $total_num * 100.0);
    }
}
echo PHP_EOL;

// format content of article
while(true) {
    $Format = new Format($MySQL);
    if(!$Format->init()) { break; }
    $Format->format();
    $Format->saveDB();
}

function showWorkerStatus($workers)
{
    $worker_num = count($workers);
    $worker_id  = array_map(function($w) {
        return str_pad($w, 7, ' ', STR_PAD_BOTH);
    }, array_keys($workers));
    $worker_pid = array_map(function($w) {
        $s = empty($w)? 'X' : $w;
        $s = str_pad($s, 7, ' ', STR_PAD_BOTH);
        $s = empty($w)? str_replace('X', "\033[1m\033[33mX\033[0m", $s) : $s;
        return $s;
    }, $workers);
    echo '+'.implode('+', array_fill(0, $worker_num, '-------')).'+'.PHP_EOL;
    echo '|'.implode('|', $worker_id).'|'.PHP_EOL;
    echo '+'.implode('+', array_fill(0, $worker_num, '-------')).'+'.PHP_EOL;
    echo '|'.implode('|', $worker_pid).'|'.PHP_EOL;
    echo '+'.implode('+', array_fill(0, $worker_num, '-------')).'+'.PHP_EOL.PHP_EOL;
}
