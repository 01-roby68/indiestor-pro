<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class AvidWorkspace extends EntityType
{

        static function add($commandAction)
        {
                $conf=new EtcWorkspaces('avid');
		$workspace=ProgramActions::$entityName;
                echo "workspace path: $workspace\n";
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

