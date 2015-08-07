<?php

// vim: set ai ts=4 sw=4 ft=php expandtab:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2014 Schmooze Com Inc.
//

class Blacklist implements BMO {
	public function __construct($freepbx = null){
		if ($freepbx == null) {
			throw new Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->astman = $this->FreePBX->astman;

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
		$setting['authenticate'] = false;
		$setting['allowremote'] = false;
		switch ($req) {
			case 'add':
			case 'edit':
			case 'del':
			case 'getJSON':
			case 'calllog':
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
			case 'del':
				$ret = $this->numberDel($request['number']);
				return array('status' => $ret);
			break;
			case 'calllog':
				$number = $request['number'];
				$sql = 'SELECT calldate FROM asteriskcdrdb.cdr WHERE src = ?';
				$stmt = \FreePBX::Database()->prepare($sql);
				$stmt->execute(array($number));
				$ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);
				return $ret;
			break;
			case 'getJSON':
			switch($request['jdata']){
				case 'grid':
					$ret = array();
					$blacklist = $this->getBlacklist();
					foreach($blacklist as $item){
						$number = $item['number'];
						$description = $item['description'];
						if($number == 'dest' || $number == 'blocked'){
							continue;
						}else{
							$ret[] = array('number' => $number, 'description' => $description);
						}
					}
				return $ret;
				break;
			}
			break;
		}
	}

