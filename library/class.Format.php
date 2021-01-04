<?php
class Format {
    private $table_main    = 'rent_apart';
    private $table_content = 'rent_apart_content';
    private $table_ext     = 'rent_apart_ext';

    private $id       = 0;
    private $title    = '';
    private $header   = '';
    private $content  = '';
    private $ext_info = [];

    public function __construct($MySQL)
    {
        $this->MySQL    = $MySQL;
        $this->ext_info = [
            'HouseType'  => '',
            'Location'   => '',
            'City'       => '', // 縣市
            'District'   => '', // 區鎮鄉
            'Elevator'   => 'Unknown', // 電梯
            'RentCost'   => '', // 租金
            'RentDesc'   => '', // 租金補充說明
            'Deposit'    => '', // 押金
            'RoomNum'    => '', // 房間數
            'WCNum'      => '', // 廁所數
            'KitchenNum' => '', // 廚房數
            'LivingNum'  => '', // 客廳數
            'BalconyNum' => '', // 陽台數
            'Floor'      => '',
            'TotalFloor' => '',
            'Area'       => '', // 坪數
            'AreaDesc'   => '',
            'Tenant'     => '', // 租客限制
        ];
    }

    public function init()
    {
        if(!$this->getTodoData()) { return false; }

        $keyword  = 'set';
        $word_len = strlen($keyword);
        $method   = get_class_methods($this);
        foreach($method as $m) {
            if(strlen($m) > $word_len && substr($m, 0, $word_len) === $keyword) {
                $this->$m();
            }
        }
        return true;
    }

    public function format()
    {
        $keyword  = __FUNCTION__;
        $word_len = strlen($keyword);
        $method   = get_class_methods($this);
        foreach($method as $m) {
            if(strlen($m) > $word_len && substr($m, 0, $word_len) === $keyword) {
                $this->$m();
            }
        }
    }

    public function saveDB()
    {
        if($this->needIgnore() !== true) {
            $this->insertExtInfo();
            $this->finishProcess();
        } else {
            $this->deleteArticle();
        }
    }



    private function getTodoData()
    {
        $colum = 'M.ID, M.Title, C.Header, C.Content';
        $table = "{$this->table_main} M JOIN {$this->table_content} C ON M.ID = C.ID";
        $sql   = "SELECT $colum FROM $table WHERE M.IsProcess = 1 ORDER BY M.ID ASC LIMIT 1";
        $data  = $this->MySQL->row($sql);

        if(empty($data)) { return false; }

        $this->id      = $data['ID'];
        $this->title   = $data['Title'];
        $this->header  = empty($data['Header'])? $data['Title'] : $data['Header'];
        $this->content = preg_replace('/\n(\d\.)/i', ' $1', $data['Content']);
        return true;
    }

    private function needIgnore()
    {
        // strlen of chinese
        $chr = strlen('市');
        if(strlen($this->content) < ($chr * 20)) {
            return true;
        }
        if(stripos($this->ext_info['HouseType'], '車位') !== false) {
            return true;
        }
        $keyword = ['徵求地點', '店面', '辦公', '已出租', '暫停', '已收訂', '欲承租', '室友'];
        $keyword = '('.implode(')|(', $keyword).')';
        if(preg_match("/$keyword/i", $this->content) > 0) {
            return true;
        }
        return false;
    }

    private function insertExtInfo()
    {
        $data = $this->ext_info;
        $data['ID'] = $this->id;
        $colums = implode(',', array_keys($data));
        $values = array_reduce($data, function($c, $d) {
            $d = isset($d)? "'$d'" : 'NULL';
            return empty($c)? $d : $c.','.$d;
        }, '');
        $sql = "INSERT IGNORE INTO {$this->table_ext}($colums) VALUES($values)";
        return $this->MySQL->executeSQL($sql);
    }

    private function deleteArticle()
    {
        $sql = "DELETE FROM {$this->table_main} WHERE ID = {$this->id}";
        $s1  = $this->MySQL->executeSQL($sql);
        $sql = "DELETE FROM {$this->table_content} WHERE ID = {$this->id}";
        $s2  = $this->MySQL->executeSQL($sql);
        return $s1 && $s2;
    }

