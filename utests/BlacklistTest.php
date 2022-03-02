<?php

/**
* https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
* @backupGlobals disabled
*/

class ModulesTest extends PHPUnit_Framework_TestCase{

    protected static $f;
    protected static $o;
    protected static $module = 'Blacklist';
    protected static $blocked_num = "948000000";
    protected static $blocked_max = "400";

	public static function setUpBeforeClass() {
        self::$f = \FreePBX::create();
        self::$o = self::$f->Blacklist;
	}
    public function setup() {}

	public function testCreateList(){
		for ($i = 1; $i <= self::$blocked_max; $i++) {
            $num = self::$blocked_num + $i;
            $des = "uTest " . $num;
            self::$o->numberAdd(['number' => $num, 'description' => $des]);
        }
	}

    public function testGetCountAllNumbersInit()
    {
        $start_time = microtime(true);
        $array_number = array();
		foreach(self::$o->getBlacklist() as $item)
        {
            array_push($array_number, $item['number']);
        }
        $count = self::$o->getCountCallIn($array_number);
        $end_time = microtime(true);
        $duration = $end_time - $start_time;
        echo "\nTime Get1: " . $duration . "\n";
    }

    public function testGetCountAllNumbersReRead()
    {
        $start_time = microtime(true);
        $array_number = array();
		foreach(self::$o->getBlacklist() as $item)
        {
            array_push($array_number, $item['number']);
        }
        $count = self::$o->getCountCallIn($array_number);
        $end_time = microtime(true);
        $duration = $end_time - $start_time;
        echo "\nTime Get2: " . $duration . "\n";
    }

    public function testRemoveList(){
		for ($i = 1; $i <= self::$blocked_max; $i++) {
            $num = self::$blocked_num + $i;
            self::$o->numberDel($num);
        }
	}
}


