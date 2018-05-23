<?php
namespace FreePBX\modules\Blacklist;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
  public function runRestore($jobid){
    $configs = $this->restoreObj->getConfigs();
    foreach($configs as $item){
      if(empty($item['number'])){
        continue;
      }
      $this->FreePBX->Blacklist->numberAdd($item);
    }
  }
}