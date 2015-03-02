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
if (dirname(__FILE__)=='/usr/share/indiestor-pro-core/prg')
{
	$BIN='/usr/bin';
	$LIB='/usr/share/indiestor-pro-core/lib';
	$INUSER='indienotify';
}
else
{
	$BIN=dirname(__FILE__);
	$LIB=dirname(__FILE__).'/lib';
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

function requireLibFile($path)
{
	global $LIB;
	require_once("$LIB/$path");
}

//--------------------------

requireLibFile("admin/etcfiles/EtcPasswd.php");
requireLibFile("admin/etcfiles/EtcGroup.php");
requireLibFile("admin/sysqueries/df.php");
requireLibFile("admin/action-engine/InotifyWait.php");
requireLibFile("admin/renameUsingShell.php");
requireLibFile("inotify/syslog.php");
requireLibFile("inotify/SharingStructureAvid.php");
requireLibFile("inotify/SharingStructureMXF.php");
requireLibFile("inotify/SharingOperations.php");
requireLibFile("inotify/SharingFolders.php");
requireLibFile("inotify/chmodRecursive.php");

//syslog error handling
function customError($errno,$errmsg,$errfile,$errline)
{
        if($errno==0) return true; //ignore errors prepended with @
	$msg="err:$errno,$errmsg in file $errfile, line $errline";
	syslog_notice($msg);
	echo $msg."\n\n";
	debug_print_backtrace();
	ob_start();
	var_dump($someVar);
	$trace = ob_get_clean();
	syslog_notice($trace);
	die();
}
set_error_handler("customError");

//catch fatal errors
function handleShutdown()
{
	$error = error_get_last();
	if($error !== NULL)
		customError('FATAL-SHUTDOWN',$error['message'],$error['file'],$error['line']);
}
register_shutdown_function('handleShutdown');

syslog_notice_start_running();

while(true)
{
        clearstatcache();

	$avidWorkspaceFiles=glob('/var/spool/indiestor-pro/*');
	//pick the first group available or terminate

	if($avidWorkspaceFiles===FALSE)
	{
		syslog_notice("error reading files in /var/spool/indiestor-pro");
		break;		
	}
	if(count($avidWorkspaceFiles)==0) break;
	$avidWorkspaceFile=$avidWorkspaceFiles[0];
	$workspace=basename($avidWorkspaceFile);        

	$ulresult=unlink($avidWorkspaceFile);
        if($ulresult===FALSE)
                syslog_notice("error unlinking group file $avidWorkspaceFile");

	syslog_notice("processing workspace: $workspace");

	//find group
        $groupName='avid_'.$workspace;
	$group=EtcGroup::instance()->findGroup($workspace);
	if($group==null)
	{
		syslog_notice("cannot find group for workspace '$workspace'; skipping");
		continue;
	}

	//retrieve all group members
	$members=$group->members;

	//reshare
	SharingStructureAvid::reshare($workspace,$members);
	SharingStructureMXF::reshare($members);

	//restart watching
	InotifyWait::startWatching($workspace);
}

//notify end run
syslog_notice_end_running();

