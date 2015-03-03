<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

requireLibFile('inotify/SharingFolders.php');

class InotifyWatchFolders
{
	static function watchesMain($workspace)
	{

                $groupName='avid_'.$workspace;
                $etcGroup=EtcGroup::instance();
                $group=$etcGroup->findGroup($groupName);                

		$folders=array();
                foreach($group->members as $member)
		{
		        $folders=array_merge($folders,self::watchesMainUser($workspace,$member));
		}
		return $folders;
	}

        static function isLocatedInValidHomeFolderOfGroupMember($folder,$workspace)
        {

                $conf=new EtcWorkspaces('avid');
                $path=$conf->workspaces[$workspace];
                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;
                $groupName='avid_'.$workspace;
                $etcGroup=EtcGroup::instance();
                $group=$etcGroup->findGroup($groupName);                

                foreach($group->members as $member)
                {
                        $memberPath="$workspace/$member";
                        if(preg_match("|^{$memberPath}|",$folder))
                                return true;
                }
                return false;
        }

	static function generateTabWatchTree($folder)
	{
		$watchFolders=array();
              	$folder=preg_replace('/ /','\ ',$folder);
		$searchFilter="\\( ! -regex '.*/\..*' ".
			"-and ! -name 'resource.frk' ".
			"-and ! -regex '.*/Statistics' ".
			"-and ! -regex '.*/SearchData'  \\)";
		$folders=ShellCommand::query("find $folder -type d $searchFilter");
		$folders=explode("\n",$folders);
		foreach($folders as $folder)
		{
			$folder=trim($folder);
			if($folder!="")
				$watchFolders[]=$folder;
		}
		return $watchFolders;
	}

	static function watchesMainUser($workspace,$user)
	{
                $conf=new EtcWorkspaces('avid');
                $path=$conf->workspaces[$workspace];
                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;

		$watchFolders=array();
		$homeFolder="$pathAbs/$user";
		$watchFolders[]=$homeFolder;
		$avidFolders=SharingFolders::userAvidProjects($homeFolder);
		foreach($avidFolders as $avidFolder)
		{
			$watchFolders[]="$homeFolder/$avidFolder";
                        $sharedFolders=SharingFolders::userSubFolders("$homeFolder/$avidFolder/Shared");
                        foreach($sharedFolders as $sharedFolder)
			{
				$folder="$homeFolder/$avidFolder/Shared/$sharedFolder";
                                if(!is_link($folder))
				{
					$watchFolders=array_merge($watchFolders,self::generateTabWatchTree($folder));
				}
                                else
                                {
                                        $target=readlink($folder);
					if($target!==false && is_dir($target) && 
                                             self::isLocatedInValidHomeFolderOfGroupMember($target,$workspace))
                                        {
						$watchFolders=array_merge($watchFolders,
							self::generateTabWatchTree($target));
                                        }
                                }
			}
		}
	
		#watch 'Avid MediaFiles'
		if(file_exists("$homeFolder/Avid MediaFiles")) 
			$watchFolders[]="$homeFolder/Avid MediaFiles";

		#watch 'Avid MediaFiles/MXF'
		if(file_exists("$homeFolder/Avid MediaFiles/MXF")) 
			$watchFolders[]="$homeFolder/Avid MediaFiles/MXF";

		return $watchFolders;
	}

	static function watchesAVP($workspace)
	{
                $groupName='avid_'.$workspace;
                $etcGroup=EtcGroup::instance();
                $group=$etcGroup->findGroup($groupName);                

		$folders=array();
                foreach($group->members as $member)
		{
			$folders=array_merge($folders,self::watchesAVPUser($workspace,$member));
		}
		return $folders;
	}

	static function watchesAVPUser($workspace,$user)
	{
                $conf=new EtcWorkspaces('avid');
                $path=$conf->workspaces[$workspace];
                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;

		$watchFolders=array();
		$homeFolder="$pathAbs/$user";

		$watchFolders=array();
		$avidFolders=SharingFolders::userAvidProjects($homeFolder);
		foreach($avidFolders as $avidFolder)
                        if(!SharingFolders::folderHasValidAVPfile("$homeFolder/$avidFolder"))
				$watchFolders[]="$homeFolder/$avidFolder";
		return $watchFolders;
	}
}
        
