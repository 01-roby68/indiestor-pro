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
requireLibFile("inotify/chmodRecursive.php");
requireLibFile('inotify/shellSilent.php');
requireLibFile("admin/renameUsingShell.php");

class SharingStructureAvid
{

        static $repurgeRequired=false;

	static function reshare($workspace,$users)
	{
                $conf=new EtcWorkspaces('avid');
                $path=$conf->workspaces[$workspace];
      
                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;

                $group='avid_'.$workspace;

		if($users==null) $users=array();
		syslog_notice("resharing workspace '$workspace' for avid folders");
                self::verifyUserFolders($workspace,$pathAbs,$group,$users);
		self::verifyProjects($workspace,$pathAbs,$group,$users);
                self::$repurgeRequired=true;
                while(self::$repurgeRequired)
                {
                        self::$repurgeRequired=false;
        		self::purgeProjectLinks($pathAbs,$users);
                }
	}

        static function renameRepurge($from,$to)
        {
                syslog_notice("purge: moving $from to $to");
                renameUsingShell($from,$to);
                self::$repurgeRequired=true;
        }

        static function verifyUserFolders($workspace,$pathAbs,$group,$users)
        {
                foreach($users as $user) {
                        $userFolder="$pathAbs/$user";
                        if(!is_dir($userFolder)) mkdir($userFolder);
                        SharingOperations::fixOwnerGroup($group,$user,$userFolder);
		        SharingOperations::fixFsObjectPermissions($userFolder,"755");
                }
        }

	static function verifyProjects($workspace,$pathAbs,$group,$users)
	{
		foreach($users as $user)
		{
			$projects=sharingFolders::userAvidProjects("$pathAbs/$user");
			foreach($projects as $project)
			{
                		$projectFolder="$pathAbs/$user/$project";
                                $shared="$projectFolder/Shared";
                                #if the folder has been shared already, we reshare, even if there is no valid AVP file
                                if(SharingFolders::folderHasValidAVPfile($projectFolder) || file_exists($shared))
                                {
				        self::verifyProject($pathAbs,$user,$project,$users,$group);
				        self::verifyProjectSharing($pathAbs,$user,$project,$users,$group);
                                }
			}
		}
	}

	static function verifyProject($pathAbs,$user,$project,$users,$group)
	{
		//verify ownership/groupship on .avid folder
		$projectFolder="$pathAbs/$user/$project";
                if(file_exists($projectFolder))
        		chmodBase($projectFolder,0755,$user,$user);

		self::verifyProjectSharedFolder($pathAbs,$user,$project,$users,$group);
	}

	static function verifyProjectSharedFolder($pathAbs,$user,$project,$users,$group)
	{
		$projectFolder="$pathAbs/$user/$project";
		$shared="$projectFolder/Shared";

		if(!is_dir($shared) && !file_exists($shared)) mkdir($shared);
		SharingOperations::fixFsObjectPermissions($shared,"755");

		#the owner's own shared subfolder
		$sharedSubOwner="$shared/$user";

		if(!is_dir($sharedSubOwner)) 
		{
                        mkdir($sharedSubOwner);
		}
		SharingOperations::fixProjectFsObjectOwnership($group,$user,$sharedSubOwner);
		SharingOperations::fixFsObjectPermissions($sharedSubOwner,"755");

                chmodRecursive($sharedSubOwner, 0644,0755,$user,$group);


	        #the unprotected shared subfolder
	        $sharedUnprotected="$shared/Unprotected";

               #the unprotected folder
	        if(!is_dir($sharedUnprotected)) 
	        {
                        mkdir($sharedUnprotected);
	        }

                chmodRecursive($sharedUnprotected, 0666,0777,$user,$group);

		#avid copy 
		$projectCopy=self::folderAvidToCopy($project);

		#link for each other member
		foreach($users as $sharingUser)
		{
			if($sharingUser!=$user)
			{
				$linkName="$shared/$sharingUser";
				$target="$pathAbs/$sharingUser/Avid Shared Projects".
                                        "/$projectCopy/Shared/$sharingUser";
				SharingOperations::verifySymLink($linkName,$target,$user);		
			}
		}
	}

