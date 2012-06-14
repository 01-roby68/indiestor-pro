<?php
/*
        Indiestor program

	Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        By Alex Gardiner, alex.gardiner@canterbury.ac.uk
*/

class User extends EntityType
{
        static function add($commandAction)
        {
		$userName=ProgramActions::$entityName;
		//check if the username is the indiestor system user
		self::checkForIndiestorSysUserName($userName);	
		//if indiestor user exists already, abort
		self::checkForDuplicateIndiestorUser($userName);
		//if name contains invalid characters, abort
		self::checkValidCharactersInUserName($userName);
		//user exists already
		$etcPasswd=EtcPasswd::instance();
		$isExistingUser=$etcPasswd->exists($userName);
		//now add the user
        	if(!$isExistingUser)
		{
			if(ProgramActions::actionExists('set-home'))
			{
				$commandAction=ProgramActions::findByName('set-home');
				$homeFolder=$commandAction->actionArg;
				self::checkHomeFolderIsAbsolutePath($homeFolder);
				self::checkNewHomeNotOwnedAlready($userName,$homeFolder);
				$homeFolderOption="--home $homeFolder"; 
			}
			else
			{
				self::checkNewHomeNotOwnedAlready($userName,"/home/$userName");
				$homeFolderOption=''; 
			}
			//execute
			Shell::exec("adduser $homeFolderOption $userName");
		}
		//make sure indiestor user group exists
		self::ensureIndiestorGroupExists();
		//add user to indiestor user group
		self::shellExecAddUserToGroup(ActionEngine::indiestorUserGroup,$userName);
        }

	static function checkNewHomeNotOwnedAlready($userName,$homeFolder)
	{
		$etcPasswd=EtcPasswd::instance();
		$otherUser=$etcPasswd->findUserByHomeFolder($homeFolder);
		if($otherUser==null) return; //nobody owns this folder as home folder
		$otherUserName=$otherUser->name;
		ActionEngine::error("home folder $homeFolder already belongs".
			" to user $otherUserName",
			ERRNUM_HOME_FOLDER_ALREADY_BELONGS_TO_USER);
	}

	static function checkHomeFolderIsAbsolutePath($homeFolder)
	{
		if(substr($homeFolder,0,1)!='/')
		{
			ActionEngine::error("home folder must be absolute path".
					" (starting with a '/' character)",
					ERRNUM_HOME_FOLDER_MUST_BE_ABSOLUTE_PATH);
		}
	}

	static function checkValidCharactersInUserName($userName)
	{
		if(!ActionEngine::isValidCharactersInName($userName))
		{
			ActionEngine::error("'$userName' contains invalid characters",
						ERRNUM_USERNAME_INVALID_CHARACTERS);
		}
	}

	static function shellExecAddUserToGroup($groupName,$userName)
	{
		Shell::exec("usermod -a -G $groupName $userName");
	}

	static function ensureIndiestorGroupExists()
	{
                $etcGroup=EtcGroup::instance();
		if($etcGroup->indiestorGroup==null)
		{
	        	Shell::exec('addgroup '.ActionEngine::indiestorUserGroup);
			EtcGroup::reset();
	                $etcGroup=EtcGroup::instance();
		}
	}

	static function checkForIndiestorSysUserName($userName)
	{
		if(ActionEngine::isIndiestorSysUserName($userName))
		{
			ActionEngine::error("Cannot add '$userName' system user as indiestor user",
						ERRNUM_CANNOT_ADD_INDIESTOR_SYSUSER);
		}
	}

	static function checkForDuplicateIndiestorUser($userName)
	{
                $etcGroup=EtcGroup::instance();
		$indiestorGroup=$etcGroup->indiestorGroup;
                if($indiestorGroup==null) return;
		if($indiestorGroup->findMember($userName)!=null)
		{
			ActionEngine::error("indiestor user '$userName' exists already",
						ERRNUM_USER_EXISTS_ALREADY);
		}
	}

	static function checkForValidUserName($userName)
	{
                $etcGroup=EtcGroup::instance();
		$indiestorGroup=$etcGroup->indiestorGroup;
                if($indiestorGroup==null) return;
		if($indiestorGroup->findMember($userName)==null)
		{
			ActionEngine::error("indiestor user '$userName' does not exist",
						ERRNUM_USER_DOES_NOT_EXIST);
		}
	}

	static function checkForValidGroupName($groupName)
	{
                $etcGroup=EtcGroup::instance();
		$group=$etcGroup->findGroup($groupName);
		if($group==null)
		{
			ActionEngine::error("indiestor group '$groupName' does not exist",
						ERRNUM_GROUP_DOES_NOT_EXIST);
		}
	}

	static function	checkForDuplicateUserMembership($userName)
	{
                $etcGroup=EtcGroup::instance();
		$group=$etcGroup->findGroupForUserName($userName);
		if($group!=null)
		{
			$groupName=$group->name;
			ActionEngine::error("user '$userName' already member of group $groupName",
						ERRNUM_DUPLICATE_MEMBERSHIP);
		}
	}

        static function delete($commandAction)
        {
		$userName=ProgramActions::$entityName;
		//if user does not exists, abort
		self::checkForValidUserName($userName);	
		//now delete the user
		if(ProgramActions::actionExists('remove-home'))
		{
			$removeHomeOption='--remove-home';
		}
		else
		{
			$removeHomeOption='';
		}

		Shell::exec("deluser $removeHomeOption $userName");
        }

