<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

function sysquery_df_filesystem_for_folder($folder)
{
	return trim(ShellCommand::query_fail_if_error("df -T $folder | tail -n1 | awk '{print $2}'"));
}

class Workspace extends EntityType
{

        const WORKSPACETYPE='';
        const OTHER_WORKSPACETYPE='';

        static function add($commandAction)
        {
                $conf=new EtcWorkspaces(static::WORKSPACETYPE);
		$workspace=ProgramActions::$entityName;

                //only accept valid characters
                if(!ActionEngine::isValidCharactersInName($workspace)) {
                        ActionEngine::error('ERR_INVALID_CHARACTERS');
                        return;
                }

                //stop if workspace exists already
                if(array_key_exists($workspace,$conf->workspaces)) {
                        ActionEngine::error('ERR_WORKSPACE_EXISTS_ALREADY');
                        return;
                }

                $otherConf=new EtcWorkspaces(static::OTHER_WORKSPACETYPE);

                //stop if workspace exists already in other workspace type
                if(array_key_exists($workspace,$otherConf->workspaces)) {
                        ActionEngine::error('ERR_WORKSPACE_EXISTS_ALREADY');
                        return;
                }

                $path=$commandAction->actionArg;
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

                //regenerate config afp/smb files
                ActionEngine::generateAfpSmbConfig();
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

                //do not delete if busy
                $lsof=trim(ShellCommand::query("lsof +D $pathAbs"));
                if(strlen($lsof)>0) {
                        ActionEngine::error('ERR_DELETE_WORKSPACE_BUSY');
                        return;
                }

                $fileSystem=sysquery_df_filesystem_for_folder(dirname($pathAbs));

		if($fileSystem=='zfs') {
                        ShellCommand::exec("zfs destroy $path");
                } else {
                        ShellCommand::exec("rm -rf $path"); 
                }

                //delete group
                static::deleteGroup($workspace);

                //save config file                
                $conf->remove($workspace);
                $conf->save();

                //regenerate config afp/smb files
                ActionEngine::generateAfpSmbConfig();
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
                        ShellCommand::exec_fail_if_error("zfs set quota={$quota} $path");
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

