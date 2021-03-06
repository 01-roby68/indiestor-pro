<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

if(!function_exists('sysquery_df_filesystem_for_folder')) {
        function sysquery_df_filesystem_for_folder($folder)
        {
	        return trim(ShellCommand::query("df -T $folder | tail -n1 | awk '{print $2}'"));
        }
}

class Workspace extends EntityType
{

        const WORKSPACETYPE='';
        const OTHER_WORKSPACETYPE='';

        static function add($commandAction)
        {
		$workspace=ProgramActions::$entityName;
                $path=$commandAction->actionArg;
                //remove trailing slash
                $folder=rtrim($path, '/'); 
                self::addWithParms($workspace,$path);
        }

        static function addWithParms($workspace,$path)
        {
                $conf=new EtcWorkspaces(static::WORKSPACETYPE);

                //only accept valid characters
                if(!ActionEngine::isValidCharactersInName($workspace)) {
                        ActionEngine::error('ERR_INVALID_CHARACTERS');
                        return;
                }

		if(strlen($workspace)>20) {
                        ActionEngine::error('ERR_WORKSPACE_NAME_TOO_LONG');
                        return;
		}

                //stop if workspace exists already
                if(array_key_exists($workspace,$conf->workspaces)) {
                        ActionEngine::error('ERR_WORKSPACE_EXISTS_ALREADY');
                        return;
                }

                //stop if path exists already for another workspace
                if($conf->pathExists($path)) {
                        ActionEngine::error('ERR_PATH_EXISTS_ALREADY_FOR_OTHER_WORKSPACE');
                        return;
                }

                $otherConf=new EtcWorkspaces(static::OTHER_WORKSPACETYPE);

                //stop if workspace exists already in other workspace type
                if(array_key_exists($workspace,$otherConf->workspaces)) {
                        ActionEngine::error('ERR_WORKSPACE_EXISTS_ALREADY');
                        return;
                }

                //stop if path exists already for another workspace
                if($otherConf->pathExists($path)) {
                        ActionEngine::error('ERR_PATH_EXISTS_ALREADY_FOR_OTHER_WORKSPACE');
                        return;
                }

                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;
                $fileSystem=sysquery_df_filesystem_for_folder(dirname($pathAbs));

		if($fileSystem=='zfs') {
                        //disallow the use of leading / for zfs
                        if(substr($path,0,1)=='/') {
                                ActionEngine::error('ERR_ZFS_DATASET_LEADING_SLASH_IN_DATASET');
                                return;
                        }

                        //check if zfs path exists already
                        if(!is_dir($pathAbs)) {
                                ShellCommand::exec_fail_if_error("zfs create $path");
                        }

                        //generic workspaces should be writeable
                        if(static::WORKSPACETYPE==='generic')
                                ShellCommand::exec_fail_if_error("chmod -R a+rwx $pathAbs");


                }
                else 
                {
                        //non-zfs folder must be absolute path
                        if(substr($path,0,1)!=='/') {
                                ActionEngine::error('ERR_NON_ZFS_FOLDER_MUST_BE_ABSOLUTE_PATH');
                                return;
                        }

                        //check if parent exists
                        $parentPath=dirname($pathAbs);
                        if(!is_dir($parentPath)) {
                                ActionEngine::error('ERR_PARENT_PATH_DOES_NOT_EXIST');
                                return;
                        }


                        //check if folder exists already; only create it if it does not exist
                        if(!is_dir($path)) {

                                mkdir($path);
                        }

                        //generic workspaces should be writeable
                        if(static::WORKSPACETYPE==='generic')
                                ShellCommand::exec_fail_if_error("chmod -R a+rwx $path");


                }

                //make CNID folder for avid
                if(static::WORKSPACETYPE=='avid') {
                        if(!is_dir("$pathAbs/CNID")) mkdir("$pathAbs/CNID");
                        ShellCommand::exec_fail_if_error("chmod 755 $pathAbs/CNID");                
                        ShellCommand::exec_fail_if_error("chown root.root $pathAbs/CNID");
                }

                //create group
                static::createGroup($workspace);

                //save config file
                $conf->add($workspace,$path);
                $conf->save();

                // regen shares and refresh filers
                ActionEngine::forkRefreshChildProgram();

        }

