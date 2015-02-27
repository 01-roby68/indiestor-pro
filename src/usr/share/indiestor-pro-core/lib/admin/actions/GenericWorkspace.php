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

class GenericWorkspace extends Workspace
{

        const WORKSPACETYPE='generic';

        static function createGroup($workspace)
        {
                $groupName1='generic_rw_'.$workspace;
                $groupName2='generic_ro_'.$workspace;
        	ShellCommand::exec_fail_if_error("addgroup $groupName1");
        	ShellCommand::exec_fail_if_error("addgroup $groupName2");
        }

        static function deleteGroup($workspace)
        {
                $groupName1='generic_rw_'.$workspace;
                $groupName2='generic_ro_'.$workspace;
        	ShellCommand::exec_fail_if_error("delgroup $groupName1");
        	ShellCommand::exec_fail_if_error("delgroup $groupName2");
        }

        static function addWriteUser($commandAction)
        {
                $userName=$commandAction->actionArg;
		$workspace=ProgramActions::$entityName;
                $groupName='generic_rw_'.$workspace;
                $oppGroupName='generic_ro_'.$workspace;
                self::addUser($userName,$groupName,$oppGroupName);

                //regenerate config afp/smb files
                ActionEngine::generateAfpSmbConfig();
        }

        static function addReadOnlyUser($commandAction)
        {
                $userName=$commandAction->actionArg;
		$workspace=ProgramActions::$entityName;
                $groupName='generic_ro_'.$workspace;
                $oppGroupName='generic_rw_'.$workspace;
                self::addUser($userName,$groupName,$oppGroupName);

                //regenerate config afp/smb files
                ActionEngine::generateAfpSmbConfig();
        }


        static function addUser($userName,$groupName,$oppGroupName)
        {
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
                        ActionEngine::error('ERR_GENERIC_GROUP_MUST_EXIST');
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

                //check if he is not already member of this group
		if($group->findMember($userName)!==null) {
			ActionEngine::error('ERR_USER_ALREADY_MEMBER');
                        return;
                }

                //check if he is not already member of the opposite group
                $oppGroup=$etcGroup->findGroup($oppGroupName);                
		if($oppGroup->findMember($userName)!==null) {
			ActionEngine::error('ERR_USER_ALREADY_MEMBER_OF_ALTERNATIVE_GROUP');
                        return;
                }


                //add the user
        	ShellCommand::exec_fail_if_error("adduser $userName $groupName");                
        }

        static function removeWriteUser($commandAction)
        {
                $userName=$commandAction->actionArg;
		$workspace=ProgramActions::$entityName;
                $groupName='generic_rw_'.$workspace;
                self::removeUser($userName,$groupName);

                //regenerate config afp/smb files
                ActionEngine::generateAfpSmbConfig();
        }

        static function removeReadOnlyUser($commandAction)
        {
                $userName=$commandAction->actionArg;
		$workspace=ProgramActions::$entityName;
                $groupName='generic_ro_'.$workspace;
                self::removeUser($userName,$groupName);

                //regenerate config afp/smb files
                ActionEngine::generateAfpSmbConfig();
        }

        static function removeUser($userName,$groupName)
        {

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
                        ActionEngine::error('ERR_GENERIC_GROUP_MUST_EXIST');
                        return;
                }

                //check if he not already member of this group
		if($group->findMember($userName)===null) {
			ActionEngine::error('ERR_USER_NOT_MEMBER');
                        return;
                }

                //remove the user
        	ShellCommand::exec_fail_if_error("deluser $userName $groupName");                
        }

        static function json($commandAction)
        {
                //handled by show command
                return;
        }

        static function showMembers($commandAction)
        {
		$workspace=ProgramActions::$entityName;
                $rwGroupName='generic_rw_'.$workspace;
                $roGroupName='generic_ro_'.$workspace;

                //the rw group must exist
                $etcGroup=EtcGroup::instance();
                $rwGroup=$etcGroup->findGroup($rwGroupName);                
                if($rwGroup===null) {
                        ActionEngine::error('ERR_GENERIC_GROUP_MUST_EXIST');
                        return;
                }

                //the ro group must exist
                $etcGroup=EtcGroup::instance();
                $roGroup=$etcGroup->findGroup($roGroupName);                
                if($roGroup===null) {
                        ActionEngine::error('ERR_GENERIC_GROUP_MUST_EXIST');
                        return;
                }

                //members
                $rwMembers=$rwGroup->members;
                $roMembers=$roGroup->members;
                
                //show members
                if(ProgramActions::actionExists('json')) {
                        $jsonStruct=['rw'=>$rwMembers,'ro'=>$roMembers];
                        echo json_encode_legacy($jsonStruct)."\n";
                } else {
                        echo "--READ-WRITE--\n";
                        if(count($rwMembers)===0) echo "no members\n";
                        else echo join("\n",$rwMembers)."\n";
                        echo "--READ-ONLY--\n";
                        if(count($roMembers)===0) echo "no members\n";
                        echo join("\n",$roMembers)."\n";
                }

        }

//-----------------------

        static function reshare($commandAction)
        {
                echo "to be implemented\n";
        }

}