	static function verifyProjectSharing($pathAbs,$owner,$project,$users,$group)
	{
		foreach($users as $user)
		{
			if($user!=$owner)
			{
				self::verifyProjectSharingMember($pathAbs,$group,$owner,$user,$project,$users);
			}
		}
	}

	static function folderAvidToCopy($folderName)
	{
		$prefix=substr($folderName,0,strlen($folderName)-strlen(TRIGGER));
		return "$prefix".PROJCOPY;
	}

	static function verifyProjectSharingMember($pathAbs,$group,$owner,$user,$project,$users)
	{
                #if the user has no home directory, bail out (this could be an error when deleting the user)
                if(!is_dir("$pathAbs/$user")) return;

		#the user's Avid Shared Projects folder
		$aspFolder="$pathAbs/$user/Avid Shared Projects";
		if(!is_dir($aspFolder)) mkdir($aspFolder);
		SharingOperations::fixUserObjectOwnership('root',$aspFolder);
		SharingOperations::fixFsObjectPermissions($aspFolder,"755");

		#the user's project.copy folder
		$projectCopy=self::folderAvidToCopy($project);
		$prjCopyFolder="$aspFolder/$projectCopy";

		if(!is_dir($prjCopyFolder)) 
                        mkdir($prjCopyFolder);


		SharingOperations::fixProjectFsObjectOwnership($group,$user,$prjCopyFolder);
		SharingOperations::fixFsObjectPermissions($prjCopyFolder,"750");

		#copy avp, .avs and .xml files
		self::copyAvidProjectFiles("$pathAbs/$owner/$project",$prjCopyFolder,$user);

		#the user's shared folder
		$shared="$prjCopyFolder/Shared";
		if(!is_dir($shared)) mkdir($shared);
		SharingOperations::fixFsObjectPermissions($shared,"755");
		SharingOperations::fixProjectFsObjectOwnership('indienotify-pro','root',$shared);

		#the link from the project owner
		$sharedSubOwner="$shared/$owner";
		$target="$pathAbs/$owner/$project/Shared/$owner";
		SharingOperations::verifySymLink($sharedSubOwner,$target,$user);		

		#the link from unprotected
		$sharedUnprotected="$shared/Unprotected";
		$target="$pathAbs/$owner/$project/Shared/Unprotected";
		SharingOperations::verifySymLink($sharedUnprotected,$target,$user);		

		#the user's own shared subfolder
		$sharedSubUser="$shared/$user";
		if(!is_dir($sharedSubUser))
				mkdir($sharedSubUser);

                chmodRecursive($sharedSubUser, 0644,0755,$user,$group);
//		SharingOperations::fixProjectFsObjectOwnership($group,$user,);
//		SharingOperations::fixFsObjectPermissions($sharedSubUser,"755");

		#all other users (not the member himself, nor the owner)
		foreach($users as $sharingMember)
		{
			if($sharingMember!=$owner && $sharingMember!=$user)
			{
				$linkName="$shared/$sharingMember";
				$target="$pathAbs/$sharingMember/Avid Shared Projects/".
                                        "$projectCopy/Shared/$sharingMember";
				SharingOperations::verifySymLink($linkName,$target,$user);		
			}
		}		
	}

        static function copyAvidProjectFile($memberName,$source,$target)
        {
	        if(!file_exists($target)) copy($source,$target);
	        SharingOperations::fixUserObjectOwnership($memberName,$target);
	        SharingOperations::fixFsObjectPermissions($target,"640");
        }

	static function copyAvidProjectFiles($ownerProjectFolder,$sharingMemberCopyFolder,$memberName)
	{
		if ($handle = opendir($ownerProjectFolder))
		{
			while(false !== ($entry = readdir($handle)))
			{
				$source="$ownerProjectFolder/$entry";
			        $copy=str_replace(TRIGGER,PROJCOPY,$entry);
			        $target="$sharingMemberCopyFolder/$copy";
				if(is_file($source))
				{
					if(SharingFolders::endsWith($entry,'.avp'))
                                                self::copyAvidProjectFile($memberName,$source,$target);
					else if(SharingFolders::endsWith($entry,'.avs'))
                                                self::copyAvidProjectFile($memberName,$source,$target);
					else if(SharingFolders::endsWith($entry,'.xml'))
                                                self::copyAvidProjectFile($memberName,$source,$target);
				}
			}
			closedir($handle);
		}
		else
		{
			syslog_notice("Cannot open folder '$ownerProjectFolder'".
                                " for copying .avp, .avs and .xml files");
		}
	}

