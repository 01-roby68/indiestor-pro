<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class User extends EntityType
{

	static function validateUpFront()
	{
	}

        static function add($commandAction)
        {
                echo "to be implemented\n";
        }

        static function delete($commandAction)
        {
                echo "to be implemented\n";
        }

	static function setPasswd($commandAction)
	{
                echo "to be implemented\n";
	}

	static function pkill($commandAction)
	{
                echo "to be implemented\n";
	}


	static function afterCommand()
	{
	}


}

