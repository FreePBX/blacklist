<?php
namespace FreePBX\modules\Blacklist;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
  public function runBackup($id,$transaction){
    $files = [];
    $dirs = [];
    $configs = [];
    $configs = FreePBX::Blacklist()->getBlacklist();
    $this->addConfigs($configs);
  }
}