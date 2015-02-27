<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/


class Version extends EntityType
{
        static function json($commandAction)
        {
                //handled by show command
                return;
        }

        static function getVersion()
        {
                $rootFolder=dirname(dirname(dirname(dirname(__FILE__))));
                $version=trim(file_get_contents("$rootFolder/VERSION"));
                return $version;
        }

        static function show($commandAction)
        {
                $version=self::getVersion();
                if(ProgramActions::actionExists('json')) {
                        $struct=['version'=>$version];
                        echo json_encode_legacy($struct)."\n";
                } else {
                        echo "$version\n";
                }                        
        }
}

