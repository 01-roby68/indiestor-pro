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
        static function default_action($commandAction)
        {
                $rootFolder=dirname(dirname(dirname(dirname(__FILE__))));
                $version=trim(file_get_contents("$rootFolder/VERSION"));
                echo "$version\n";
        }
}