    private function finishProcess()
    {
        $sql = "UPDATE {$this->table_main} SET IsProcess = 2 WHERE ID = {$this->id}";
        return $this->MySQL->executeSQL($sql);
    }

    private function setHouseType()
    {
        if(preg_match('/房屋(?>型式|類別)\s*:?([^\n]+)\n/i', $this->content, $m) > 0) {
            $this->ext_info['HouseType'] = str_replace('。', '', trim($m[1]));
        }
    }

    private function setLocation()
    {
        // strlen of chinese
        $chr = strlen('市');
        // get City & District from Title
        if(preg_match('/\[[^\]]+(\/[^\]]+){2}\]/i', $this->title, $m) > 0) {
            $desc = str_replace(['[', ']'], '', $m[0]);
            $desc = explode('/', $desc);
            $this->ext_info['City']     = strlen($desc[1]) <= ($chr * 3)? $desc[1] : '';
            $this->ext_info['District'] = strlen($desc[2]) <= ($chr * 3)? $desc[2] : '';
        }
        // get Location & City & District from Content
        if(preg_match('/(房店|租屋|房屋)?(地址|地點):?([^\n]+)\n/i', $this->content, $m) > 0) {
            $desc = str_replace([':'], '', $m[3]);
            $this->ext_info['Location'] = trim($desc);
            // City
            $desc = explode('(', $desc)[0];
            $pos  = stripos($desc, '縣');
            $pos  = ($pos === false)? stripos($desc, '市') : $pos;
            if($pos !== false) {
                $city = substr($desc, 0, $pos + $chr);
                $city = trim($city);
                if(strlen($city) >= ($chr * 2) && strlen($city) <= ($chr * 3)) {
                    $this->ext_info['City'] = $city;
                }
                $desc = str_replace($city, '', $desc);
            }
            // District
            $pos = stripos($desc, '市');
            $pos = ($pos === false)? stripos($desc, '區') : $pos;
            $pos = ($pos === false)? stripos($desc, '鄉') : $pos;
            $pos = ($pos === false)? stripos($desc, '鎮') : $pos;
            if($pos !== false) {
                $district = substr($desc, 0, $pos + $chr);
                $district = trim($district);
                if(strlen($district) >= ($chr * 2) && strlen($district) <= ($chr * 3)) {
                    $this->ext_info['District'] = $district;
                }
            }
        }
    }

    private function setRentCost()
    {
        if(preg_match('/(租金|月租)[^:]*:?([^\n]+)\n/i', $this->content, $m) > 0) {
            $desc = str_replace(':', '', $m[2]);
            if(stripos($desc, '/') !== false) {
                $desc = explode('/', $desc);
                $this->ext_info['RentCost'] = trim($desc[0]);
                $this->ext_info['Deposit']  = trim($desc[1]);
            } else {
                $this->ext_info['RentCost'] = trim($desc);
            }
        }
        if(preg_match('/(租屋)?押金:?([^\n]+)\n/i', $this->content, $m) > 0) {
            $desc = Helper::chineseToDigit($m[2]);
            $this->ext_info['Deposit'] = trim($desc);
        }
    }

    private function setFloor()
    {
        if(preg_match('/樓層:?([^\n]+)\n/i', $this->content, $m) > 0) {
            $desc = Helper::chineseToDigit($m[1]);
            if(preg_match('/([\d、\-]+)(F|樓)?\s*\/\s*(\d+)(F|樓)?/', $desc, $m1) > 0) {
                $this->ext_info['Floor']      = $m1[1];
                $this->ext_info['TotalFloor'] = $m1[3];
            } elseif(preg_match('/([\d]+)[^\/]*\/[^\/\d]*([\d]+)/i', $desc, $m1) > 0) {
                $this->ext_info['Floor']      = min($m1[1], $m1[2]);
                $this->ext_info['TotalFloor'] = max($m1[1], $m1[2]);
            } elseif(preg_match('/\d+/i', $desc, $m1) === 1) {
                $this->ext_info['Floor'] = $m1[0];
            }
        }
    }