	//BMO Methods
	public function install() {
		$fcc = new \featurecode('blacklist', 'blacklist_add');
		$fcc->setDescription('Blacklist a number');
		$fcc->setHelpText('Adds a number to the Blacklist Module.  All calls from that number to the system will receive a disconnect recording.  Manage these in the Blacklist module.');
		$fcc->setDefault('*30');
		$fcc->setProvideDest();
		$fcc->update();
		unset($fcc);

		$fcc = new \featurecode('blacklist', 'blacklist_remove');
		$fcc->setDescription('Remove a number from the blacklist');
		$fcc->setHelpText('Removes a number from the Blacklist Module');
		$fcc->setDefault('*31');
		$fcc->setProvideDest();
		$fcc->update();
		unset($fcc);
		$fcc = new featurecode('blacklist', 'blacklist_last');
		$fcc->setDescription('Blacklist the last caller');
		$fcc->setHelpText('Adds the last caller to the Blacklist Module.  All calls from that number to the system will receive a disconnect recording.');
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
						foreach ($list as $l) {
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
		return true;
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
		$ext->add($id, $c, '', new ext_macro('user-callerid'));
		$id = 'app-blacklist-check';
		// LookupBlackList doesn't seem to match empty astdb entry for "blacklist/", so we
		// need to check for the setting and if set, send to the blacklisted area
		// The gotoif below is not a typo.  For some reason, we've seen the CID number set to Unknown or Unavailable
		// don't generate the dialplan if they are not using the function
		//
		if ($this->astman->database_get('blacklist', 'blocked') == '1') {
			$ext->add($id, $c, '', new ext_gotoif('$["${CALLERID(number)}" = "Unknown"]', 'check-blocked'));
			$ext->add($id, $c, '', new ext_gotoif('$["${CALLERID(number)}" = "Unavailable"]', 'check-blocked'));
			$ext->add($id, $c, '', new ext_gotoif('$["foo${CALLERID(number)}" = "foo"]', 'check-blocked', 'check'));
			$ext->add($id, $c, 'check-blocked', new ext_gotoif('$["${DB(blacklist/blocked)}" = "1"]', 'blacklisted'));
		}

		$ext->add($id, $c, 'check', new ext_gotoif('$["${BLACKLIST()}"="1"]', 'blacklisted'));
		$ext->add($id, $c, '', new ext_setvar('CALLED_BLACKLIST', '1'));
		$ext->add($id, $c, '', new ext_return(''));
		$ext->add($id, $c, 'blacklisted', new ext_answer(''));
		$ext->add($id, $c, '', new ext_set('BLDEST', '${DB(blacklist/dest)}'));
		$ext->add($id, $c, '', new ext_gotoif('${BLDEST}', '${BLDEST}', 'app-blackhole,zapateller,1'));
		/*
		$ext->add($id, $c, '', new ext_wait(1));
		$ext->add($id, $c, '', new ext_zapateller(''));
		$ext->add($id, $c, '', new ext_playback('ss-noservice'));
		$ext->add($id, $c, '', new ext_hangup(''));
		$modulename = 'blacklist';
		*/
		//Dialplan for add
		$ext->add('app-blacklist', $addfc, '', new ext_goto('1', 's', 'app-blacklist-add'));

		$id = 'app-blacklist-add';
		$c = 's';
		$ext->add($id, $c, '', new ext_answer());
		$ext->add($id, $c, '', new ext_macro('user-callerid'));
		$ext->add($id, $c, '', new ext_wait(1));
		$ext->add($id, $c, '', new ext_set('NumLoops', 0));
		$ext->add($id, $c, 'start', new ext_playback('enter-num-blacklist'));
		$ext->add($id, $c, '', new ext_digittimeout(5));
		$ext->add($id, $c, '', new ext_responsetimeout(60));
		$ext->add($id, $c, '', new ext_read('blacknr', 'then-press-pound'));
		$ext->add($id, $c, '', new ext_saydigits('${blacknr}'));
		// i18n - Some languages need this is a different format. If we don't
		// know about the language, assume english
		$ext->add($id, $c, '', new ext_gosubif('$[${DIALPLAN_EXISTS('.$id.',${CHANNEL(language)})}]', $id.',${CHANNEL(language)},1', $id.',en,1'));
		// en - default
		$ext->add($id, 'en', '', new ext_playback('if-correct-press&digits/1'));
		$ext->add($id, 'en', '', new ext_return());
		// ja
		$ext->add($id, 'ja', '', new ext_playback('if-correct-press&digits/1&pleasepress'));
		$ext->add($id, 'ja', '', new ext_return());

		$ext->add($id, $c, '', new ext_noop('Waiting for input'));
		$ext->add($id, $c, 'end', new ext_waitexten(10));
		$ext->add($id, $c, '', new ext_playback('sorry-youre-having-problems&goodbye'));
		$ext->add($id, $c, '', new ext_wait(1));
		$ext->add($id, $c, '', new ext_hangup());
		$c = '1';
		$ext->add($id, $c, '', new ext_gotoif('$[ "${blacknr}" != ""]', '', 'app-blacklist-add-invalid,s,1'));
		$ext->add($id, $c, '', new ext_set('DB(blacklist/${blacknr})', 1));
		$ext->add($id, $c, '', new ext_playback('num-was-successfully&added'));
		$ext->add($id, $c, '', new ext_wait(1));
		$ext->add($id, $c, '', new ext_hangup());

		$id = 'app-blacklist-add-invalid';
		$c = 's';
		$ext->add($id, $c, '', new ext_set('NumLoops', '$[${NumLoops} + 1]'));
		$ext->add($id, $c, '', new ext_playback('pm-invalid-option'));
		$ext->add($id, $c, '', new ext_gotoif('$[${NumLoops} < 3]', 'app-blacklist-add,s,start'));
		$ext->add($id, $c, '', new ext_playback('goodbye'));
		$ext->add($id, $c, '', new ext_hangup());

		//Del
		$ext->add('app-blacklist', $delfc, '', new ext_goto('1', 's', 'app-blacklist-remove'));

		$id = 'app-blacklist-remove';
		$c = 's';
		$ext->add($id, $c, '', new ext_answer());
		$ext->add($id, $c, '', new ext_macro('user-callerid'));
		$ext->add($id, $c, '', new ext_wait(1));
		$ext->add($id, $c, '', new ext_playback('entr-num-rmv-blklist'));
		$ext->add($id, $c, '', new ext_digittimeout(5));
		$ext->add($id, $c, '', new ext_responsetimeout(60));
		$ext->add($id, $c, '', new ext_read('blacknr', 'then-press-pound'));
		$ext->add($id, $c, '', new ext_saydigits('${blacknr}'));
		// i18n - Some languages need this is a different format. If we don't
		// know about the language, assume english
		$ext->add($id, $c, '', new ext_gosubif('$[${DIALPLAN_EXISTS('.$id.',${CHANNEL(language)})}]', $id.',${CHANNEL(language)},1', $id.',en,1'));
		// en - default
		$ext->add($id, 'en', '', new ext_playback('if-correct-press&digits/1'));
		$ext->add($id, 'en', '', new ext_return());
		// ja
		$ext->add($id, 'ja', '', new ext_playback('if-correct-press&digits/1&pleasepress'));
		$ext->add($id, 'ja', '', new ext_return());

		$ext->add($id, $c, '', new ext_noop('Waiting for input'));
		$ext->add($id, $c, 'end', new ext_waitexten(60));
		$ext->add($id, $c, '', new ext_playback('sorry-youre-having-problems&goodbye'));
		$c = '1';
		$ext->add($id, $c, '', new ext_dbdel('blacklist/${blacknr}'));
		$ext->add($id, $c, '', new ext_playback('num-was-successfully&removed'));
		$ext->add($id, $c, '', new ext_wait(1));
		$ext->add($id, $c, '', new ext_hangup());
		//Last
		$ext->add('app-blacklist', $lastfc, '', new ext_goto('1', 's', 'app-blacklist-last'));

		$id = 'app-blacklist-last';
		$c = 's';
		$ext->add($id, $c, '', new ext_answer());
		$ext->add($id, $c, '', new ext_macro('user-callerid'));
		$ext->add($id, $c, '', new ext_wait(1));
		$ext->add($id, $c, '', new ext_setvar('lastcaller', '${DB(CALLTRACE/${AMPUSER})}'));
		$ext->add($id, $c, '', new ext_gotoif('$[ $[ "${lastcaller}" = "" ] | $[ "${lastcaller}" = "unknown" ] ]', 'noinfo'));
		$ext->add($id, $c, '', new ext_playback('privacy-to-blacklist-last-caller&telephone-number'));
		$ext->add($id, $c, '', new ext_saydigits('${lastcaller}'));
		$ext->add($id, $c, '', new ext_setvar('TIMEOUT(digit)', '3'));
		$ext->add($id, $c, '', new ext_setvar('TIMEOUT(response)', '7'));
		// i18n - Some languages need this is a different format. If we don't
		// know about the language, assume english
		$ext->add($id, $c, '', new ext_gosubif('$[${DIALPLAN_EXISTS('.$id.',${CHANNEL(language)})}]', $id.',${CHANNEL(language)},1', $id.',en,1'));
		// en - default
		$ext->add($id, 'en', '', new ext_playback('if-correct-press&digits/1'));
		$ext->add($id, 'en', '', new ext_return());
		// ja
		$ext->add($id, 'ja', '', new ext_playback('if-correct-press&digits/1&pleasepress'));
		$ext->add($id, 'ja', '', new ext_return());

		$ext->add($id, $c, '', new ext_goto('end'));
		$ext->add($id, $c, 'noinfo', new ext_playback('unidentified-no-callback'));
		$ext->add($id, $c, '', new ext_hangup());
		$ext->add($id, $c, '', new ext_noop('Waiting for input'));
		$ext->add($id, $c, 'end', new ext_waitexten(60));
		$ext->add($id, $c, '', new ext_playback('sorry-youre-having-problems&goodbye'));
		$c = '1';
		$ext->add($id, $c, '', new ext_set('DB(blacklist/${lastcaller})', 1));
		$ext->add($id, $c, '', new ext_playback('num-was-successfully'));
		$ext->add($id, $c, '', new ext_playback('added'));
		$ext->add($id, $c, '', new ext_wait(1));
		$ext->add($id, $c, '', new ext_hangup());
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
				'upload' => array(
					'name' => 'upload',
					'class' => 'hidden',
					'id' => 'Upload',
					'value' => _('Upload'),
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
			return load_view(__DIR__.'/views/blgrid.php', array('blacklist' => $blacklistitems));
			break;
			default:
			return load_view(__DIR__.'/views/general.php', array('blacklist' => $blacklistitems, 'destination' => $destination, 'filter_blocked' => $filter_blocked));
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
			throw new Exception('Cannot connect to Asterisk Manager, is Asterisk running?');
		}
	}

	/**
	 * Add Number
	 * @param  array $post Array of blacklist params
	 */
	public function numberAdd($post){
		extract($post);
		if ($this->astman->connected()) {
			$post['description'] == '' ? $post['description'] = '1' : $post['description'];
			$this->astman->database_put('blacklist', $post['number'], '"'.$post['description'].'"');
		} else {
			throw new Exception('Cannot connect to Asterisk Manager, is Asterisk running?');
		}
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
			throw new Exception('Cannot connect to Asterisk Manager, is Asterisk running?');
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
			throw new Exception('Cannot connect to Asterisk Manager, is Asterisk running?');
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
			throw new Exception('Cannot connect to Asterisk Manager, is Asterisk running?');
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
			throw new Exception('Cannot connect to Asterisk Manager, is Asterisk running?');
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
			throw new Exception('Cannot connect to Asterisk Manager, is Asterisk running?');
		}
	}
}
