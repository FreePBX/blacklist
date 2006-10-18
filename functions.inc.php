<?php /* $Id */

if( !class_exists('extension') ) {
	require('extensions.class.php');
}
function blacklist_get_config($engine) {
        global $ext;
        switch($engine) {
                case "asterisk":

		      $id = "app-blacklist";
		      $ext->addInclude('from-internal-additional', $id); // Add the include from from-internal

		      $id = "app-blacklist-check";
		      $c = "s";
                      $ext->add($id, $c, '', new ext_lookupblacklist(''));
                      $ext->add($id, $c, '', new ext_gotoif('$["${LOOKUPBLSTATUS}"="FOUND"]', 'blacklisted'));
                      $ext->add($id, $c, '', new ext_return(''));
                      $ext->add($id, $c, 'blacklisted', new ext_answer(''));
                      $ext->add($id, $c, '', new ext_wait(1));
                      $ext->add($id, $c, '', new ext_zapateller(''));
                      $ext->add($id, $c, '', new ext_zapateller(''));
                      $ext->add($id, $c, '', new ext_playback('ss-noservice'));
                      $ext->add($id, $c, '', new ext_hangup(''));

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
	$ext->add($id, $c, '', new ext_set('DB(blacklist/${blacknr})', 1));
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
       	$ext->add($id, $c, '', new ext_set('DB(blacklist/${lastcaller})', 1));
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

		if (isset($numbers) && is_array($numbers))
			natcasesort($numbers);

		return isset($numbers)?$numbers:null;
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