    private function setStructure()
    {
        $pattern1 = '/((?>格[　\s]*局:)|(?>格[　\s]+局)|(?>房型))([^\n]+)\n/i';
        $pattern2 = '/\d(?>房|廳|衛|廚|陽台)(\D{0,7}\d(?>房|廳|衛|廚|陽台)){2,}/i';
        foreach([$this->content, $this->header] as $description) {
            if(preg_match($pattern1, $description, $m) > 0 || preg_match($pattern2, $description, $m) > 0) {
                $desc = Helper::chineseToDigit($m[0]);
                if(preg_match('/(\d+)\s*房/i', $desc, $m1) > 0) {
                    $this->ext_info['RoomNum'] = $m1[1];
                }
                if(preg_match('/(\d+)\s*衛/i', $desc, $m1) > 0) {
                    $this->ext_info['WCNum'] = $m1[1];
                }
                if(preg_match('/(\d+)\s*廚/i', $desc, $m1) > 0) {
                    $this->ext_info['KitchenNum'] = $m1[1];
                }
                if(preg_match('/(\d+)\s*廳/i', $desc, $m1) > 0) {
                    $this->ext_info['LivingNum'] = $m1[1];
                }
                if(preg_match('/(\d+)\s*陽台/i', $desc, $m1) > 0) {
                    $this->ext_info['BalconyNum'] = $m1[1];
                }
                if(stripos($desc, '陽台') !== false) {
                    if(preg_match('/後(大)?陽台/i', $desc) > 0) {
                        $this->ext_info['BalconyNum'] = 2;
                    } elseif(stripos($desc, '無陽台') !== false) {
                        $this->ext_info['BalconyNum'] = 0;
                    } elseif(empty($this->ext_info['BalconyNum'])) {
                        $this->ext_info['BalconyNum'] = 1;
                    }
                }
            }
        }
    }

    private function setArea()
    {
        if(preg_match('/坪[　\s]*數:?([^\n]+)\n/i', $this->content, $m) > 0) {
            $desc = str_replace('無', '', $m[1]);
            $desc = preg_replace('/([^\d\.]+)?([\d\.a-z]+)([^\d\.]+)/i', '$1 $2 $3', $desc);
            $desc = preg_replace('/^([\d\.]+)$/i', '$1 坪', $desc);
            $this->ext_info['AreaDesc'] = trim($desc);

            if(preg_match('/([\d\.]+)\s*坪/i', $desc, $m1) > 0) {
                $this->ext_info['Area'] = $m1[1];
            } elseif(preg_match('/^\s*([\d\.]+)\s*$/i', $desc, $m1) > 0) {
                $this->ext_info['Area'] = $m1[1];
            }
        }
    }

    private function setTenant()
    {
        if(preg_match('/限制:?([^\n]+)\n/i', $this->content, $m) > 0) {
            $desc = str_replace('無', '', $m[1]);
            $desc = str_replace([',', '，', '+'], '、', $desc);
            $desc = preg_replace('/^([。、:]+)/i', '', $desc);
            $this->ext_info['Tenant'] = trim($desc);
        }
    }

    private function formatRentCost()
    {
        // format RentCost
        if(preg_match('/[\$]?([\d,]{4,})([\s元，]*)(.*)/i', $this->ext_info['RentCost'], $m) > 0) {
            $cost = preg_replace('/\D+/', '', $m[1]);
            $desc = str_replace(['(', '（', '）', ')'], '', $m[3]);
            $desc = str_replace(['.', '。'], '、', $desc);
            $desc = preg_replace('/(^、)|(、$)/i', '', $desc);
            $desc = trim($desc);
            if($cost >= 1000) {
                $cost = (floatval($cost)/10000.0);
            }
            $this->ext_info['RentCost'] = $cost;
            $this->ext_info['RentDesc'] = $desc;
        }
        if(preg_match('/\d+/i', $this->ext_info['RentCost']) === 0) {
            $this->ext_info['RentCost'] = '';
        }
        $this->ext_info['RentCost'] = str_replace('萬', '', $this->ext_info['RentCost']);
        // format Deposit
        if(preg_match('/([\d\,]{4,})/i', $this->ext_info['Deposit'], $m) > 0) {
            $deposit   = $m[1];
            $cost      = intval(floatval($this->ext_info['RentCost']) * 10000);
            $month_num = @($deposit / $cost);
            
            if(is_int($month_num) && $month_num > 0) { 
                $deposit = $month_num.'個月';
            } elseif($deposit >= 1000) { 
                $deposit = (floatval($deposit)/10000.0).' 萬';
            }
            $this->ext_info['Deposit'] = $deposit;
        }
        if(preg_match('/(\d+)\s*(個月)/i', $this->ext_info['Deposit'], $m) > 0) {
            $this->ext_info['Deposit'] = $m[1].' '.$m[2];
        }
    }

