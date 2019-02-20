<?php
namespace FreePBX\modules\Blacklist;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore($jobid){
		$configs = $this->getConfigs();
		foreach($configs as $item){
			if(empty($item['number'])){
				continue;
			}
			$this->FreePBX->Blacklist->numberAdd($item);
		}
	}
	public function processLegacy($pdo, $data, $tables, $unknownTables){
		if(!isset($astdb['blacklist'])){
			return $this;
		}
		foreach($astdb['blacklist'] as $number => $desc){
			$this->FreePBX->Blacklist->numberAdd(['number' => $number, 'description' => $desc]);
		}
	}
}