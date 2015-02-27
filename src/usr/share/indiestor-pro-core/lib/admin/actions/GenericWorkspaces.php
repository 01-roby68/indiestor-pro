<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class GenericWorkspaces extends EntityType
{
        static function json($commandAction)
        {
                //handled by show command
                return;
        }

        static function show($commandAction)
        {
                $conf=new EtcWorkspaces('generic');
                if(ProgramActions::actionExists('json')) {
                        echo json_encode_legacy($conf->workspaces)."\n";
                } else {
                        if(count($conf->workspaces)===0) {
                                echo "no generic workspaces.\n";
                        } else {
                                $format1="%-20s %-50s\n";
                                $format2="%-20s %-50s\n";
                                printf($format1,"workspace","path");
                                foreach($conf->workspaces as $workspace=>$path) {
                                        printf($format2,$workspace,$path);
                                }
                        }
                }                        
        }
}