    private function formatLocation()
    {
        // strlen of chinese
        $chr = strlen('市');
        // format City
        if(strlen($this->ext_info['City']) === ($chr * 2)) {
            $this->ext_info['City'] .= '市';
        }
        // format District
        if(strlen($this->ext_info['District']) === ($chr * 2)) {
            $city     = $this->ext_info['City'];
            $district = $this->ext_info['District'];
            if(stripos($city, '市') !== false && stripos($district, '區') === false) {
                $this->ext_info['District'] .= '區';
            }
        }
    }

    private function formatFloor()
    {
        if(preg_match('/(\d+)(?>樓|F)/i', $this->ext_info['Location'], $m) > 0) {
            if(empty($this->ext_info['Floor'])) {
               $this->ext_info['Floor'] = $m[1]; 
            }
        }
    }

    private function formatStructure()
    {
        $description = $this->header.PHP_EOL.$this->content;
        if(stripos($description, '套房') !== false) {
            $this->ext_info['RoomNum'] = '1';
            $this->ext_info['WCNum']   = '1';
        }
    }

    private function formatHouseType()
    {
        $description = $this->header.PHP_EOL.$this->content;
        $house_type  = $this->ext_info['HouseType'];
        if(!in_array($house_type, ['透天', '華廈', '套房', '公寓', '大樓'])) {
            $house_type = '';
        }
        if(empty($house_type) && stripos($description, '透天') !== false) {
            $house_type = '透天';
        }
        if(empty($house_type) && stripos($description, '華廈') !== false) {
            $house_type = '華廈';
        }
        if(empty($house_type) && stripos($description, '套房') !== false) {
            $house_type = '套房';
        }
        if(empty($house_type) && preg_match('/(公寓)|(美寓)/i', $description) > 0) {
            $house_type = '公寓';
        }
        if(empty($house_type) && preg_match('/(大樓)|(中庭)|(4\s*房)/i', $description) > 0) {
            $house_type = '大樓';
        }
        if(empty($house_type)) {
            $total_floor = intval($this->ext_info['TotalFloor']);
            if($total_floor < 1) {
                // ignore
            } elseif($total_floor <= 5) {
                $house_type = '公寓';
            } elseif(intval($this->ext_info['TotalFloor']) <= 10) {
                $house_type = '華廈';
            } else {
                $house_type = '大樓';
            }
            if(intval($this->ext_info['Floor']) > 10) { $house_type = '大樓'; }
        }
        

        if(stripos($house_type, '套房') !== false) { $house_type = '套房'; }
        if(stripos($house_type, '透天') !== false) { $house_type = '透天'; }
        if(stripos($house_type, '別墅') !== false) { $house_type = '透天'; }
        if(stripos($house_type, '華廈') !== false) { $house_type = '華廈'; }
        if(stripos($house_type, '大樓') !== false) { $house_type = '大樓'; }
        if(stripos($house_type, '公寓') !== false) { $house_type = '公寓'; }
        $this->ext_info['HouseType'] = $house_type;
    }

    private function formatElevator()
    {
        if(stripos($this->content, '無電梯') !== false) {
            $this->ext_info['Elevator'] = 'No';
        } elseif(stripos($this->content, '電梯') !== false) {
            $this->ext_info['Elevator'] = 'Yes';
        } elseif(stripos($this->content, '大樓') !== false) {
            $this->ext_info['Elevator'] = 'Yes';
        }
    }
}
?>