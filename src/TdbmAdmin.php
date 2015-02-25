<?php
use Mouf\MoufManager;
use Mouf\MoufUtils;

MoufUtils::registerMainMenu('dbMainMenu', 'DB', null, 'mainMenu', 70);
MoufUtils::registerMenuItem('dbTDBMAdminSubMenu', 'DAOs', null, 'dbMainMenu', 80);
MoufUtils::registerChooseInstanceMenuItem('dbTDBMGenereateDAOAdminSubMenu', 'Generate DAOs', 'tdbmadmin/', "Mouf\\Database\\TDBM\\TDBMService", 'dbTDBMAdminSubMenu', 10);

// Controller declaration
$moufManager = MoufManager::getMoufManager();
$moufManager->declareComponent('tdbmadmin', 'Mouf\\Database\\TDBM\\Controllers\\TdbmController', true);
$moufManager->bindComponents('tdbmadmin', 'template', 'moufTemplate');
$moufManager->bindComponents('tdbmadmin', 'content', 'block.content');

$moufManager->declareComponent('tdbminstall', 'Mouf\\Database\\TDBM\\Controllers\\TdbmInstallController', true);
$moufManager->bindComponents('tdbminstall', 'template', 'moufInstallTemplate');
$moufManager->bindComponents('tdbminstall', 'content', 'block.content');

?>