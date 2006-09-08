<?php /* $Id */

if( !class_exists( extension ) ) {
	require('extensions.class.php');
}

// These should probably be moved to extensions.class.php //
// They have been, but I'll leave them here for those that just see the new
// Blacklist module and don't do a SVN update of core.
// They can be removed prior to 2.2-rc1

if( !class_exists( ext_return ) ) {
	class ext_return extends extension {
	        function output() {
	                return "Return";
	        }
	}
}
if( !class_exists( ext_lookupblacklist ) ) {
	class ext_lookupblacklist extends extension {
	        function output() {
	                return "LookupBlacklist(".$this->data.")";
	        }
	}
}
if( !class_exists( ext_zapateller ) ) {
	class ext_zapateller extends extension {
	        function output() {
	                return "Zapateller";
	        }
	}
}
if( !class_exists( ext_gosub ) ) {
	class ext_gosub extends extension {
	        var $pri;
	        var $ext;
	        var $context;

	        function ext_gosub($pri, $ext = false, $context = false) {
	                if ($context !== false && $ext === false) {
	                        trigger_error(E_ERROR, "\$ext is required when passing \$context in ext_gosub::ext_gosub()");
	                }

	                $this->pri = $pri;
	                $this->ext = $ext;
	                $this->context = $context;
	        }

	        function incrementContents($value) {
	                $this->pri += $value;
	        }

	        function output() {
	                return 'Gosub('.($this->context ? $this->context.',' : '').($this->ext ? $this->ext.',' : '').$this->pri.')' ;
	        }
	}
}
if( !class_exists( ext_db_put ) ) {
        class ext_db_put extends extension {
                var $family;
                var $key;
                var $value;

                function ext_db_put($family, $key, $value) {
                        $this->family = $family;
                        $this->key = $key;
                        $this->value = $value;
                }

                function output() {
                        return 'Set(DB('.$this->family.'/'.$this->key.')='.$this->value.')';
                }
        }
}

function blacklist_get_config($engine) {
        global $ext;
        switch($engine) {
                case "asterisk":

		      $id = "app-blacklist";
		      $ext->addInclude('from-internal-additional', $id); // Add the include from from-internal

		      $id = "app-blacklist-check";
		      $c = "s";
                      $ext->add($id, $c, '', new ext_lookupblacklist());
                      $ext->add($id, $c, '', new ext_gotoif('$["${LOOKUPBLSTATUS}"="FOUND"]', 'blacklisted'));
                      $ext->add($id, $c, '', new ext_return());
                      $ext->add($id, $c, 'blacklisted', new ext_answer());
                      $ext->add($id, $c, '', new ext_wait(1));
                      $ext->add($id, $c, '', new ext_zapateller());
                      $ext->add($id, $c, '', new ext_zapateller());
                      $ext->add($id, $c, '', new ext_playback('ss-noservice'));
                      $ext->add($id, $c, '', new ext_hangup());

		      $modulename = 'blacklist';

                        if (is_array($featurelist = featurecodes_getModuleFeatures($modulename))) {
                                foreach($featurelist as $item) {
                                        $featurename = $item['featurename'];
                                        $fname = $modulename.'_'.$featurename;
                                        if (function_exists($fname)) {
                                                $fcc = new featurecode($modulename, $featurename);
                                                $fc = $fcc->getCodeActive();
                                                unset($fcc);

                                                if ($fc != '')
                                                        $fname($fc);
                                        } else {
                                                $ext->add('from-internal-additional', 'debug', '', new ext_noop($modulename.": No func $fname"));
                                                var_dump($item);
                                        }
                                }
                        }

                      break;
        }
}

function blacklist_blacklist_add($fc) {
	global $ext;

	$ext->add('app-blacklist', $fc, '', new ext_goto('1', 's', 'app-blacklist-add'));

	$id = "app-blacklist-add";
	$c = "s";
       	$ext->add($id, $c, '', new ext_answer);
       	$ext->add($id, $c, '', new ext_wait(1));
       	$ext->add($id, $c, '', new ext_Playback('enter-num-blacklist'));
       	$ext->add($id, $c, '', new ext_digittimeout(5));
       	$ext->add($id, $c, '', new ext_responsetimeout(60));
       	$ext->add($id, $c, '', new ext_read('blacknr', 'then-press-pound'));
       	$ext->add($id, $c, '', new ext_saydigits('${blacknr}'));
       	$ext->add($id, $c, '', new ext_Playback('if-correct-press'));
       	$ext->add($id, $c, '', new ext_Playback('digits/1'));
       	$ext->add($id, $c, 'end', new ext_noop('Waiting for input'));
	$c = "1";
	$ext->add($id, $c, '', new ext_db_put('blacklist', '${blacknr}', 1));
       	$ext->add($id, $c, '', new ext_Playback('num-was-successfully'));
       	$ext->add($id, $c, '', new ext_Playback('added'));
       	$ext->add($id, $c, '', new ext_wait(1));
       	$ext->add($id, $c, '', new ext_hangup);
}

