<?php
namespace FreePBX\modules\Blacklist;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
  public function runRestore($restoreobj,$freebpx,$tmpdir){
    $configs = $restoreobj->getConfigs();
    foreach($configs as $item){
      if(empty($item['number'])){
        continue;
      }
      $this->numberAdd($item);
    }
  }
}