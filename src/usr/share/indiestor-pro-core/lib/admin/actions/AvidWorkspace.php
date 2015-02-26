<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

require "Workspace.php";

class AvidWorkspace extends Workspace
{

        const WORKSPACETYPE='avid';

        static function createGroup($workspace)
        {
                $groupName='avid_'.$workspace;
        	ShellCommand::exec_fail_if_error("addgroup $groupName");
        }

        static function deleteGroup($workspace)
        {
                $groupName='avid_'.$workspace;
        	ShellCommand::exec_fail_if_error("delgroup $groupName");
        }

        static function addUser($commandAction)
        {
                echo "to be implemented\n";
        }

        static function removeUser($commandAction)
        {
                echo "to be implemented\n";
        }

//-----------------------

        static function showMembers($commandAction)
        {
                echo "to be implemented\n";
        }

        static function json($commandAction)
        {
                echo "to be implemented\n";
        }

//-----------------------

        static function reshare($commandAction)
        {
                echo "to be implemented\n";
        }


}