function blacklist_blacklist_remove($fc) {
	global $ext;

	$ext->add('app-blacklist', $fc, '', new ext_goto('1', 's', 'app-blacklist-remove'));

	$id = "app-blacklist-remove";
	$c = "s";
       	$ext->add($id, $c, '', new ext_answer);
       	$ext->add($id, $c, '', new ext_wait(1));
       	$ext->add($id, $c, '', new ext_Playback('entr-num-rmv-blklist'));
       	$ext->add($id, $c, '', new ext_digittimeout(5));
       	$ext->add($id, $c, '', new ext_responsetimeout(60));
       	$ext->add($id, $c, '', new ext_read('blacknr', 'then-press-pound'));
       	$ext->add($id, $c, '', new ext_saydigits('${blacknr}'));
       	$ext->add($id, $c, '', new ext_Playback('if-correct-press'));
       	$ext->add($id, $c, '', new ext_Playback('digits/1'));
       	$ext->add($id, $c, 'end', new ext_noop('Waiting for input'));
	$c = "1";
       	$ext->add($id, $c, '', new ext_dbdel('blacklist/${blacknr}'));
       	$ext->add($id, $c, '', new ext_Playback('num-was-successfully'));
       	$ext->add($id, $c, '', new ext_Playback('removed'));
       	$ext->add($id, $c, '', new ext_wait(1));
       	$ext->add($id, $c, '', new ext_hangup);
}

function blacklist_blacklist_last($fc) {
	global $ext;

	$ext->add('app-blacklist', $fc, '', new ext_goto('1', 's', 'app-blacklist-last'));

	$id = "app-blacklist-last";
	$c = "s";
       	$ext->add($id, $c, '', new ext_answer);
       	$ext->add($id, $c, '', new ext_wait(1));
       	$ext->add($id, $c, '', new ext_setvar('lastcaller', '${DB(CALLTRACE/${CALLERID(number)})}'));
       	$ext->add($id, $c, '', new ext_gotoif('$[ $[ "${lastcaller}" = "" ] | $[ "${lastcaller}" = "unknown" ] ]', 'noinfo'));
       	$ext->add($id, $c, '', new ext_playback('privacy-to-blacklist-last-caller'));
       	$ext->add($id, $c, '', new ext_playback('telephone-number'));
       	$ext->add($id, $c, '', new ext_saydigits('${lastcaller}'));
       	$ext->add($id, $c, '', new ext_setvar('TIMEOUT(digit)', '3'));
       	$ext->add($id, $c, '', new ext_setvar('TIMEOUT(response)', '7'));
       	$ext->add($id, $c, '', new ext_playback('if-correct-press'));
       	$ext->add($id, $c, '', new ext_playback('digits/1'));
       	$ext->add($id, $c, '', new ext_goto('end'));
       	$ext->add($id, $c, 'noinfo', new ext_playback('unidentified-no-callback'));
       	$ext->add($id, $c, '', new ext_hangup);
       	$ext->add($id, $c, 'end', new ext_noop('Waiting for input'));
	$c = "1";
       	$ext->add($id, $c, '', new ext_db_put('blacklist', '${lastcaller}', 1));
       	$ext->add($id, $c, '', new ext_Playback('num-was-successfully'));
       	$ext->add($id, $c, '', new ext_Playback('added'));
       	$ext->add($id, $c, '', new ext_wait(1));
       	$ext->add($id, $c, '', new ext_hangup);
}

function blacklist_hookGet_config($engine) {
        global $ext;
        switch($engine) {
                case "asterisk":
              	// Code from modules/core/functions.inc.php core_get_config inbound routes
			$didlist = core_did_list();
			if (is_array($didlist)) {
				foreach ($didlist as $item) {
					$did = core_did_get($item['extension'],$item['cidnum'],$item['channel']);
                    			$exten = $item['extension'];
                    			$cidnum = $item['cidnum'];
                    			$channel = $item['channel'];

                    			$exten = (empty($exten)?"s":$exten);
                    			$exten = $exten.(empty($cidnum)?"":"/".$cidnum); //if a CID num is defined, add it

                    			if (empty($channel))
                    				$context = "ext-did";
                    			else
                    				$context = "macro-from-zaptel-{$channel}";

                    			$ext->splice($context, $exten, 1, new ext_gosub('1', 's', 'app-blacklist-check'));
				}
			} // else no DID's defined. Not even a catchall.
                break;
        }
}

function blacklist_list() {
	require_once('common/php-asmanager.php');
	global $amp_conf;

        $astman = new AGI_AsteriskManager();
        if ($res = $astman->connect("127.0.0.1", $amp_conf["AMPMGRUSER"] , $amp_conf["AMPMGRPASS"])) {
		$list = $astman->database_show();
		foreach ($list as $k => $v)	{
			if (substr($k, 1, 9) == 'blacklist')
			{
				$numbers[substr($k, 11)] = substr($k, 11);
			}
		}

		if (is_array($numbers))
			natcasesort($numbers);

		return $numbers;
        } else {
                fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
        }
}

function blacklist_del($number){
	require_once('common/php-asmanager.php');
	global $amp_conf;

	$astman = new AGI_AsteriskManager();
        if ($res = $astman->connect("127.0.0.1", $amp_conf["AMPMGRUSER"] , $amp_conf["AMPMGRPASS"])) {
                $astman->database_del("blacklist",$number);
        } else {
                fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
        }
}

function blacklist_add($post){
	require_once('common/php-asmanager.php');
	global $amp_conf;

	if(!blacklist_chk($post))
		return false;
	extract($post);
        $astman = new AGI_AsteriskManager();
        if ($res = $astman->connect("127.0.0.1", $amp_conf["AMPMGRUSER"] , $amp_conf["AMPMGRPASS"])) {
                $astman->database_put("blacklist",$number, '1');
        } else {
                fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
        }
}


// ensures post vars is valid
function blacklist_chk($post){
	return true;
}

?>
