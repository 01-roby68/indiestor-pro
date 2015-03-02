<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

requireLibFile('inotify/syslog.php');
requireLibFile('inotify/SharingOperations.php');
requireLibFile('inotify/SharingFolders.php');

define('AMF_SUBFOLDER','Avid MediaFiles');
define('MXF_SUBFOLDER',AMF_SUBFOLDER.'/'.'MXF');

class SharingStructureMXF
{

        static function ensureAMFPermsOwnership($pathAbs,$users)
        {
		foreach($users as $user)
		{
		        $amfSubFolder="$pathAbs/$user/".AMF_SUBFOLDER;
                        if(is_dir($amfSubFolder))
                                chmodRecursive($amfSubFolder, 0644,0755,$user,$user);
		}
        }

	static function reshare($workspace,$users,$ensureAMFPermsOwnership=false)
	{

                $conf=new EtcWorkspaces('avid');
                $path=$conf->workspaces[$workspace];
      
                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;

                $group='avid_'.$workspace;

		if($users==null) $users=array();
                if($ensureAMFPermsOwnership)
                        self::ensureAMFPermsOwnership($pathAbs,$users);
		self::reshareAvid($pathAbs,$users);
		self::purgeAvid($pathAbs,$users);
	}

	static function reshareAvid($pathAbs,$users)
	{
		foreach($users as $user)
		{
			self::reshareAvidFromUser($pathAbs,$user,$users);
		}
	}

	static function reshareAvidFromUser($pathAbs,$user,$users)
	{
		$amfFolder="$pathAbs/$user/".AMF_SUBFOLDER;
		$mxfFolder="$pathAbs/$user/".MXF_SUBFOLDER;
		if(!file_exists($mxfFolder)) return;

                //fix ownership for AMF subfolder
                if(file_exists($amfFolder)) {
                        SharingOperations::fixUserObjectOwnership($user,$amfFolder);
                }

		$folders=self::mxfSubFolders($mxfFolder);
		foreach($folders as $folder)
		{
			$target="$mxfFolder/$folder";
			foreach($users as $sharingUser)
			{
				if($user != $sharingUser)
					self::reshareAvidMXFToUser($pathAbs,$sharingUser,$target,$folder,$user);
			}
		}
	}

	static function reshareAvidMXFToUser($pathAbs,$sharingUser,$target,$entry,$fromUserName)
	{
		$amfSubFolder="$pathAbs/$sharingUser/".AMF_SUBFOLDER;
		$mxfSubFolder="$pathAbs/$sharingUser/".MXF_SUBFOLDER;
		if(!is_dir($mxfSubFolder))
		{	
			$result=mkdir($mxfSubFolder,0755,true);
			if(!$result) syslog_notice("Cannot create folder '$mxfSubFolder'");
			syslog_notice("chown($mxfSubFolder,$sharingUser)");
			chown($mxfSubFolder,$sharingUser);
			if(!$result) syslog_notice("Cannot chown folder '$mxfSubFolder' to $sharingUser");
			syslog_notice("chgrp($mxfSubFolder,$sharingUser");
			chgrp($mxfSubFolder,$sharingUser);
			if(!$result) syslog_notice("Cannot chgrp folder '$mxfSubFolder' to $sharingUser");
		}

		$linkName="$mxfSubFolder/{$entry}_$fromUserName";
		if(!is_link($linkName))
			SharingOperations::createSymlink($linkName,$target,$sharingUser);
		SharingOperations::ensureLinkOwnership($linkName,$sharingUser);
	}

	static function purgeAvid($pathAbs,$users)
	{
		foreach($users as $user)
			self::purgeAvidForUser($pathAbs,$user,$users);
	}


	static function purgeAvidForUser($pathAbs,$user,$users)
	{
		$mxfFolder="$pathAbs/$user/".MXF_SUBFOLDER;
		if(!file_exists($mxfFolder)) return;
		$folders=self::mxfSubFolderLinks($mxfFolder);
		foreach($folders as $folder)
		{
			$linkName="$mxfFolder/$folder";
			$target=readlink($linkName);
			$rootFolder=dirname($target);
			$targetHomeFolder=dirname(dirname($rootFolder));

			//if the link does not point to a folder, remove it
			if(!is_dir($target))
			{
				unlink($linkName);
				syslog_notice("Removed '$linkName'; target '$target' is not a folder");
			}
			//the link must point to an mxf folder
			elseif(!SharingFolders::endsWith($rootFolder,MXF_SUBFOLDER))
			{
				unlink($linkName);
				syslog_notice("Removed '$linkName'; in target '$target' ".
					"the target is not a valid mxf folder");
			}
			//the link must point to member project folder
			elseif(!SharingFolders::isGroupMemberHomeFolder($pathAbs,$users,$targetHomeFolder))
			{
				//file could have been deleted already by a concurrent process
				if(file_exists($linkName))
                                {
                                        unlink($linkName);
        				syslog_notice("Removed '$linkName'; in target '$target' ".
					"the home folder '$targetHomeFolder' is not the home folder for a workspace member");
                                }
			}

		}	
	}

	static function mxfSubFolders($mxfFolder)
	{
		return self::mxfSubFoldersForType($mxfFolder,'folder');
	}

	static function mxfSubFolderLinks($mxfFolder)
	{
		return self::mxfSubFoldersForType($mxfFolder,'link');
	}

	static function isRequiredMxfSubFolderType($target,$type)
	{
		if(is_file($target)) return false;
		if($type=='folder' && is_link($target)) return false;
		if($type=='link' && !is_link($target)) return false;
		return true;
	}

	static function mxfSubFoldersForType($mxfFolder,$type)
	{
		$folders=array();
		if ($handle = opendir($mxfFolder))
		{
			while(false !== ($entry = readdir($handle)))
			{
				$target="$mxfFolder/$entry";
				if(
					!SharingFolders::isRejectedFolderEntry($entry)  && 
					self::isRequiredMxfSubFolderType($target,$type) &&
					substr($entry,0,1)!='.' && //don't deal with hidden fsobjects
                                        $entry!='resource.frk' //don't deal with these folder/files either (MAC OSX)
				)
				$folders[$entry]=$entry;
			}
			closedir($handle);
		}
		return $folders;
	}
}

