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
		self::verifyProjects($groupName,$users);
                self::$repurgeRequired=true;
                while(self::$repurgeRequired)
                {
                        self::$repurgeRequired=false;
        		self::purgeProjectLinks($pathAbs,$users);
                }
	}

        static function renameRepurge($from,$to)
        {
                renameUsingShell($from,$to);
                self::$repurgeRequired=true;
        }

	static function verifyProjects($workspace,$patAbs,$group,$users)
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
				        self::verifyProjectArchive($pathAbs,$user,$project);
                                }
			}
		}
	}

	static function verifyProjectArchive($pathAbs,$user,$project)
	{
		#remove archive, if needed
		$projectFolder="$pathAbs/$user/$project";
		$archived="$projectFolder/Archived";
		if(is_dir($archived)) 
		{
			//check if archive is empty
			$numberOfItems=intval(shellSilent("ls --ignore resource.frk '$archived' 2> /dev/null | wc -l"));
			if($numberOfItems==0) shellSilent("rm -rf '$archived'");
		}
	}

	static function verifyProject($pathAbs,$user,$project,$users,$group)
	{
		//verify ownership/groupship on .avid folder
		$projectFolder="$pathAbs/$user/$project";
                if(file_exists($projectFolder))
        		chmodBase($projectFolder,0755,$user,$user);

		self::verifyProjectFiles($pathAbs,$user,$project);
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

		#owner's archive, if it exists
		$archived="$projectFolder/Archived";

                #the owner's own archive folder
		$archivedOwner="$archived/$user";
		if(!is_dir($sharedSubOwner)) 
		{
			if(is_dir($archivedOwner))
				renameUsingShell($archivedOwner, $sharedSubOwner);
			else
				if(!file_exists($sharedSubOwner)) mkdir($sharedSubOwner);
		}
		SharingOperations::fixProjectFsObjectOwnership($group,$user,$sharedSubOwner);
		SharingOperations::fixFsObjectPermissions($sharedSubOwner,"755");

                if(!empty($group))
                        chmodRecursive($sharedSubOwner, 0644,0755,$user,$group);
                else
                        chmodRecursive($sharedSubOwner, 0644,0755,$user,$user);


	        #the unprotected shared subfolder
	        $sharedUnprotected="$shared/Unprotected";

                #the unprotected folder
	        $archivedUnprotected="$archived/Unprotected";
	        if(!is_dir($sharedUnprotected)) 
	        {
		        if(is_dir($archivedUnprotected))
			        renameUsingShell($archivedUnprotected, $sharedUnprotected);
		        else
			        if(!is_dir($sharedUnprotected)) 
                                {
                                        mkdir($sharedUnprotected);
                                }
	        }

                if(!empty($group))
                        chmodRecursive($sharedUnprotected, 0664,0775,$user,$group);
                else
                        chmodRecursive($sharedUnprotected, 0664,0775,$user,$user);

		#avid copy 
		$projectCopy=self::folderAvidToCopy($project);

		#link for each other member
		foreach($users as $sharingUser)
		{
			if($sharingUser->name!=$userName)
			{
				$linkName="$shared/{$sharingUser->name}";
				$target="$pathAbs/{$sharingUser->name}/Avid Shared Projects".
                                        "/$projectCopy/Shared/{$sharingUser->name}";
				SharingOperations::verifySymLink($linkName,$target,$user);		
			}
		}
	}

	static function verifyProjectFiles($pathAbs,$user,$project)
	{
		$userName=$user->name;
		$homeFolder=$user->homeFolder;
		if ($handle = opendir("$pathAbs/$user/$project"))
		{
			while(false !== ($entry = readdir($handle)))
			{
				if(is_file("$pathAbs/$user/$project/$entry"))
				{
					if(SharingFolders::endsWith($entry,'.avp'))
						SharingOperations::renameAvpProjectFile($user,"$pathAbs/$user",$project,$entry);
					if(SharingFolders::endsWith($entry,'.avs'))
						SharingOperations::renameAvsProjectFile($user,"$pathAbs/$user",$project,$entry);
					if(SharingFolders::endsWith($entry,'.xml'))
						SharingOperations::renameXmlProjectFile($user,"$pathAbs/$user",$project,$entry);
				}
			}
			closedir($handle);
		}
		else
		{
			syslog_notice("Cannot open folder '$pathAbs/$user/$project' for renaming .avp, .avs and .xml files");
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
		$prefix=substr($folderName,0,strlen($folderName)-strlen('.avid'));
		return "$prefix.copy";
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
		if(!is_dir($prjCopyFolder)) mkdir($prjCopyFolder);
		SharingOperations::fixProjectFsObjectOwnership($group,$user,$prjCopyFolder);
		SharingOperations::fixFsObjectPermissions($prjCopyFolder,"750");

		#copy avp, .avs and .xml files
		self::copyAvidProjectFiles("$pathAbs/$owner/$project",$prjCopyFolder,$user);

		#the user's shared folder
		$shared="$prjCopyFolder/Shared";
		if(!is_dir($shared)) mkdir($shared);
		SharingOperations::fixProjectFsObjectOwnership($group,$user,$shared);
		SharingOperations::fixFsObjectPermissions($shared,"755");

                if(!empty($groupName))
                        chmodRecursive($shared, 0644,0755,$user,$group);
                else
                        chmodRecursive($shared, 0644,0755,$user,$user);


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
		{
			$archived="$pathAbs/$owner/$project/Archived";
			$archivedUser="$archived/$user";
			if(!is_dir($archivedUser))
				mkdir($sharedSubUser);
			else
			{
				renameUsingShell($archivedUser,$sharedSubUser);
				shellSilent("chown -R $user.$group '$sharedSubUser'");
			}
		}

		SharingOperations::fixProjectFsObjectOwnership($group,$user,$sharedSubUser);
		SharingOperations::fixFsObjectPermissions($sharedSubUser,"755");

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
			        $copy=str_replace('.avid','.copy',$entry);
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
			self::purgeOldProjectsForUser($pathAbs,$user);
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
						        "the home folder '$targetHomeFolder'".
                                                        " is in the section of a workspace user");
				        }
			        }	
			}			
		}
	}

