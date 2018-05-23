<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */

function blacklist_get_config($engine) {
    //Handled in class
}

function blacklist_blacklist_add($fc) {
	return FreePBX::Blacklist()->numberAdd($fc);
}

function blacklist_blacklist_remove($fc) {
	return FreePBX::Blacklist()->numberDelete($fc);
}

function blacklist_blacklist_last($fc) {
	return FreePBX::Blacklist()->getBlacklist();
}

function blacklist_list() {
	return FreePBX::Blacklist()->getBlacklist();
}

function blacklist_del($number){
	return FreePBX::Blacklist()->numberDel($number);
}

function blacklist_add($post){
	return FreePBX::Blacklist()->numberAdd($post);
}

// ensures post vars is valid
function blacklist_chk($post){
	return FreePBX::Blacklist()->checkPost($post);
}