	static function removeHome($commandAction)
	{
		//if the delete action is present, the remove-home action has already been executed
		if(ProgramActions::actionExists('delete')) return;
		ActionEngine::error("-remove-home only possible in -delete action",
						ERRNUM_REMOVE_HOME_CONTENT_WITHOUT_DELETE);
	}

	static function addToGroup($commandAction)
	{
		$userName=ProgramActions::$entityName;
		//if user does not exists, abort
		self::checkForValidUserName($userName);	
		$groupName=$commandAction->actionArg;
		//if group does not exist, abort
		self::checkForValidGroupName($groupName);
		//if user already member of any group, abort
		self::checkForDuplicateUserMembership($userName);
		//add user to user group
		self::shellExecAddUserToGroup(ActionEngine::sysGroupName($groupName),$userName);
	}

        function removeGroupNameFromGroupNames($groupNames,$groupNameToRemove)
        {
                $result=array();
                foreach($groupNames as $groupName)
                {
                        if(trim($groupName)!=$groupNameToRemove)
                                $result[]=trim($groupName);
                }
                return $result;
        }

	function newGroupNamesForUserName($userName,$groupNameToRemove)
	{
                $groupNamesForUserName=sysquery_id_nG($userName);
                $newGroupNamesForUserName=self::removeGroupNameFromGroupnames(
						$groupNamesForUserName,
						$groupNameToRemove); 
                return implode(',',$newGroupNamesForUserName);
	}

	static function removeFromGroup($commandAction)
	{
		$userName=ProgramActions::$entityName;
		//if user does not exists, abort
		self::checkForValidUserName($userName);	
		//if user is not member of a group, abort
                $etcGroup=EtcGroup::instance();
		$group=$etcGroup->findGroupForUserName($userName);
		if($group==null)
		{
			ActionEngine::error("user '$userName' is not member of any group",
						ERRNUM_USER_NOT_MEMBER_OF_ANY_GROUP);
		}
		//we calculate the new collection of groups to which the user belongs
		//by removing his existing group from the list
		$groupNameToRemove=ActionEngine::sysGroupName($group->name);
		$groupNames=self::newGroupNamesForUserName($userName,$groupNameToRemove);
		Shell::exec("usermod $userName -G $groupNames");
	}

	static function setPasswd($commandAction)
	{
		$userName=ProgramActions::$entityName;
		//if user does not exists, abort
		self::checkForValidUserName($userName);	
		$passwd=$commandAction->actionArg;
		$cryptedPwd=crypt($passwd);
		Shell::exec("usermod --password '$cryptedPwd' $userName");
	}

	static function lock($commandAction)
	{
		$userName=ProgramActions::$entityName;
		//if user does not exists, abort
		self::checkForValidUserName($userName);	
		Shell::exec("usermod --lock $userName");
	}

	static function expel($commandAction)
	{
		$userName=ProgramActions::$entityName;
		//if user does not exists, abort
		self::checkForValidUserName($userName);	
		Shell::exec("pkill -KILL -u $userName");
	}

	static function removeFromIndiestor($commandAction)
	{
		$userName=ProgramActions::$entityName;
		//if user does not exists, abort
		self::checkForValidUserName($userName);	
		$groupNames=self::newGroupNamesForUserName($userName,ActionEngine::indiestorUserGroup);
		Shell::exec("usermod $userName -G $groupNames");
	}

	static function setHome($commandAction)
	{
		$userName=ProgramActions::$entityName;
		//if user does not exists, abort
		self::checkForValidUserName($userName);	
		//if the add action is present, the set-home action has already been executed
		if(ProgramActions::actionExists('add')) return;
		$homeFolder=$commandAction->actionArg;
		self::checkHomeFolderIsAbsolutePath($homeFolder);
		self::checkNewHomeNotOwnedAlready($userName,$homeFolder);
		if(ProgramActions::actionExists('move-home-content'))
		{
			if(!file_exists($homeFolder))
			{
				$etcPasswd=EtcPasswd::instance();
				$user=$etcPasswd->findUserByName($userName);
				$oldHomeFolder=$user->homeFolder;
				$userShell=$user->shell;
				//expel user
				Shell::exec("pkill -KILL -u $userName");
				//prevent login
				Shell::exec("chsh --shell /bin/false $userName");
				//move the folder
				Shell::exec("mv $oldHomeFolder $homeFolder");
				//reallow login
				Shell::exec("chsh --shell $userShell $userName");
			}
			else
			{
				ActionEngine::error("cannot move home content to folder $homeFolder;".
					"the folder exists already",
					ERRNUM_CANNOT_MOVE_HOME_CONTENT_TO_EXISTING_FOLDER);
			}
		}
		else
		{
			if(!file_exists($homeFolder))
			{
				Shell::exec("mkdir $homeFolder");
				Shell::exec("cp -aR /etc/skel/* $homeFolder");
				Shell::exec("chown -R $userName.$userName $homeFolder");
			}
			else
			{
				if(!is_dir($homeFolder))
				{
					ActionEngine::error("cannot set home content to $homeFolder;".
					"it is not a folder",
					ERRNUM_CANNOT_MOVE_HOME_TO_NON_FOLDER);
				}
				else
				{
					Shell::exec("chown -R $userName.$userName $homeFolder");
				}
			}
		}
		Shell::exec("usermod --home $homeFolder $userName");
	}

	static function moveHomeContent($commandAction)
	{
		//if the add action is present, the set-home action has already been executed
		if(ProgramActions::actionExists('set-home')) return;
		ActionEngine::error("-move-home-content only possible in -set-home action",
						ERRNUM_MOVE_HOME_CONTENT_WITHOUT_SET_HOME);
	}
}