	static function purgeProjectLinks($pathAbs,$users)
	{
		foreach($users as $user)
		{
			self::purgeOldProjectsForUser($pathAbs,$user,$users);
		}
		foreach($users as $user)
		{
			self::purgeInvalidSymlinksInProjects($pathAbs,$user,$users);
			self::purgeInvalidSymlinksInAVSFolder($pathAbs,$user,$users);
		}                
	}

	static function homeFolderSegmentForLinkTarget($folder)
	{
		if(preg_match('|/Avid Shared Projects/|',$folder))
		{
			//Example: /home/carl/Avid Shared Projects/project3.copy/Shared/peter
			//Example: /home/carl/Avid Shared Projects/project2.copy/Shared/carl
			$segments=explode('/',$folder);
			array_pop($segments);
			array_pop($segments);
			array_pop($segments);
			array_pop($segments);
			$home=implode('/',$segments);
			return $home;
		}
		else if(preg_match('|/Shared/|',$folder))
		{
			//Example: home/peter/project3.avid/Shared/peter
			//Example: home/erik/haha4.avid/Shared/erik
			$segments=explode('/',$folder);
			array_pop($segments);
			array_pop($segments);
			array_pop($segments);
			$home=implode('/',$segments);
			return $home;
		}
		else return null;
	}

	static function purgeInvalidSymlinksInProjects($pathAbs,$user,$users)
	{
		$projects=sharingFolders::userAvidProjects("$pathAbs/$user");
		foreach($projects as $project)
		{
			$sharedSubFolderRoot="$pathAbs/$user/$project/Shared";
			if(!file_exists($sharedSubFolderRoot)) continue;
			$sharedSubFolders=SharingFolders::userSubFolders($sharedSubFolderRoot);
			foreach($sharedSubFolders as $sharedSubFolder)
			{
				$memberFolder="$sharedSubFolderRoot/$sharedSubFolder";
			        if(is_link($memberFolder))
			        {
				        $target=readlink($memberFolder);
				
				        //if the link does not point to a folder, remove it
				        if(!is_dir($target))
				        {
					        unlink($memberFolder);
					        syslog_notice("purgeInvalidSymlinksInProjects: Removed '$memberFolder'; ".
                                                        "target '$target' is not a valid link target");
				        }
				        //the link must point to member project folder
				        $targetHomeFolder=self::homeFolderSegmentForLinkTarget($target);
				        if(!SharingFolders::isGroupMemberHomeFolder($pathAbs,$users,$targetHomeFolder))
				        {
					        if(file_exists($memberFolder)) unlink($memberFolder);
					        syslog_notice("Removed '$memberFolder'; in target '$target' ".
						        "the workspace folder '$targetHomeFolder'".
                                                        " is in the section of a workspace user");
				        }
			        }	
			}			
		}
	}

