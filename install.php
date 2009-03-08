<?php
//This file is part of FreePBX.
//
//    FreePBX is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 2 of the License, or
//    (at your option) any later version.
//
//    FreePBX is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with FreePBX.  If not, see <http://www.gnu.org/licenses/>.
//
//  Copyright (C) 2006 Magnus Ullberg (magnus@ullberg.us)
//
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
