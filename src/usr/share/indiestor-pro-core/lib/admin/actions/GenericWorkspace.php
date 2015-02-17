<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class GenericWorkspace extends EntityType
{

        static function add($commandAction)
        {
                $conf=new EtcWorkspaces('generic');
		$workspace=ProgramActions::$entityName;
                $path=$commandAction->actionArg;
                if(substr($path,0,1)!=='/')
                        $pathAbs="/$path";
                else $pathAbs=$path;
                $fileSystem=sysquery_df_filesystem_for_folder(dirname($pathAbs));
		if($fileSystem=='zfs')
                        ShellCommand::exec_fail_if_error("zfs create $path");
                else mkdir($path);
                $conf->add($workspace,$path);
                $conf->save();
        }

        static function addWriteUser($commandAction)
        {
                echo "to be implemented\n";
        }

        static function addReadOnlyUser($commandAction)
        {
                echo "to be implemented\n";
        }

        static function setLocation($commandAction)
        {
                echo "to be implemented\n";
        }

        static function removeWriteUser($commandAction)
        {
                echo "to be implemented\n";
        }

        static function removeReadOnlyUser($commandAction)
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

        static function delete($commandAction)
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

