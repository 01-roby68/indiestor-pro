<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

require_once("Workspace.php");

class AvidWorkspace extends Workspace
{

        const WORKSPACETYPE='avid';
        const OTHER_WORKSPACETYPE='generic';

        static function createGroup($workspace)
        {
                $groupName='avid_'.$workspace;
        	ShellCommand::exec("delgroup --quiet $groupName");
        	ShellCommand::exec_fail_if_error("addgroup $groupName");
        }

        static function deleteGroup($workspace)
        {
                $groupName='avid_'.$workspace;
        	ShellCommand::exec("delgroup $groupName");
        }

        static function addUser($commandAction)
        {
		$workspace=ProgramActions::$entityName;
                $userName=$commandAction->actionArg;
                self::addUserWithParms($workspace,$userName);
        }

        static function addUserWithParms($workspace,$userName,$reshare=true)
        {
                $groupName='avid_'.$workspace;

                //check if user exists
		$etcPasswd=EtcPasswd::instance();
		$user=$etcPasswd->findUserByName($userName);
		if($user===null) {
                        ActionEngine::error('ERR_USER_DOES_NOT_EXIST');
                        return;
                }

		//the workspace must exist
                $conf=new EtcWorkspaces('avid');
                if(!array_key_exists($workspace,$conf->workspaces)) {
                        ActionEngine::error('ERR_WORKSPACE_DOES_NOT_EXISTS');
                        return;
		}
                
                //the group must exist
                $etcGroup=EtcGroup::instance();
                $group=$etcGroup->findGroup($groupName);                
                if($group===null) {
                        ActionEngine::error('ERR_AVID_GROUP_MUST_EXIST');
                        return;
                }

                //user must be part indiestor group
		$indiestorGroup=$etcGroup->indiestorGroup;
                if($indiestorGroup===null) {
		        ActionEngine::error('ERR_INDIESTOR_GROUP_DOES_NOT_EXIST');
                        return;
                }

		if($indiestorGroup->findMember($userName)===null) {
			ActionEngine::error('ERR_USER_NOT_INDIESTOR_USER');
                        return;
                }                

                //check if he not already member of this group
		if($group->findMember($userName)!==null) {
			ActionEngine::error('ERR_USER_ALREADY_MEMBER');
                        return;
                }

                //add the user
        	ShellCommand::exec_fail_if_error("adduser $userName $groupName");                


                //create user folder
                $path=$conf->workspaces[$workspace];

                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;
                
                //set owner and group
                if(!is_dir("$pathAbs/$userName")) {
                        mkdir("$pathAbs/$userName",0755);
                        chown("$pathAbs/$userName",$userName);
                        chgrp("$pathAbs/$userName",$groupName);
                }

                // regen shares and refresh filers
                ActionEngine::forkRefreshChildProgram();

                //startwatching again and reshare indirectly
                //by touching the workspace spool file
                InotifyWait::startWatching($workspace);
                ShellCommand::exec("indiestor-pro-touch $workspace"); 
        }

        static function removeUser($commandAction)
        {
		$workspace=ProgramActions::$entityName;
                $userName=$commandAction->actionArg;
                $groupName='avid_'.$workspace;

		//the workspace must exist
                $conf=new EtcWorkspaces('avid');
                if(!array_key_exists($workspace,$conf->workspaces)) {
                        ActionEngine::error('ERR_WORKSPACE_DOES_NOT_EXISTS');
                        return;
		}

                //check if user exists
		$etcPasswd=EtcPasswd::instance();
		$user=$etcPasswd->findUserByName($userName);
		if($user===null) {
                        ActionEngine::error('ERR_USER_DOES_NOT_EXIST');
                        return;
                }

                //the group must exist
                $etcGroup=EtcGroup::instance();
                $group=$etcGroup->findGroup($groupName);                
                if($group===null) {
                        ActionEngine::error('ERR_AVID_GROUP_MUST_EXIST');
                        return;
                }

                //check if he not already member of this group
		if($group->findMember($userName)===null) {
			ActionEngine::error('ERR_USER_NOT_MEMBER');
                        return;
                }

                $path=$conf->workspaces[$workspace];

                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;

                //remove the user's folder
                $attempt=0;
                clearstatcache(true);
                while(is_dir("$pathAbs/$userName") && $attempt<10 ) { 
                        ShellCommand::exec_fail_if_error("rm -rf '$pathAbs/$userName' ; sync");
                        clearstatcache(true);
                        if(is_dir("$pathAbs/$userName")) {
                                if($attempt==0)
                                        echo "Please wait for complete removal of folder $pathAbs/$userName ...\n";
                                sleep(0.5);
                        }
                        $attempt++;
                }

                //remove the user
        	ShellCommand::exec("deluser $userName $groupName");                

                // regen shares and refresh filers
                ActionEngine::forkRefreshChildProgram();

                //startwatching again and reshare indirectly
                //by touching the workspace spool file
                InotifyWait::startWatching($workspace);
                ShellCommand::exec("indiestor-pro-touch $workspace"); 
        }

