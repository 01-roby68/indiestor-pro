#!/usr/bin/php
<?php
/*
        Indiestor Pro program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

//--------------------------
//Check deployment location
//--------------------------
global $appRoot;
global $appPrefix;
$appRoot=dirname(dirname(dirname(dirname(dirname(__FILE__)))));
if($appRoot=="/") $appPrefix=""; else $appPrefix=$appRoot;

$LIB="$appPrefix/usr/share/indiestor-pro-core/lib";
$BIN="$appPrefix/usr/bin";

if (dirname(__FILE__)=='/usr/share/indiestor-pro-core/prg')
{
	$INUSER='indienotify';
}
else
{
	$INUSER='root';
}

function indiestor_INUSER()
{
	global $INUSER;
	return $INUSER;
}

function indiestor_BIN()
{
	global $BIN;
	return $BIN;
}

function indiestor_LIB()
{
	global $LIB;
	return $LIB;
}

function requireLibFile($path)
{
	require_once(indiestor_LIB().'/'.$path);
}
//--------------------------

requireLibFile("admin/require_once_folder.php");
requireLibFile("admin/args/ProgramActions.php");
requireLibFile("admin/args/ProgramOptions.php");
requireLibFile("admin/ArgEngine.php");
requireLibFile("admin/ActionEngine.php");
requireLibFile("admin/ShellCommand.php");
requireLibFile("admin/DefinitionFile.php");
requireLibFile("admin/NoticeDefinitions.php");
requireLibFile("inotify/SharingStructureAvid.php");
requireLibFile("inotify/SharingStructureMXF.php");

//check that the user is root
$processUser = posix_getpwuid(posix_geteuid());
$processUserName=$processUser['name'];
if($processUserName!='root')
{
	NoticeDefinitions::instance()->error('EXEC_ROOT_ONLY');
}

//check that shell_exec is allowed
if(false !== strpos(ini_get('disable_functions'), 'shell_exec'))
{
	NoticeDefinitions::instance()->error('SYS_ERR_SHELL_EXEC_DISABLED');
}

$argEngine=new ArgEngine($argv);
$argEngine->process();

ProgramOptions::extractFromProgramActions();

ActionEngine::execute();

