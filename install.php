<?php
$blacklistnum = _("Blacklist a number");
$fcc = new featurecode('blacklist', 'blacklist_add');
$fcc->setDescription($blacklistnum);
$fcc->setDefault('*30');
$fcc->update();
unset($fcc);

$blacklistremove = _("Remove a number from the blacklist");
$fcc = new featurecode('blacklist', 'blacklist_remove');
$fcc->setDescription($blacklistremove);
$fcc->setDefault('*31');
$fcc->update();
unset($fcc);

$blacklistlast = _("Blacklist the last caller");
$fcc = new featurecode('blacklist', 'blacklist_last');
$fcc->setDescription($blacklistlast);
$fcc->setDefault('*32');
$fcc->update();
unset($fcc);
?>
