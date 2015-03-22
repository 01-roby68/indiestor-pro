<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

function sysquery_which($executable)
{
	$result=ShellCommand::query("which '$executable'",true);
	if($result->returnCode==0) return true;
	else return false;
}

function ps_is_logged_in($userName)
{
	$result=ShellCommand::query("ps -e -o user | sort | uniq");
	$lines=explode("\n",$result);
	foreach($lines as $line)
	{
		$line=trim($line);
		if($line==$userName) return true;
	}

	$result=ShellCommand::query("ps -e -o ruser | sort | uniq");
	$lines=explode("\n",$result);
	foreach($lines as $line)
	{
		$line=trim($line);
		if($line==$userName) return true;
	}
	return false;	
}

$smbstatus_cached_users=null;

function sysquery_smbstatus_is_logged_in($userName)
{
	$users=sysquery_smbstatus_processes();
	return array_key_exists($userName,$users);
}

function sysquery_smbstatus_processes()
{
	global $smbstatus_cached_users;

	//check if we can serve from cache
	if($smbstatus_cached_users!=null)
		return $smbstatus_cached_users;

	$smbstatus_cached_users=array();

	if(!sysquery_which('smbstatus')) return $smbstatus_cached_users;

	$result=ShellCommand::query("smbstatus --processes | tail -n +5 | awk '{ print $2}' | sort | uniq",true);
	if($result->returnCode!=0) return $smbstatus_cached_users;

	$lines=explode("\n",$result->stdout);

	foreach($lines as $line)
	{
		$line=trim($line);
		if($line!='')
		{
			$smbstatus_cached_users[$line]=$line;
		}
	}

	return $smbstatus_cached_users;
}

function sysquery_pdbedit_user($userName)
{
	$sambaUsers=sysquery_pdbedit_list($userName);
	if(array_key_exists($userName,$sambaUsers)) return $sambaUsers[$userName];
	else return null;
}


function sysquery_pdbedit_list($userName=null)
{
	$users=array();
	if(!sysquery_which('pdbedit')) return $users;
	if($userName!=null) $userClause="--user $userName"; else $userClause=''; 
	$result=ShellCommand::query("pdbedit --list --smbpasswd-style $userClause",true);
	if($result->returnCode!=0) return $users;
	
	$lines=explode("\n",$result->stdout);
	foreach($lines as $line)
	{
		if(trim($line)!='')
		{
			$fields=explode(':',$line);
			if(count($fields)>=5)
			{
				$user=array();
				$name=$fields[0];
				$flags=$fields[4];
				$user['name']=$name;
				$user['flags']=$flags;
				$user['sambaFlagArray']=sambaFlagArray($flags);
				$users[$name]=$user;
			}
		}
	}
	return $users;
}

function sambaFlagArray($flags)
{
	$individualFlags=array();
	$i=0;
	for($i=0;$i<strlen($flags);$i++)
	{
		$letter=$flags[$i];
		if($letter!='[' & $letter!=']') $individualFlags[$letter]=$letter;
	}
	return $individualFlags;
}

class User extends EntityType
{

        static function add($commandAction)
        {
		$userName=ProgramActions::$entityName;
		$etcPasswd=EtcPasswd::instance();
		$isExistingUser=$etcPasswd->exists($userName);

                //check for valid characters
		if(!ActionEngine::isValidCharactersInName($userName)) {
			ActionEngine::error('ERR_USER_INVALID_CHARACTERS');
                        return;
                }

                //make sure indiestor group exists

                $etcGroup=EtcGroup::instance();
		$indiestorGroup=$etcGroup->indiestorGroup;
                if($indiestorGroup==null) {
		        ActionEngine::error('ERR_INDIESTOR_GROUP_DOES_NOT_EXIST');
                        return;
                }

                //check for duplicate users

		if($indiestorGroup->findMember($userName)!=null) {
			ActionEngine::error('ERR_USER_EXISTS_ALREADY');
                        return;
                }

                //check if a password has been supplied

		if(!ProgramActions::actionExists('set-passwd'))
			ActionEngine::warning('WARN_USER_NO_PASSWORD');

		//now add the user
        	if(!$isExistingUser)
		{
                	ShellCommand::exec_fail_if_error(
		                "adduser --system --group --shell /bin/false --disabled-password --gecos '' --no-create-home $userName");
			EtcPasswd::reset();
		}
		//add user to indiestor user group
                ShellCommand::exec_fail_if_error("usermod -a -G ".ActionEngine::indiestorUserGroup." $userName");
		EtcPasswd::reset();
		EtcGroup::reset();

		//add samba user
	        if(sysquery_which('smbpasswd'))
	        {
		        if(sysquery_pdbedit_user($userName)==null)	
			        ShellCommand::exec_fail_if_error("(echo '';echo '') | smbpasswd -s -a $userName");
	        }
        }

        static function delete($commandAction)
        {
		$userName=ProgramActions::$entityName;
       
                //check if logged in
		if(ps_is_logged_in($userName) || sysquery_smbstatus_is_logged_in($userName)) {
			ActionEngine::error('ERR_USER_LOGGED_IN');
                        return;
                }

                //remove user's avid workspace folders
                $etcGroup=EtcGroup::instance();                
                $confAvid=new EtcWorkspaces('avid');
                foreach($confAvid->workspaces as $workspace=>$path) {
                        if(substr($path,0,1)!=='/')
                                $pathAbs="/$path";
                        else $pathAbs=$path;
                        $groupName='avid_'.$workspace;
                        $group=$etcGroup->findGroup($groupName);                
                        if($group===null) continue;
                        if($group->isMember($userName)) {
                                ShellCommand::exec("rm -rf $pathAbs/$userName");
                                $members=$group->members;
	                        SharingStructureAvid::reshare($workspace,$members);
	                        SharingStructureMXF::reshare($workspace,$members,true);
                        }                        
                }

                //remove system user
        	ShellCommand::exec_fail_if_error("deluser $userName");
		EtcPasswd::reset();

                //remove samba user
	        if(sysquery_which('pdbedit'))
	        {
		        if(sysquery_pdbedit_user($userName)!=null)
			        ShellCommand::exec_fail_if_error("pdbedit --delete --user $userName");
	        }
        }

	static function setPasswd($commandAction)
	{
		$userName=ProgramActions::$entityName;
		$passwd=$commandAction->actionArg;

                //check if user exists
		$etcPasswd=EtcPasswd::instance();
		if(!$etcPasswd->exists($userName)) {
                        ActionEngine::error('ERR_USER_DOES_NOT_EXIST');
                        return;
                }

                //set password as system level
	        $cryptedPwd=crypt($passwd);
	        ShellCommand::exec_fail_if_error("usermod --password '$cryptedPwd' $userName");
		EtcPasswd::reset();

                //set password at samba level
	        if(sysquery_which('smbpasswd'))
	        {
		        if(sysquery_pdbedit_user($userName)!=null)	
		        {
			        ShellCommand::exec_fail_if_error("(echo '$passwd';echo '$passwd') | smbpasswd -s $userName ");
			        ShellCommand::exec_fail_if_error("smbpasswd -e $userName ");
		        }
	        }
	}

	static function pkill($commandAction)
	{
		$userName=ProgramActions::$entityName;
		$etcPasswd=EtcPasswd::instance();
		if(!$etcPasswd->exists($userName)) {
                        ActionEngine::error('ERR_USER_DOES_NOT_EXIST');
                        return;
                }
                
		$userName=ProgramActions::$entityName;
        	ShellCommand::exec("pkill -15 -u $userName");
	}



}

