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

class GenericWorkspace extends Workspace
{

        const WORKSPACETYPE='generic';

        static function createGroup($workspace)
        {
                $groupName1='generic_rw_'.$workspace;
                $groupName2='generic_ro_'.$workspace;
        	ShellCommand::exec_fail_if_error("addgroup $groupName1");
        	ShellCommand::exec_fail_if_error("addgroup $groupName2");
        }

        static function deleteGroup($workspace)
        {
                $groupName1='generic_rw_'.$workspace;
                $groupName2='generic_ro_'.$workspace;
        	ShellCommand::exec_fail_if_error("delgroup $groupName1");
        	ShellCommand::exec_fail_if_error("delgroup $groupName2");
        }

        static function addWriteUser($commandAction)
        {
                echo "to be implemented\n";
        }

        static function addReadOnlyUser($commandAction)
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

