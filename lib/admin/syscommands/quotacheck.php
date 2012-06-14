<?php
/*
        Indiestor program

	Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        By Alex Gardiner, alex.gardiner@canterbury.ac.uk
*/

/*

Repairs the quota file. Example:

$ quotacheck --format=vfsold -ucm /

*/

//http://loydgravitt.wordpress.com/2012/02/11/important-note-for-is418-linux-students/

function syscommand_quotacheck_new_quota_file($mountPoint)
{
	//Nice that the url above reveals that we can simply unmount and chmod all those .gvfs Gnome clusterfucks ;-)
	//watch out for those horrible Gnome virtual file system .gvfs
	//we will try to unmount them
	unclusterFuckTheHorribleGnome_gvfs($mountPoint);
	ShellCommand::exec("quotacheck --format=vfsold -ucm $mountPoint");
}

function syscommand_quotacheck_existing_quota_file($mountPoint)
{
	//Nice that the url above reveals that we can simply unmount and chmod all those .gvfs Gnome clusterfucks ;-)
	//watch out for those horrible Gnome virtual file system .gvfs
	//we will try to unmount them
	unclusterFuckTheHorribleGnome_gvfs($mountPoint);
	ShellCommand::exec("quotacheck --format=vfsold -um $mountPoint");
}

function unclusterFuckTheHorribleGnome_gvfs($mountPoint)
{
	//This is an attempt to get rid of the imbecile .gvfs Gnome virtual filesystems (Why? Oh my God, why?)
	//Normally, it should work. This should allow us to kick all of that crap into the ocean.
	$etcPasswd=EtcPasswd::instance();
	$users=$etcPasswd->users;
	foreach($users as $user)
	{
		$homeFolder=$user->homeFolder;

		if(substr($homeFolder, 0, strlen($mountPoint)) === $mountPoint) //startsWith
		{
			$dirtyFuck="$homeFolder/.gvfs";
			if(file_exists($dirtyFuck))
			{
				shellCommand::exec("umount $dirtyFuck");
				shellCommand::exec("chmod 755 $dirtyFuck");
			}
		}
	}
}
