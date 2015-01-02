<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class UserReportRecord
{
	var $userName=null;
	var $homeFolder=null;
	var $device=null;
	var $locked=null;
	var $groupName=null;
	var $AvailSpace=null;
	var $UsedSpace=null;

	function __construct($userName)
	{
		$this->userName=$userName;
		$etcPasswd=EtcPasswd::instance();
		$etcUser=$etcPasswd->findUserByName($userName);
		$this->homeFolder=$etcUser->homeFolder;
		//find device for user home folder
		$this->device=sysquery_df_device_for_folder($this->homeFolder);
		$this->locked=sysquery_passwd_S_locked($userName);
		$etcGroup=EtcGroup::instance();
		$group=$etcGroup->findGroupForUserName($userName);
		if($group==null) $this->groupName=null;
		else $this->groupName=$group->name;

		//getting space utilisation stats
		$this->AvailSpace=sysquery_df_home_fs_total($userName,$this->homeFolder);
		$this->UsedSpace=sysquery_df_home_size($userName,$this->homeFolder);

	}
}

