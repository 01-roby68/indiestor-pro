<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class Groups extends EntityType
{

	static function noMembers()
	{
		echo "no indiestor groups\n";
	}

        static function show($commandAction)
        {
                $etcGroup=EtcGroup::instance();
                if(ProgramActions::actionExists('json'))
                        self::showJSON($etcGroup->groups);
                else
                        self::showCLI($etcGroup->groups);
        }

        static function showCLI($groups)
        {
	        if(count($groups)==0) 
	        {
		        self::noMembers();
		        return;
	        }

                foreach($groups as $group)
                {
                        echo "$group->name\n";
                }
        }

        static function showJSON($groups)
        {
                echo json_encode_legacy($groups)."\n";
        }

        static function json($commandAction)
        {
                //handled by show command
                return;
        }

	static function startWatching($commandAction)
	{
		InotifyWait::startWatchingAll();
	}

	static function stopWatching($commandAction)
	{
		InotifyWait::stopWatchingAll();
	}

        static function statusWatching($commandAction)
        {
                if(InotifyWait::statusWatchingAll()==0)
                        echo "not running\n";
                else echo "running\n";
        }
}