/* ==============================        CONTINUE FROM HERE ==================================*/

	static function purgeInvalidSymlinksInAVSFolder($user,$users)
	{
		$avpFolder="{$user->homeFolder}/Avid Shared Projects";
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
				        if(!SharingFolders::isGroupMemberHomeFolder($users,$targetHomeFolder))
				        {
					        if(file_exists($memberFolder)) unlink($memberFolder);
					        syslog_notice("Removed '$memberFolder'; in target '$target' ".
						        "the home folder '$targetHomeFolder'".
                                                        " is not the home folder for a group member");
				        }
			        }	
			}
                        //check if there exists an .avid folder for this .copy folder
                        $baseOfCopy=preg_replace('/(.*)\.copy/','${1}',$copyFolder);
                        $originalProjectFound=false;
                        foreach($users as $member)
                        {
                                if($user->name != $member->name)
                                {
                                        $originalAvidProject="{$member->homeFolder}/$baseOfCopy.avid";
                                        if(is_dir($originalAvidProject)) $originalProjectFound=true;
                                }
                        }
                        if(!$originalProjectFound)
                        {
                                $copyFolderPath="$avpFolder/$copyFolder";
                                $finderCmd1="find '$copyFolderPath' -type f 2> /dev/null ".
                                                "| grep -v '.avp$' 2> /dev/null ".
                                                "| grep -v '.avs$' 2> /dev/null ".
                                                "| grep -v '.xml$' 2> /dev/null ".
                                                "| grep -v '/Statistics' 2> /dev/null ".
                                                "| grep -v '/WaveformCache' 2> /dev/null ".
                                                "| grep -v '/SearchData' 2> /dev/null ";
                                $finderCmd="$finderCmd1 | wc -l";
                                $numberOfFiles=shellSilent($finderCmd);
                                if(intval($numberOfFiles)==0) 
                                {
                                        syslog_notice("rm -rf '$copyFolderPath'");
                                        shellSilent("rm -rf '$copyFolderPath'");
                                }
                                else
                                {
                                        syslog_notice("finderCmd1:".
                                                shellSilent($finderCmd1));                                        
                                }
                        }
			
		}
                $finderCmd2="ls '$avpFolder' 2> /dev/null";
                $numberOfRemainingCopyFolders=shellSilent("$finderCmd2 | wc -l");
                if(intval($numberOfRemainingCopyFolders)==0)
                {
                        syslog_notice("rm -rf '$avpFolder'");
                        shellSilent("rm -rf '$avpFolder'");
                }
	}

	static function purgeOldProjectsForUser($user)
	{
		$oldProjectFolders=SharingFolders::userRenamedProjectFolders($user->homeFolder);
		foreach($oldProjectFolders as $oldProjectFolder)
		{
			self::verifyProjectFiles($user,$oldProjectFolder);
			self::purgeOldProjectForUser($user,$oldProjectFolder);
		}
	}
	
	static function purgeOldProjectForUser($user,$oldProjectFolder)
	{
		//create archive folder
		$archiveFolder="{$user->homeFolder}/$oldProjectFolder/Archived";
		if(!is_dir($archiveFolder) && !file_exists($archiveFolder)) mkdir($archiveFolder);
		SharingOperations::fixUserObjectOwnership($user->name,$archiveFolder);

		//handle shared subfolders
		$sharedSubFolderRoot="{$user->homeFolder}/$oldProjectFolder/Shared";
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
			if(file_exists($source)) self::renameRepurge($source,$subArchiveFolder);
			shellSilent("chown -R {$user->name}.{$user->name} '$subArchiveFolder'");

			//purge copy
			if($islink)
			{
				$copy=dirname(dirname($source));
				$rootOfCopy=dirname($copy);
				$baseOfCopy=basename($rootOfCopy);
				if($baseOfCopy=='Avid Shared Projects')
				{

					//remove copy of project, if it is empty
					$numberOfItems=intval(shellSilent("ls '$copy' 2> /dev/null | wc -l"));
					if($numberOfItems==0) shellSilent("rm -rf '$copy'");
					//check if this is the last copy
					$numberOfItems=intval(shellSilent("ls '$rootOfCopy' 2> /dev/null | wc -l"));
					if($numberOfItems==0) shellSilent("rm -rf '$rootOfCopy'");
				}
			}


		}

		//purge shared folder
		shellSilent("rm -rf '$sharedSubFolderRoot'");
	}

	static function renameUserAvidProjects($user)
	{
		$renameOps=array();
		$projects=sharingFolders::userAvidProjects($user->homeFolder);
		foreach($projects as $project)
		{
			$projectTmp="__{$project}__tmp__";
			renameUsingShell("{$user->homeFolder}/$project","{$user->homeFolder}/$projectTmp");
			$renameOps[]=array('tmp'=>$projectTmp,'project'=>$project);
		}
		return $renameOps;
	}

	static function renameBackUserAvidProjects($user,$renameOps)
	{
		foreach($renameOps as $renameOp)
		{
			$projectTmp=$renameOp['tmp'];
			$project=$renameOp['project'];
			renameUsingShell("{$user->homeFolder}/$projectTmp","{$user->homeFolder}/$project");
			self::verifyProjectFiles($user,$project);
		}
	}

	static function archiveASPFolder($user)
	{
		$aspFolder="{$user->homeFolder}/Avid Shared Projects";
		if(!is_dir($aspFolder)) return;
		$copyFolders=SharingFolders::userSubFolders($aspFolder);
		foreach($copyFolders as $copyFolder)
		{
			$projectPrefix=basename($copyFolder,'.copy');
			$sharedFolder="$aspFolder/$copyFolder/Shared";
			$members=SharingFolders::userSubFolders($sharedFolder);
			$ownFolder=null;
			$ownerName=null;
			foreach($members as $member)
			{
				$folder="$sharedFolder/$member";
				if($member==$user->name) $ownFolder=$folder;
				if(is_link($folder)) $target=readlink($folder);
				else $target="";
				$requiredSuffix="$projectPrefix.avid/Shared/$member";
				if(SharingFolders::endsWith($target,$requiredSuffix)) $ownerName=$member;
			}

			if($ownFolder!=null && $ownerName!=null)
			{
				//find owner record
				$etcPasswd=EtcPasswd::instance();
				$owner=$etcPasswd->findUserByName($ownerName);

				//archive
                                //this is possible, e.g. the 'Unprotected' folder
                                if($owner!=null)
                                {
				        $archived="{$owner->homeFolder}/$projectPrefix.avid/Archived";

				        if(!file_exists($archived))
				        {
					        mkdir($archived);
					        SharingOperations::fixUserObjectOwnership($ownerName,$archived);
				        }

				        $archiveSubFolder="$archived/{$user->name}";
				        shellSilent("chown -R $ownerName.$ownerName '$archiveSubFolder'");
                                        syslog_notice("archiving $ownFolder in $archiveSubFolder");  
				        self::renameRepurge($ownFolder,$archiveSubFolder);
                                }
			}
		}
	}
}

