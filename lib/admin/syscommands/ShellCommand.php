<?php
/*
        Indiestor program

	Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        By Alex Gardiner, alex.gardiner@canterbury.ac.uk
*/

class ShellCommand
{
	function exec($command)
	{
		if(ProgramOptions::$simulation || ProgramOptions::$verbose)
		{
			echo "-exec-> $command\n";
		}
		if(!ProgramOptions::$verbose) $command=$command.' 2>&1';
		if(!ProgramOptions::$simulation)
		{
			$output=shell_exec($command);
		}
		if(ProgramOptions::$simulation || ProgramOptions::$verbose)
		{
			echo "-output--> $output";
		}
	}
}
