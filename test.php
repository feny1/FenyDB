<?php

require_once 'FenyDB.php';

$db = new FenyDB('data');

// create accounts and basic auth system

$db->createTable("users");
$db->createColumn("users", "username", "string");
$db->createColumn("users", "password", "string");
$db->createColumn("users", "email", "string");
$db->createColumn("users", "role_id", "number");

$db->insert("users", array("username" => "admin", "password" => "admin", "email" => "[EMAIL_ADDRESS]", "role_id" => 1));
$db->insert("users", array("username" => "user", "password" => "user", "email" => "[EMAIL_ADDRESS]", "role_id" => 2));