	static function purgeInvalidSymlinksInAVSFolder($pathAbs,$user,$users)
	{
		$avpFolder="$pathAbs/$user/Avid Shared Projects";
		if(!file_exists($avpFolder)) return;
		if(!is_dir($avpFolder)) return;
		$copyFolders=SharingFolders::userSubFolders($avpFolder);
		foreach($copyFolders as $copyFolder)
		{
			$sharedSubFolderRoot="$avpFolder/$copyFolder/Shared";
			$sharedSubFolders=SharingFolders::userSubFolders($sharedSubFolderRoot);
			foreach($sharedSubFolders as $sharedSubFolder)
			{
				$memberFolder="$sharedSubFolderRoot/$sharedSubFolder";
			        if(is_link($memberFolder))
			        {
				        $target=readlink($memberFolder);
				        //if the link does not point to a folder, remove it
				        if(!is_dir($target))
				        {
					        unlink($memberFolder);
					        syslog_notice("purgeInvalidSymlinksInAVSFolder:".
                                                        " Removed '$memberFolder'; ".
                                                        "target '$target' is not a valid link target");
				        }

				        //the link must point to member project folder
				        $targetHomeFolder=self::homeFolderSegmentForLinkTarget($target);
				        if(!SharingFolders::isGroupMemberHomeFolder($pathAbs,$users,$targetHomeFolder))
				        {
					        if(file_exists($memberFolder)) unlink($memberFolder);
					        syslog_notice("Removed '$memberFolder'; in target '$target' ".
						        "the workspace folder '$targetHomeFolder'".
                                                        " is not the folder for a workspace member");
				        }
			        }	
			}
                        //check if there exists an .avid folder for this .copy folder
                        $baseOfCopy=preg_replace('/(.*)'.preg_quote(PROJCOPY,'/').'/','${1}',$copyFolder);
                        $originalProjectFound=false;
                        foreach($users as $member)
                        {
                                if($user !== $member)
                                {
                                        $originalAvidProject="$pathAbs/$member/$baseOfCopy".TRIGGER;
                                        if(is_dir($originalAvidProject)) $originalProjectFound=true;
                                }
                        }
                        if(!$originalProjectFound)
                        {
                                $copyFolderPath="$avpFolder/$copyFolder";
                                syslog_notice("rm -rf '$copyFolderPath'");
                                $copyFolderPath=escapeshellarg($copyFolderPath);
                                shellSilent("rm -rf $copyFolderPath");
                        }
			
		}
                $avpFolder=escapeshellarg($avpFolder);
                $finderCmd2="ls $avpFolder 2> /dev/null";
                $numberOfRemainingCopyFolders=shellSilent("$finderCmd2 | wc -l");
                if(intval($numberOfRemainingCopyFolders)==0)
                {
                        syslog_notice("rm -rf $avpFolder");
                        shellSilent("rm -rf $avpFolder");
                }
	}

	static function purgeOldProjectsForUser($pathAbs,$user,$users)
	{
		$oldProjectFolders=SharingFolders::userRenamedProjectFolders("$pathAbs/$user");
                if(count($oldProjectFolders)>0)
                        syslog_notice('purge: discovered old project folders:'.join(',',$oldProjectFolders));
		foreach($oldProjectFolders as $oldProjectFolder)
		{
                        $archiveFolder=self::createOldProjectArchiveFolder($pathAbs,$user,$oldProjectFolder);
                        $oldProjectName=self::detectOldProjectName($pathAbs,$user,$oldProjectFolder);
			self::purgeOldProjectSharedFolderForUser($pathAbs,$user,$oldProjectFolder,$archiveFolder);
                        self::archiveProjectTopLevelsForUsers($pathAbs,$user,$oldProjectFolder,$users,$archiveFolder,$oldProjectName);
		}
	}
	
        static function createOldProjectArchiveFolder($pathAbs,$user,$oldProjectFolder) 
        {
                $timestamp=date("Ymd-His");
		$archiveFolder="$pathAbs/$user/$oldProjectFolder/Archived-{$timestamp}";
		if(!is_dir($archiveFolder) && !file_exists($archiveFolder)) mkdir($archiveFolder);
		SharingOperations::fixUserObjectOwnership($user,$archiveFolder);
                return $archiveFolder;
        }

	static function endsWith($str, $needle)
	{
		$length = strlen($needle);
		$result=!$length || substr($str, - $length) === $needle;
	   	return $result;
	}

        static function detectOldProjectName($pathAbs,$user,$oldProjectFolder) {
		$sharedSubFolderRoot="$pathAbs/$user/$oldProjectFolder/Shared";
		$sharedSubFolders=SharingFolders::userSubFolders($sharedSubFolderRoot);
		foreach($sharedSubFolders as $sharedSubFolder)
		{
			$pathSharedSubFolder="$sharedSubFolderRoot/$sharedSubFolder";
			if(is_link($pathSharedSubFolder))
			{
				$source=readlink($pathSharedSubFolder);
                                $copySegment=basename(dirname(dirname($source)));
                                $oldProjectName=substr($copySegment,0,-strlen(PROJCOPY));
                                return $oldProjectName;
                        }
                }
                return null; //not found
        }

