<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class UserReportRecords
{
	var $records=null;

	function __construct($userNames)
	{
		$this->records=array();
		if($userNames==null) return;
                foreach($userNames as $userName)
                {
			$userRecord=new UserReportRecord($userName);
			$this->records[]=$userRecord;
                }
	}

        function findFormattedUserRecords()
        {
                $formattedUserRecords=array();
		$sambaUsers=sysquery_pdbedit_list();
		$sambaConnectedUsers=sysquery_smbstatus_processes();
		foreach($this->records as $userReportRecord)
		{
                        $formattedUserRecord=array();

			//locked
			if($userReportRecord->locked) $formattedUserRecord['locked']='yes';
			else $formattedUserRecord['locked']='no';

			//groupName
			if($userReportRecord->groupName==null) $formattedUserRecord['groupName']='(none)';
			else $formattedUserRecord['groupName']=$userReportRecord->groupName;

			//Total Space Available to home directory
			$formattedUserRecord['AvailSpace']=$userReportRecord->AvailSpace;

			//Space Used by home directory
			$formattedUserRecord['UsedSpace']=$userReportRecord->UsedSpace;

			if(array_key_exists($userReportRecord->userName,$sambaUsers))
			{                                
				$sambaUser=$sambaUsers[$userReportRecord->userName];
				$formattedUserRecord['samba']='yes';
				$formattedUserRecord['sambaFlags']=implode('',$sambaUser['sambaFlagArray']);
			}
			else
			{
				$formattedUserRecord['samba']='no';
				$formattedUserRecord['sambaFlags']='-';
			}

			if(array_key_exists($userReportRecord->userName,$sambaConnectedUsers))
				$formattedUserRecord['sambaConnected']='yes';
			else $formattedUserRecord['sambaConnected']='no';

			$formattedUserRecord['userName']=$userReportRecord->userName;
			$formattedUserRecord['homeFolder']=$userReportRecord->homeFolder;

                        $formattedUserRecords[]=$formattedUserRecord;
		}

                return $formattedUserRecords;
        }

	function outputCLI()
	{
                $formattedUserRecords=self::findformattedUserRecords();

		if(count($formattedUserRecords)==0) 
		{
			echo "no users\n";
			return;
		}

        $format1="%-15s %-20s %-8s %-15s %-10s %-10s %-6s %-6s %-8s\n";
        $format2="%-15s %-20s %-8s %-15s %-10s %-10s %-6s %-6s %-8s\n";
		printf($format1,'user','home','locked','group','Avail.','Used','samba','flags','SMB conn.');
		foreach($formattedUserRecords as $formattedUserRecord)
		{
			printf($format2,
				$formattedUserRecord['userName'],
				$formattedUserRecord['homeFolder'],
				$formattedUserRecord['locked'],
				$formattedUserRecord['groupName'],
				$formattedUserRecord['AvailSpace'],
				$formattedUserRecord['UsedSpace'],
				$formattedUserRecord['samba'],
				$formattedUserRecord['sambaFlags'],
				$formattedUserRecord['sambaConnected']);  
		}
	}

	function outputJSON()
	{
                echo json_encode_legacy(self::findformattedUserRecords())."\n";
        }
}

