<?php
class MySQL extends Connector {
    public function __construct($host, $port, $user, $password, $dbName)
    {
        parent::__construct($host, $port, $user, $password, $dbName);
        $this->adjustTimeZone();
        $this->createTableMain();
        $this->createTableContent();
        $this->createTableExt();
    }

    public function executeSQL($sql)
    {
        $r = null;
        for($i = 0; $i < 3; ++$i) {
            try {
                $r = $this->query($sql); 
                break;
            } catch (Exception $e) {
                echo $e->getMessage().PHP_EOL;
            }
        }
        return $r;
    }



    private function adjustTimeZone()
    {
        $init_tz    = date_default_timezone_get();
        $time       = new \DateTime('now', new DateTimeZone($init_tz));
        $gmt_offset = $time->format('P');

        try {
            $this->query("set time_zone = '$gmt_offset'");
        } catch (Exception $e) {}
    }

    private function createTableMain()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `rent_apart` (
                `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `Title` varchar(255) NOT NULL DEFAULT '',
                `PostDate` varchar(255) NOT NULL DEFAULT '',
                `Link` varchar(1023) NOT NULL DEFAULT '',
                `Author` varchar(255) NOT NULL DEFAULT '',
                `IsReply` enum('Yes','No') NOT NULL DEFAULT 'No',
                `FormalPostDate` timestamp NULL DEFAULT NULL,
                `AddTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `IsProcess` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0 = todo, 1 = get_content_finished, 2= format_finished',
                `LockTime` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`ID`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8
            ";
        try {
            $r = $this->query($sql); 
        } catch (Exception $e) {
            echo $e->getMessage().PHP_EOL;
            exit;
        }
    }

    private function createTableContent()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `rent_apart_content` (
                `ID` int(10) unsigned NOT NULL,
                `Header` varchar(255) NOT NULL DEFAULT '',
                `Content` mediumtext,
                `AddTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`ID`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8
            ";
        try {
            $r = $this->query($sql); 
        } catch (Exception $e) {
            echo $e->getMessage().PHP_EOL;
            exit;
        }
    }

    private function createTableExt()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `rent_apart_ext` (
                `ID` int(10) unsigned NOT NULL,
                `HouseType` varchar(255) NOT NULL DEFAULT '',
                `Location` varchar(255) NOT NULL DEFAULT '',
                `City` varchar(50) NOT NULL DEFAULT '' COMMENT '縣市',
                `District` varchar(50) NOT NULL DEFAULT '' COMMENT '區鎮鄉',
                `Elevator` enum('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown' COMMENT '有無電梯',
                `RentCost` varchar(100) NOT NULL DEFAULT '' COMMENT '單位: 萬',
                `RentDesc` varchar(100) NOT NULL DEFAULT '',
                `Deposit` varchar(10) NOT NULL DEFAULT '' COMMENT '押金, 單位: 萬',
                `RoomNum` varchar(10) NOT NULL DEFAULT '' COMMENT '房間數',
                `WCNum` varchar(10) NOT NULL DEFAULT '',
                `KitchenNum` varchar(10) NOT NULL DEFAULT '' COMMENT '廚房數',
                `LivingNum` varchar(10) NOT NULL DEFAULT '' COMMENT '客廳數',
                `BalconyNum` varchar(10) NOT NULL DEFAULT '' COMMENT '陽台數',
                `Floor` varchar(10) NOT NULL DEFAULT '' COMMENT '樓層',
                `TotalFloor` varchar(10) NOT NULL DEFAULT '' COMMENT '總樓高',
                `Area` varchar(10) NOT NULL DEFAULT '' COMMENT '面積坪數',
                `AreaDesc` varchar(100) NOT NULL DEFAULT '',
                `Tenant` varchar(255) NOT NULL DEFAULT '' COMMENT '租客限制',
                `AddTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`ID`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8
            ";
        try {
            $r = $this->query($sql); 
        } catch (Exception $e) {
            echo $e->getMessage().PHP_EOL;
            exit;
        }
    }
}
?>