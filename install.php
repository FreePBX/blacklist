<?php
// For translations
if (false) {
_("Blacklist a number");
_("Remove a number from the blacklist");
_("Blacklist the last caller");
_("Blacklist");
}

$fcc = new featurecode('blacklist', 'blacklist_add');
$fcc->setDescription('Blacklist a number');
$fcc->setDefault('*30');
$fcc->update();
unset($fcc);

$fcc = new featurecode('blacklist', 'blacklist_remove');
$fcc->setDescription('Remove a number from the blacklist');
$fcc->setDefault('*31');
$fcc->update();
unset($fcc);

$fcc = new featurecode('blacklist', 'blacklist_last');
$fcc->setDescription('Blacklist the last caller');
$fcc->setDefault('*32');
$fcc->update();
unset($fcc);
?>