        static function archiveProjectTopLevelsForUsers($pathAbs,$user,$oldProjectFolder,$users,$archiveFolder,$oldProjectName)
        {
                if($oldProjectName==null) {
                        //try to detect old project name from avp file
                        $path=escapeshellarg("$pathAbs/$user/$oldProjectFolder");
                        $oldProjectName=trim(shell_exec("basename $(find $path -type f -name *.avp) .avp"));
                        if(self::endsWith($oldProjectName,TRIGGER)) {
                                $oldProjectName=substr($oldProjectName,0,-strlen(TRIGGER));
                        }
                }

                foreach($users as $sharingUser) {
                        if($sharingUser!=$user) {
                                $toplevel="$pathAbs/$sharingUser/Avid Shared Projects/$oldProjectName".PROJCOPY;
                                if(is_dir($toplevel)) {
                                        $archiveToplevel="$archiveFolder/{$sharingUser}-toplevel";
                                        self::renameRepurge($toplevel,$archiveToplevel);
                                        
                                        shellSilent("rm -Rf ".escapeshellarg($archiveToplevel)."/Shared");
                                        shellSilent("rm ".escapeshellarg("$archiveToplevel/$oldProjectFolder".PROJCOPY.".avp"));
                                        shellSilent("rm ".escapeshellarg("$archiveToplevel/$oldProjectFolder".PROJCOPY." Settings.avs"));
                                        shellSilent("rm ".escapeshellarg("$archiveToplevel/$oldProjectFolder".PROJCOPY." Settings.xml"));
                			shellSilent("chown -R $user.$user ".escapeshellarg($archiveToplevel));
                                }
                        }
                }
        }

	static function purgeOldProjectSharedFolderForUser($pathAbs,$user,$oldProjectFolder,$archiveFolder)
	{
		//handle shared subfolders
		$sharedSubFolderRoot="$pathAbs/$user/$oldProjectFolder/Shared";
		$sharedSubFolders=SharingFolders::userSubFolders($sharedSubFolderRoot);
		foreach($sharedSubFolders as $sharedSubFolder)
		{
			//move content to archive
			$pathSharedSubFolder="$sharedSubFolderRoot/$sharedSubFolder";
			$subArchiveFolder="$archiveFolder/$sharedSubFolder";
			if(is_link($pathSharedSubFolder))
			{
				$islink=true;
				$source=readlink($pathSharedSubFolder);
			}
			else
			{
				$islink=false;
				$source=$pathSharedSubFolder;
			}
			if(file_exists($source)) 
                                self::renameRepurge($source,$subArchiveFolder);
                        $subArchiveFolder=escapeshellarg($subArchiveFolder);
			shellSilent("chown -R $user.$user $subArchiveFolder");

			//purge copy
			if($islink)
			{
				$copy=dirname(dirname($source));
				$rootOfCopy=dirname($copy);
				$baseOfCopy=basename($rootOfCopy);
				if($baseOfCopy=='Avid Shared Projects')
				{

					//remove copy of project, if it is empty
                                        $copy=escapeshellarg($copy);
					$numberOfItems=intval(shellSilent("ls $copy 2> /dev/null | wc -l"));
					if($numberOfItems==0) shellSilent("rm -rf $copy");
					//check if this is the last copy
                                        $rootOfCopy=escapeshellarg($rootOfCopy);
					$numberOfItems=intval(shellSilent("ls '$rootOfCopy' 2> /dev/null | wc -l"));
					if($numberOfItems==0) shellSilent("rm -rf '$rootOfCopy'");
				}
			}


		}

		//purge shared folder
                syslog_notice("purge: deleting folder $sharedSubFolderRoot");
                $sharedSubFolderRoot=escapeshellarg($sharedSubFolderRoot);
		shellSilent("rm -rf $sharedSubFolderRoot");
	}
}

