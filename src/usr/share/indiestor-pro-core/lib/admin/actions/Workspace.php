<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class Workspace extends EntityType
{

        const WORKSPACETYPE='';

        static function add($commandAction)
        {
                $conf=new EtcWorkspaces(static::WORKSPACETYPE);
		$workspace=ProgramActions::$entityName;

                //stop if workspace exists already
                if(array_key_exists($workspace,$conf->workspaces)) {
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
                        if(is_dir($pathAbs)) {
                                ActionEngine::error('ERR_ZFS_DATASET_EXISTS_ALREADY');
                                return;
                        }

                        ShellCommand::exec_fail_if_error("zfs create $path"); //XXX trap errors
                }
                else 
                {
                        //check if folder exists already
                        if(is_dir($path)) {
                                ActionEngine::error('ERR_FOLDER_EXISTS_ALREADY');
                                return;
                        }

                        //check if parent exists
                        $parentPath=dirname($pathAbs);
                        if(!is_dir($parentPath)) {
                                ActionEngine::error('ERR_PARENT_PATH_DOES_NOT_EXIST');
                                return;
                        }


                        mkdir($path);
                }
                $conf->add($workspace,$path);
                $conf->save();
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

                $fileSystem=sysquery_df_filesystem_for_folder(dirname($pathAbs));

		if($fileSystem=='zfs') {
                        ShellCommand::exec_fail_if_error("zfs destroy $path");
                } else {
                        ShellCommand::exec_fail_if_error("rm -rf $path"); 
                }
                
                $conf->remove($workspace);
                $conf->save();
        }

        static function setLocation($commandAction)
        {
                echo "to be implemented\n";
        }

        static function addUser($commandAction)
        {
                echo "to be implemented\n";
        }

        static function removeUser($commandAction)
        {
                echo "to be implemented\n";
        }

        static function setZfsQuota($commandAction)
        {
                echo "to be implemented\n";
        }

        static function removeZfsQuota($commandAction)
        {
                echo "to be implemented\n";
        }

        static function reshare($commandAction)
        {
                echo "to be implemented\n";
        }

        static function showMembers($commandAction)
        {
                echo "to be implemented\n";
        }

        static function json($commandAction)
        {
                echo "to be implemented\n";
        }

}

