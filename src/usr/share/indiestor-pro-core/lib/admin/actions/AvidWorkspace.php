<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

require "Workspace.php";

class AvidWorkspace extends Workspace
{

        const WORKSPACETYPE='avid';

        static function createGroup($workspace)
        {
                $groupName='avid_'.$workspace;
        	ShellCommand::exec_fail_if_error("addgroup $groupName");
        }

        static function deleteGroup($workspace)
        {
                $groupName='avid_'.$workspace;
        	ShellCommand::exec_fail_if_error("delgroup $groupName");
        }

        static function addUser($commandAction)
        {
		$workspace=ProgramActions::$entityName;
                $userName=$commandAction->actionArg;
                $groupName='avid_'.$workspace;

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

                //regenerate config afp/smb files
                ActionEngine::generateAfpSmbConfig();
        }

        static function removeUser($commandAction)
        {
		$workspace=ProgramActions::$entityName;
                $userName=$commandAction->actionArg;
                $groupName='avid_'.$workspace;

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

                //remove the user
        	ShellCommand::exec_fail_if_error("deluser $userName $groupName");                

                //regenerate config afp/smb files
                ActionEngine::generateAfpSmbConfig();
        }

        static function json($commandAction)
        {
                //handled by show command
                return;
        }

        static function showMembers($commandAction)
        {
		$workspace=ProgramActions::$entityName;
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

//-----------------------

        static function reshare($commandAction)
        {
                echo "to be implemented\n";
        }


}

