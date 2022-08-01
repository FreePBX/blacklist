<?php
namespace FreePBX\modules;
// vim: set ai ts=4 sw=4 ft=php expandtab:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2014 Schmooze Com Inc.
//  Copyright 2018 Sangoma Technologies, Inc

use BMO;
use FreePBX_Helpers;

class Blacklist  extends FreePBX_Helpers implements BMO {
	private $objSmsplus = false;
	public function __construct($freepbx = null){
		if ($freepbx == null) {
			throw new \RuntimeException('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->astman = $this->FreePBX->astman;
		if ($this->FreePBX->Modules->checkStatus('smsplus')) {
			$this->objSmsplus = $this->FreePBX->Smsplus->getObject();
		}

		if (false) {
			_('Blacklist a number');
			_('Remove a number from the blacklist');
			_('Blacklist the last caller');
			_('Blacklist');
			_('Adds a number to the Blacklist Module.  All calls from that number to the system will receive a disconnect recording.  Manage these in the Blacklist module.');
			_('Removes a number from the Blacklist Module');
			_('Adds the last caller to the Blacklist Module.  All calls from that number to the system will receive a disconnect recording.');
		}
	}
	public function ajaxRequest($req, &$setting){
		switch ($req) {
			case 'add':
			case 'edit':
			case 'del':
			case 'bulkdelete':
			case 'getJSON':
			case 'calllog':
			case 'smslog':
			return true;
			break;
		}
		return false;
	}

	public function ajaxHandler(){

		$request = $_REQUEST;
		if(!empty($_REQUEST['oldval']) && $_REQUEST['command'] == 'add' ){
			$_REQUEST['command'] = 'edit';
		}
		switch ($_REQUEST['command']) {
			case 'add':
				$this->numberAdd($request);
				return array('status' => true);
			break;
			case 'edit':
				$this->numberDel($request['oldval']);
				$this->numberAdd($request);
				return array('status' => true);
			break;
			case 'bulkdelete':
				$numbers = isset($_REQUEST['numbers'])?$_REQUEST['numbers']:array();
				$numbers = json_decode($numbers, true);
				foreach ($numbers as $number) {
					$this->numberDel($number);
				}
				return array('status' => 'true', 'message' => _("Numbers Deleted"));
			break;
			case 'del':
				$ret = $this->numberDel($request['number']);
				return array('status' => $ret);
			break;
			case 'calllog':
				$mod_cdr = $this->FreePBX->Cdr;
				$number = $request['number'];
				$sql = sprintf('
				SELECT T3.calldate FROM (
					SELECT DISTINCT(T1.linkedid), T2.calldate FROM %1$s T1
					LEFT JOIN (
						SELECT calldate, linkedid, sequence FROM %1$s GROUP BY linkedid ASC, sequence ASC
					) T2 ON T1.linkedid = T2.linkedid AND T1.sequence = T2.sequence
					WHERE T1.src = ?
					GROUP BY T1.linkedid DESC
					ORDER BY T1.sequence ASC
				) T3
				ORDER BY T3.calldate DESC', $mod_cdr->getDbTable());
				$stmt = $mod_cdr->getCdrDbHandle()->prepare($sql);
				$stmt->execute(array($number));
				$ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);
				return $ret;
			break;
			case 'smslog':
				if ($this->objSmsplus) {
					return $this->objSmsplus->getBlocklistedSMS($request['number']);
				}
			break;
			case 'getJSON':
				switch($request['jdata'])
				{
					case 'grid':
						$ret = array();
						$blacklist = $this->getBlacklist();

						$a_numbers = array();
						foreach($blacklist as $item)
						{
							array_push($a_numbers, $item['number']);
						}
						$info_nums = $this->getCountCallIn($a_numbers);
						foreach($blacklist as $item)
						{
							$number = $item['number'];
							if (in_array($number, array('dest', 'blocked', 'blockedSMS'))) {
								continue;
							}
							//if it is 1, do not return anything since it is used for blank description.
							$description = $item['description'] != 1 ? $item['description'] : '';
							$blockType=null;
							$count 		 = $info_nums[$number]['count'];
							$last_date 	 = $info_nums[$number]['last_date'];
							if ($this->objSmsplus) {
								$getNumberDetails = $this->FreePBX->Smsplus->getNumberDetails($number);
								if (!empty($getNumberDetails)) {
									$blockType = $getNumberDetails['blockType'];
									$description = ($getNumberDetails['description'] != 1) ? $getNumberDetails['description'] : '';
								}
							}
							$ret[] = array(
								'number' => $number,
								'description' => $description,
								'blockedType' => $blockType,
								'count' => $count,
								'last_date' => $count == 0 ? _("Never") : $last_date,
							);
						}
						return $ret;
					break;
					case 'count':
						$number = $request['number'];
						$count = $this->getCountCallIn($number);
						return array('status' => true, 'count' => $count);
					break;
				}
			break;
		}
	}

	//BMO Methods
	public function install() {
		$fcc = new \featurecode('blacklist', 'blacklist_add');
		$fcc->setDescription(_('Blacklist a number'));
		$fcc->setHelpText(_('Adds a number to the Blacklist Module. All calls from that number to the system will receive a disconnect recording. Manage these in the Blacklist module.'));
		$fcc->setDefault('*30');
		$fcc->setProvideDest();
		$fcc->update();
		unset($fcc);

		$fcc = new \featurecode('blacklist', 'blacklist_remove');
		$fcc->setDescription(_('Remove a number from the blacklist'));
		$fcc->setHelpText(_('Removes a number from the Blacklist Module'));
		$fcc->setDefault('*31');
		$fcc->setProvideDest();
		$fcc->update();
		unset($fcc);
		$fcc = new \featurecode('blacklist', 'blacklist_last');
		$fcc->setDescription(_('Blacklist the last caller'));
		$fcc->setHelpText(_('Adds the last caller to the Blacklist Module. All calls from that number to the system will receive a disconnect recording.'));
		$fcc->setDefault('*32');
		$fcc->update();
		unset($fcc);
	}
	public function uninstall(){}

	public function backup(){}

	public function restore($backup){}

	public function doConfigPageInit($page) {
		$dispnum = 'blacklist';
		$astver = $this->FreePBX->Config->get('ASTVERSION');
		$request = $_REQUEST;

		if (isset($request['goto0'])) {
			$destination = $request[$request['goto0'].'0'];
		}
		isset($request['action']) ? $action = $request['action'] : $action = '';
		isset($request['oldval']) ? $action = 'edit' : $action;
		isset($request['number']) ? $number = $request['number'] : $number = '';
		isset($request['description']) ? $description = $request['description'] : $description = '';

		if (isset($request['action'])) {
			switch ($action) {
				case 'settings':
					$this->destinationSet($destination);
					$this->blockunknownSet($request['blocked']);
					if ($this->objSmsplus) {
						$this->objSmsplus->blockUnknownSmsSet($request['blockedSMS']);
						needreload();
					}
				break;
				case 'import':
					if ($_FILES['file']['error'] > 0) {
						echo '<div class="alert alert-danger" role="alert">'._('There was an error uploading the file').'</div>';
					} else {
						if (pathinfo($_FILES['blacklistfile']['name'], PATHINFO_EXTENSION) == 'csv') {
							$path = sys_get_temp_dir().'/'.$_FILES['blacklistfile']['name'];
							move_uploaded_file($_FILES['blacklistfile']['tmp_name'], $path);
							if (file_exists($path)) {
								ini_set('auto_detect_line_endings', true);
								$handle = fopen($path, 'r');
								set_time_limit(0);
								while (($data = fgetcsv($handle)) !== false) {
									if ($data[0] == 'number' && $data[1] == 'description') {
										continue;
									}
									blacklist_add(array(
										'number' => $data[0],
										'description' => $data[1],
										'blocked' => 0,
									));
								}
								unlink($path);
								echo '<div class="alert alert-success" role="alert">'._('Sucessfully imported all entries').'</div>';
							} else {
								echo '<div class="alert alert-danger" role="alert">'._('Could not find file after upload').'</div>';
							}
						} else {
							echo '<div class="alert alert-danger" role="alert">'._('The file must be in CSV format!').'</div>';
						}
					}
				break;
				case 'export':
					$list = $this->getBlacklist();
					if (!empty($list)) {
						header('Content-Type: text/csv; charset=utf-8');
						header('Content-Disposition: attachment; filename=blacklist.csv');
						$output = fopen('php://output', 'w');
						fputcsv($output, array('number', 'description'));
						foreach ($list as $l=>$val) {
							if (in_array($val['number'], array('dest', 'blocked', 'blockedSMS'))) {
								continue;
							}
							fputcsv($output, $l);
						}
					} else {
						header('HTTP/1.0 404 Not Found');
						echo _('No Entries to export');
					}
					die();
				break;
			}
		}
	}

	public function myDialplanHooks(){
		return 400;
	}

	public function doDialplanHook(&$ext, $engine, $priority) {
		$modulename = 'blacklist';
		//Add
		$fcc = new \featurecode($modulename, 'blacklist_add');
		$addfc = $fcc->getCodeActive();
		unset($fcc);
		//Delete
		$fcc = new \featurecode($modulename, 'blacklist_remove');
		$delfc = $fcc->getCodeActive();
		unset($fcc);
		//Last
		$fcc = new \featurecode($modulename, 'blacklist_last');
		$lastfc = $fcc->getCodeActive();
		unset($fcc);
		$id = 'app-blacklist';
		$c = 's';
		$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal
		$ext->add($id, $c, '', new \ext_macro('user-callerid'));
		$id = 'app-blacklist-check';
		// LookupBlackList doesn't seem to match empty astdb entry for "blacklist/", so we
		// need to check for the setting and if set, send to the blacklisted area
		// The gotoif below is not a typo.  For some reason, we've seen the CID number set to Unknown or Unavailable
		// don't generate the dialplan if they are not using the function
		//
		if ($this->astman->database_get('blacklist', 'blocked') == '1') {
			$ext->add($id, $c, '', new \ext_gotoif('$["${CALLERID(number)}" = "Unknown"]', 'check-blocked'));
			$ext->add($id, $c, '', new \ext_gotoif('$["${CALLERID(number)}" = "Unavailable"]', 'check-blocked'));
			$ext->add($id, $c, '', new \ext_gotoif('$["foo${CALLERID(number)}" = "foo"]', 'check-blocked', 'check'));
			$ext->add($id, $c, 'check-blocked', new \ext_gotoif('$["${DB(blacklist/blocked)}" = "1"]', 'blacklisted'));
		}
		if (!$this->objSmsplus) {
			$ext->add($id, $c, 'check', new \ext_gotoif('$["${BLACKLIST()}"="1"]', 'blacklisted'));
			$ext->add($id, $c, '', new \ext_setvar('CALLED_BLACKLIST', '1'));
		} else {
			$this->objSmsplus->dialplanHook($ext, $engine, $priority);
		}
		$ext->add($id, $c, '', new \ext_return(''));
		$ext->add($id, $c, 'blacklisted', new \ext_answer(''));
		$ext->add($id, $c, '', new \ext_set('BLDEST', '${DB(blacklist/dest)}'));
		$ext->add($id, $c, '', new \ext_execif('$["${BLDEST}"=""]', 'Set', 'BLDEST=app-blackhole,hangup,1'));
		$ext->add($id, $c, '', new \ext_gotoif('$["${returnhere}"="1"]', 'returnto'));
		$ext->add($id, $c, '', new \ext_gotoif('${LEN(${BLDEST})}', '${BLDEST}', 'app-blackhole,zapateller,1'));
		$ext->add($id, $c, 'returnto', new \ext_return());
		/*
		$ext->add($id, $c, '', new \ext_wait(1));
		$ext->add($id, $c, '', new \ext_zapateller(''));
		$ext->add($id, $c, '', new \ext_playback('ss-noservice'));
		$ext->add($id, $c, '', new \ext_hangup(''));
		$modulename = 'blacklist';
		*/
		//Dialplan for add
		if(!empty($addfc)){
			$ext->add('app-blacklist', $addfc, '', new \ext_goto('1', 's', 'app-blacklist-add'));
		}
		$id = 'app-blacklist-add';
		$c = 's';
		$ext->add($id, $c, '', new \ext_answer());
		$ext->add($id, $c, '', new \ext_macro('user-callerid'));
		$ext->add($id, $c, '', new \ext_wait(1));
		$ext->add($id, $c, '', new \ext_set('NumLoops', 0));
		$ext->add($id, $c, 'start', new \ext_digittimeout(5));
		$ext->add($id, $c, '', new \ext_responsetimeout(10));
		$ext->add($id, $c, '', new \ext_read('blacknr', 'enter-num-blacklist&vm-then-pound'));
		$ext->add($id, $c, '', new \ext_saydigits('${blacknr}'));
		// i18n - Some languages need this is a different format. If we don't
		// know about the language, assume english
		$ext->add($id, $c, '', new \ext_gosubif('$[${DIALPLAN_EXISTS('.$id.',${CHANNEL(language)})}]', $id.',${CHANNEL(language)},1', $id.',en,1'));
		// en - default
		$ext->add($id, 'en', '', new \ext_digittimeout(1));
		$ext->add($id, 'en', '', new \ext_read('confirm','if-correct-press&digits/1&to-enter-a-diff-number&press&digits/2'));
		$ext->add($id, 'en', '', new \ext_return());
		// ja
		$ext->add($id, 'ja', '', new \ext_digittimeout(1));
		$ext->add($id, 'ja', '', new \ext_read('if-correct-press&digits/1&pleasepress'));
		$ext->add($id, 'ja', '', new \ext_return());

		$ext->add($id, $c, '', new \ext_gotoif('$[ "${confirm}" = "1" ]','app-blacklist-add,1,1'));
		$ext->add($id, $c, '', new \ext_gotoif('$[ "${confirm}" = "2" ]','app-blacklist-add,2,1'));
		$ext->add($id, $c, '', new \ext_goto('app-blacklist-add-invalid,s,1'));

		$c = '1';
		$ext->add($id, $c, '', new \ext_gotoif('$[ "${blacknr}" != ""]', '', 'app-blacklist-add-invalid,s,1'));
		if ($this->objSmsplus) {
			$ext->add($id, $c, '', new \ext_set('DB(blacklist/${blacknr})', '1/Call'));
		} else {
			$ext->add($id, $c, '', new \ext_set('DB(blacklist/${blacknr})', 1));
		}
		$ext->add($id, $c, '', new \ext_playback('num-was-successfully&added'));
		$ext->add($id, $c, '', new \ext_wait(1));
		$ext->add($id, $c, '', new \ext_hangup());

        $c = '2';
        $ext->add($id, $c, '', new \ext_set('NumLoops', '$[${NumLoops} + 1]'));
        $ext->add($id, $c, '', new \ext_gotoif('$[${NumLoops} < 3]', 'app-blacklist-add,s,start'));
        $ext->add($id, $c, '', new \ext_playback('sorry-youre-having-problems&goodbye'));
        $ext->add($id, $c, '', new \ext_hangup());


		$id = 'app-blacklist-add-invalid';
		$c = 's';
		$ext->add($id, $c, '', new \ext_set('NumLoops', '$[${NumLoops} + 1]'));
		$ext->add($id, $c, '', new \ext_playback('pm-invalid-option'));
		$ext->add($id, $c, '', new \ext_gotoif('$[${NumLoops} < 3]', 'app-blacklist-add,s,start'));
		$ext->add($id, $c, '', new \ext_playback('sorry-youre-having-problems&goodbye'));
		$ext->add($id, $c, '', new \ext_hangup());

		//Del
		if(!empty($delfc)){
			$ext->add('app-blacklist', $delfc, '', new \ext_goto('1', 's', 'app-blacklist-remove'));
		}
		$id = 'app-blacklist-remove';
		$c = 's';
		$ext->add($id, $c, '', new \ext_answer());
		$ext->add($id, $c, '', new \ext_macro('user-callerid'));
		$ext->add($id, $c, '', new \ext_set('NumLoops', 0));
        $ext->add($id, $c, '', new \ext_wait(1));
		$ext->add($id, $c, 'start', new \ext_digittimeout(5));
		$ext->add($id, $c, '', new \ext_responsetimeout(10));
		$ext->add($id, $c, '', new \ext_read('blacknr', 'entr-num-rmv-blklist&vm-then-pound'));
		$ext->add($id, $c, '', new \ext_saydigits('${blacknr}'));
		// i18n - Some languages need this is a different format. If we don't
		// know about the language, assume english
		$ext->add($id, $c, '', new \ext_gosubif('$[${DIALPLAN_EXISTS('.$id.',${CHANNEL(language)})}]', $id.',${CHANNEL(language)},1', $id.',en,1'));
		// en - default
		$ext->add($id, 'en', '', new \ext_digittimeout(1));
		$ext->add($id, 'en', '', new \ext_read('confirm','if-correct-press&digits/1&to-enter-a-diff-number&press&digits/2'));
		$ext->add($id, 'en', '', new \ext_return());
		// ja
		$ext->add($id, 'ja', '', new \ext_digittimeout(1));
		$ext->add($id, 'ja', '', new \ext_read('confirm','if-correct-press&digits/1&pleasepress'));
		$ext->add($id, 'ja', '', new \ext_return());

		$ext->add($id, $c, '', new \ext_gotoif('$[ "${confirm}" = "1" ]','app-blacklist-remove,1,1'));
	    $ext->add($id, $c, '', new \ext_gotoif('$[ "${confirm}" = "2" ]','app-blacklist-remove,2,1'));
	    $ext->add($id, $c, '', new \ext_goto('app-blacklist-add-invalid,s,1'));

		$c = '1';
		$ext->add($id, $c, '', new \ext_dbdel('blacklist/${blacknr}'));
		$ext->add($id, $c, '', new \ext_playback('num-was-successfully&removed'));
		$ext->add($id, $c, '', new \ext_wait(1));
		$ext->add($id, $c, '', new \ext_hangup());

        $c = '2';
        $ext->add($id, $c, '', new \ext_set('NumLoops', '$[${NumLoops} + 1]'));
        $ext->add($id, $c, '', new \ext_gotoif('$[${NumLoops} < 3]', 'app-blacklist-remove,s,start'));
        $ext->add($id, $c, '', new \ext_playback('goodbye'));
        $ext->add($id, $c, '', new \ext_hangup());


        $id = 'app-blacklist-remove-invalid';
        $c = 's';
        $ext->add($id, $c, '', new \ext_set('NumLoops', '$[${NumLoops} + 1]'));
        $ext->add($id, $c, '', new \ext_playback('pm-invalid-option'));
        $ext->add($id, $c, '', new \ext_gotoif('$[${NumLoops} < 3]', 'app-blacklist-remove,s,start'));
        $ext->add($id, $c, '', new \ext_playback('sorry-youre-having-problems&goodbye'));
        $ext->add($id, $c, '', new \ext_hangup());

        //Last
		if(!empty($lastfc)){
			$ext->add('app-blacklist', $lastfc, '', new \ext_goto('1', 's', 'app-blacklist-last'));
		}
		$id = 'app-blacklist-last';
		$c = 's';
		$ext->add($id, $c, '', new \ext_answer());
		$ext->add($id, $c, '', new \ext_macro('user-callerid'));
		$ext->add($id, $c, '', new \ext_wait(1));
		$ext->add($id, $c, '', new \ext_setvar('lastcaller', '${DB(CALLTRACE/${AMPUSER})}'));
		$ext->add($id, $c, '', new \ext_gotoif('$[ $[ "${lastcaller}" = "" ] | $[ "${lastcaller}" = "unknown" ] ]', 'noinfo'));
		$ext->add($id, $c, '', new \ext_playback('privacy-to-blacklist-last-caller&telephone-number'));
		$ext->add($id, $c, '', new \ext_saydigits('${lastcaller}'));
		$ext->add($id, $c, '', new \ext_setvar('TIMEOUT(digit)', '1'));
		$ext->add($id, $c, '', new \ext_setvar('TIMEOUT(response)', '7'));
		// i18n - Some languages need this is a different format. If we don't
		// know about the language, assume english
		$ext->add($id, $c, '', new \ext_gosubif('$[${DIALPLAN_EXISTS('.$id.',${CHANNEL(language)})}]', $id.',${CHANNEL(language)},1', $id.',en,1'));
		// en - default
		$ext->add($id, 'en', '', new \ext_read('confirm','if-correct-press&digits/1'));
		$ext->add($id, 'en', '', new \ext_return());
		// ja
		$ext->add($id, 'ja', '', new \ext_read('confirm','if-correct-press&digits/1&pleasepress'));
		$ext->add($id, 'ja', '', new \ext_return());

		$ext->add($id, $c, '', new \ext_gotoif('$[ "${confirm}" = "1" ]','app-blacklist-last,1,1'));
		$ext->add($id, $c, '', new \ext_goto('end'));
		$ext->add($id, $c, 'noinfo', new \ext_playback('unidentified-no-callback'));
		$ext->add($id, $c, '', new \ext_hangup());
		$ext->add($id, $c, '', new \ext_noop('Waiting for input'));
		$ext->add($id, $c, 'end', new \ext_playback('sorry-youre-having-problems&goodbye'));
		$ext->add($id, $c, '', new \ext_hangup());

		$c = '1';
		if ($this->objSmsplus) {
			$ext->add($id, $c, '', new \ext_set('DB(blacklist/${lastcaller})', '1/Call'));
		} else {
			$ext->add($id, $c, '', new \ext_set('DB(blacklist/${lastcaller})', 1));
		}
		$ext->add($id, $c, '', new \ext_playback('num-was-successfully'));
		$ext->add($id, $c, '', new \ext_playback('added'));
		$ext->add($id, $c, '', new \ext_wait(1));
		$ext->add($id, $c, '', new \ext_hangup());

		$ext->add($id, 'i', '', new \ext_playback('sorry-youre-having-problems&goodbye'));
		$ext->add($id, 'i', '', new \ext_hangup());
	}

	public function getActionBar($request) {
		$buttons = array();
		switch ($request['display']) {
			case 'blacklist':
			$buttons = array(
				'reset' => array(
					'name' => 'reset',
					'id' => 'Reset',
					'class' => 'hidden',
					'value' => _('Reset'),
				),
				'submit' => array(
					'name' => 'submit',
					'class' => 'hidden',
					'id' => 'Submit',
					'value' => _('Submit'),
				),
			);

			return $buttons;
			break;
		}
	}

	//Blacklist Methods
	public function showPage() {
		$blacklistitems = $this->getBlacklist();
		$destination = $this->destinationGet();
		$filter_blocked = $this->blockunknownGet() == 1 ? true : false;
		$view = isset($_REQUEST['view'])?$_REQUEST['view']:'';
		switch ($view) {
			case 'grid':
			return load_view(__DIR__.'/views/blgrid.php', array('blacklist' => $blacklistitems, 'objSmsplus' => $this->objSmsplus));
			break;
			default:
			return load_view(__DIR__.'/views/general.php', array('blacklist' => $blacklistitems, 'destination' => $destination, 'filter_blocked' => $filter_blocked, 'objSmsplus' => $this->objSmsplus));
			break;
		}
	}

	/**
	 * Get lists
	 * @return array Black listed numbers
	 */
	public function getBlacklist() {
		if ($this->astman->connected()) {
			$list = $this->astman->database_show('blacklist');
			$blacklisted = array();
			foreach ($list as $k => $v) {
				$numbers = substr($k, 11);
				$blacklisted[] = array('number' => $numbers, 'description' => $v);
			}
			return $blacklisted;
		} else {
			throw new \RuntimeException(_('Cannot connect to Asterisk Manager, is Asterisk running?'));
		}
	}


	/**
	 * Add Number
	 * @param  array $post Array of blacklist params
	 */
	public function numberAdd($post){
		if ($this->astman->connected()) {
			$blockType =null;
			if (in_array($post['number'], array('dest', 'blocked', 'blockedSMS'))) {
				unset($post['blockType']);
			} else {
				if ($this->objSmsplus) {
					$blockType = (!empty($post['blockType'])) ? '/' . $post['blockType'] : '/Call';
				}
			}
			$post['description'] = $post['description'] == '' ? $post['description'] = '1'. $blockType : $post['description'] . $blockType;
			$this->astman->database_put('blacklist', $post['number'], htmlentities($post['description'], ENT_COMPAT | ENT_HTML401, "UTF-8"));
		} else {
			throw new \RuntimeException(_('Cannot connect to Asterisk Manager, is Asterisk running?'));
		}
		return $post['number'];
	}

	/**
	 * Delete a number
	 * @param  string $number Number to delete
	 * @return boolean         Status of deletion
	 */
	public function numberDel($number){
		if ($this->astman->connected()) {
			return($this->astman->database_del('blacklist', $number));
		} else {
			throw new \RuntimeException(_('Cannot connect to Asterisk Manager, is Asterisk running?'));
		}
	}

	/**
	 * Set blacklist destination
	 * @param  string $dest Destination
	 * @return boolean       Status of set
	 */
	public function destinationSet($dest) {
		if ($this->astman->connected()) {
			$this->astman->database_del('blacklist', 'dest');
			if (!empty($dest)) {
				return $this->astman->database_put('blacklist', 'dest', $dest);
			} else {
				return true;
			}
		} else {
			throw new \RuntimeException(_('Cannot connect to Asterisk Manager, is Asterisk running?'));
		}
	}

	/**
	 * Get the destination
	 * @return string The destination
	 */
	public function destinationGet(){
		if ($this->astman->connected()) {
			return $this->astman->database_get('blacklist', 'dest');
		} else {
			throw new \RuntimeException(_('Cannot connect to Asterisk Manager, is Asterisk running?'));
		}
	}

	/**
	 * Whether to block unknown calls
	 * @param  boolean $blocked True to block, false otherwise
	 */
	public function blockunknownSet($blocked){
		if ($this->astman->connected()) {
			// Remove filtering for blocked/unknown cid
			$this->astman->database_del('blacklist', 'blocked');
			// Add it back if it's checked
			if (!empty($blocked)) {
				$this->astman->database_put('blacklist', 'blocked', '1');
			}
		} else {
			throw new \RuntimeException(_('Cannot connect to Asterisk Manager, is Asterisk running?'));
		}
	}

	/**
	 * Get status of unknown blocking
	 * @return string 1 if blocked, 0 otherwise
	 */
	public function blockunknownGet(){
		if ($this->astman->connected()) {
			return $this->astman->database_get('blacklist', 'blocked');
		} else {
			throw new \RuntimeException(_('Cannot connect to Asterisk Manager, is Asterisk running?'));
		}
	}
	//BulkHandler hooks
	public function bulkhandlerGetTypes() {
		return array(
			'blacklist' => array(
				'name' => _('Blacklist'),
				'description' => _('Import/Export Caller Blacklist')
			)
		);
	}
	public function bulkhandlerGetHeaders($type) {
		switch($type){
			case 'blacklist':
				$headers = array();
				$headers['number'] = array(
					'required' => true,
					'identifier' => _("Phone Number"),
					'description' => _("The number as it appears in the callerid display")
				);
				$headers['description'] = array(
					'required' => false,
					'identifier' => _("Description"),
					'description' => _("Description of number blacklisted")
				);
			break;
		}
		return $headers;
	}
	public function bulkhandlerImport($type, $rawData, $replaceExisting = true) {
		$blistnums = array();
		if(!$replaceExisting){
			$blist = $this->getBlacklist();
			foreach ($blist as $value) {
				$blistnums[] = $value['number'];
			}
		}
		switch($type){
			case 'blacklist':
				foreach($rawData as $data){
					if(empty($data['number'])){
						return array('status' => false, 'message'=> _('Phone Number Required'));
					}
					//Skip existing numbers. Array is only populated if replace is false.
					if(in_array($data['number'], $blistnums)){
						continue;
					}
					if ($this->objSmsplus) {
						$descblockedtype = explode('/',$data['description']);
						$data['blockType'] = (in_array(end($descblockedtype), array('Call', 'Sms', 'Both'))) ? end($descblockedtype) : 'Call';
						$data['description'] = str_replace('/' . $data['blockType'],'',$data['description']);
					}
					$this->numberAdd($data);
				}
			break;
		}
		return array('status' => true);
	}
	public function bulkhandlerExport($type) {
		$data = NULL;
		switch ($type) {
			case 'blacklist':
				$data = $this->getBlacklist();
				foreach ($data as $key=>$val) {
					if (in_array($val['number'], array('dest', 'blocked', 'blockedSMS'))) {
						unset($data[$key]);
					}
				}
			break;
		}
		return $data;
	}

	/**
	 * Gets the number of calls from the specified number
	 * @param string $number number of which we want to know the number of incoming calls
	 * @return int number of incoming calls recorded
	 */
	public function getCountCallIn($number) {
		$return_data = 0;
		if (! empty( is_array($number) ? $number : trim($number)))
		{
			$mod_cdr = $this->FreePBX->Cdr;
			$mod_cdr_db = $mod_cdr->getCdrDbHandle();

			$return_array = array();
			$number_process = is_array($number) ? $number : array($number);
			foreach ($number_process as $num) {
				$return_array[$num] = array(
					'count' => 0,
					'last_date' => '',
				);
			}
			
			$place_holders = implode(',', array_fill(0, count($number_process), '?'));
			$number_process = array_map( function($value) { return (string)$value; }, $number_process );
			$sql = sprintf('
			SELECT DISTINCT(T1.src) as src, T2.ncount, T3.calldate FROM %1$s T1
			LEFT JOIN (
				SELECT COUNT(DISTINCT linkedid) AS ncount, src FROM %1$s GROUP BY src
			) T2 ON T1.src = T2.src
			LEFT JOIN (
				SELECT MAX(calldate) AS calldate, src FROM %1$s GROUP BY src
			) T3 ON T1.src = T3.src
			WHERE T1.src IN (%2$s)
			GROUP BY T1.src
			', $mod_cdr->getDbTable(), $place_holders);

			$stmt = $mod_cdr_db->prepare($sql);
			if ($stmt->execute($number_process))
			{
				$res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
				foreach ($res as $row)
				{
					$return_array[$row['src']] = array(
						'count' => $row['ncount'],
						'last_date' => $row['calldate'],
					);
				}
			}
			
			$return_data = is_array($number) ? $return_array : $return_array[$number]['count'];
		}
		return $return_data;
	}
}