        static function delete($commandAction)
        {
                $conf=new EtcWorkspaces(static::WORKSPACETYPE);
                $workspace=ProgramActions::$entityName;

                if(!array_key_exists($workspace,$conf->workspaces)) {
                        ActionEngine::error('ERR_WORKSPACE_DOES_NOT_EXISTS');
                        return;
                }

                $path=$conf->workspaces[$workspace];
                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;

                // if force option is specified then delete the workspace even if it is busy
                if (ProgramActions::actionExists('force')) {
                goto force;
                }

                //do not delete if busy
                //check if any command uses the folder
                $isActiveCheck=trim(ShellCommand::query("lsof $pathAbs"));

                //if there is lsof output warn that the workspace is busy
                if(strlen($isActiveCheck)>0) {
                                ActionEngine::error('ERR_DELETE_WORKSPACE_BUSY');
                                return;
                } else {
                goto force;
                }

                // beyond here run the delete process
                force:

                //stop watching
                InotifyWait::stopWatching($workspace);      

                // eval filesystem type
                $fileSystem=sysquery_df_filesystem_for_folder(dirname($pathAbs));

                // destory zpool is zfs, or simply delete if regular filesystem
                if($fileSystem=='zfs') {
                        ShellCommand::exec("fuser -k $pathAbs; umount -l $path; zfs destroy -f $path  > /dev/null 2>/dev/null &");
                } else {
                        ShellCommand::exec("fuser -k $pathAbs; rm -rf $pathAbs > /dev/null 2>/dev/null &"); 
                }

                //delete group
                static::deleteGroup($workspace);

                //delete old workspace cache record
                ShellCommand::exec("rm /var/cache/indiestor-pro/*".$workspace."*");

                //save config file                
                $conf->remove($workspace);
                $conf->save();

                // regen shares and refresh filers
                ActionEngine::forkRefreshChildProgram();

        }

        static function force($commandAction)
        {
        /* handled in the delete action already */
        }

        static function setZfsQuota($commandAction)
        {
                $conf=new EtcWorkspaces(static::WORKSPACETYPE);
		$workspace=ProgramActions::$entityName;

                if(!array_key_exists($workspace,$conf->workspaces)) {
                        ActionEngine::error('ERR_WORKSPACE_DOES_NOT_EXISTS');
                        return;
                }

                $quota=$commandAction->actionArg;

                $path=$conf->workspaces[$workspace];

                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;

                $fileSystem=sysquery_df_filesystem_for_folder(dirname($pathAbs));

		if($fileSystem=='zfs') {
                        ShellCommand::exec_fail_with_message("zfs set quota={$quota} $path",
                                "Workspace quota unavailable. ZFS dataset not present.");
                } else {
                        ActionEngine::error('ERR_QUOTA_ONLY_FOR_ZFS');
                        return;
                }
        }

        static function removeZfsQuota($commandAction)
        {
                $conf=new EtcWorkspaces(static::WORKSPACETYPE);
		$workspace=ProgramActions::$entityName;

                if(!array_key_exists($workspace,$conf->workspaces)) {
                        ActionEngine::error('ERR_WORKSPACE_DOES_NOT_EXISTS');
                        return;
                }

                $path=$conf->workspaces[$workspace];

                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;

                $fileSystem=sysquery_df_filesystem_for_folder(dirname($pathAbs));

		if($fileSystem=='zfs') {
                        ShellCommand::exec_fail_if_error("zfs set quota=none $path");
                } else {
                        ActionEngine::error('ERR_QUOTA_ONLY_FOR_ZFS');
                        return;
                }
        }

}