        static function json($commandAction)
        {
                //handled by show command
                return;
        }

        static function showMembers($commandAction)
        {
		$workspace=ProgramActions::$entityName;
                $conf=new EtcWorkspaces('avid');

                if(!array_key_exists($workspace,$conf->workspaces))
                {
                        ActionEngine::error('ERR_AVID_WORKSPACE_MUST_EXIST');
                        return;
                }

                $groupName='avid_'.$workspace;

                //the group must exist
                $etcGroup=EtcGroup::instance();
                $group=$etcGroup->findGroup($groupName);                
                if($group===null) {
                        ActionEngine::error('ERR_AVID_GROUP_MUST_EXIST');
                        return;
                }

                //members
                $members=$group->members;
                
                //show members
                if(ProgramActions::actionExists('json')) {
                        echo json_encode_legacy($members)."\n";
                } else {
                        if(count($members)===0) echo "no members\n";
                        else echo join("\n",$members)."\n";
                }
        }

	static function showWatches($commandAction)
	{
		$workspace=ProgramActions::$entityName;
                $conf=new EtcWorkspaces('avid');

                if(!isset($conf->workspaces[$workspace]))
                {
                        ActionEngine::error('ERR_AVID_WORKSPACE_MUST_EXIST');
                        return;
                }


                //must have at least 2 members
                $groupName='avid_'.$workspace;
                $etcGroup=EtcGroup::instance();
                $group=$etcGroup->findGroup($groupName);                

		if(count($group->members)<2) return;

		$watchType=$commandAction->actionArg;

		switch($watchType)
		{
			case 'main': self::showWatchesMain($workspace); break;
			case 'avp': self::showWatchesAVP($workspace); break;
			default: ActionEngine::error('ERR_WATCH_TYPE_DOES_NOT_EXISTS');
		}
	}

	static function showWatchesMain($workspace)
	{
		foreach(InotifyWatchFolders::watchesMain($workspace) as $folder)
			echo "$folder\n";
	}

	static function showWatchesAVP($workspace)
	{
		foreach(InotifyWatchFolders::watchesAVP($workspace) as $folder)
			echo "$folder\n";
	}

	static function showWatchProcesses($commandAction)
	{
		$workspace=ProgramActions::$entityName;
                $conf=new EtcWorkspaces('avid');
                if(!array_key_exists($workspace,$conf->workspaces))
                {
                        ActionEngine::error('ERR_AVID_WORKSPACE_MUST_EXIST');
                        return;
                }
		$pids=InotifyWait::watchProcesses($workspace);
		foreach($pids as $pid)
			echo "$pid\n";

	}

        static function reshare($commandAction)
        {
		$workspace=ProgramActions::$entityName;
                self::reshareWithParms($workspace);
        }

        static function reshareWithParms($workspace)
        {
                $conf=new EtcWorkspaces('avid');
                if(!array_key_exists($workspace,$conf->workspaces))
                {
                        ActionEngine::error('ERR_AVID_WORKSPACE_MUST_EXIST');
                        return;
                }
                $groupName='avid_'.$workspace;

                //the group must exist
                $etcGroup=EtcGroup::instance();
                $group=$etcGroup->findGroup($groupName);                
                if($group===null) {
                        ActionEngine::error('ERR_AVID_GROUP_MUST_EXIST');
                        return;
                }

                //members
                $members=$group->members;

	        SharingStructureAvid::reshare($workspace,$members);
	        SharingStructureMXF::reshare($workspace,$members,true);
        }
}

