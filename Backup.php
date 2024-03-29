<?php
namespace FreePBX\modules\Blacklist;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$configs = [
			'data' 		=> $this->FreePBX->Blacklist->getBlacklist(),
			'features' 	=> $this->dumpFeatureCodes(),
		];
		$this->addConfigs($configs);
	}
}