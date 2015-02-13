<?php

/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class EtcWorkspaces
{
	var $confFilePath=null;	
        var $workspaces=null;

	//----------------------------------------------
	// CONSTRUCTOR
	//----------------------------------------------

	function __construct($workspaceType)
	{
                $this->confFilePath="/etc/indiestor-pro/$workspaceType-workspaces.conf";
		$conf=file_get_contents($this->confFilePath);
		$this->parse($conf);
	}

	//----------------------------------------------
	// PARSE
	//----------------------------------------------

        function parse($file)
        {
		$lines=explode("\n",$file);
		foreach($lines as $line)
		{
			if(strlen($line)>0)
			{
                                $this->workspaces[]=$line;
			}
		}
                
        } 

	//----------------------------------------------
	// SAVE
	//----------------------------------------------
        function save() 
        {
                $lines=join("\n",$this->workspaces);
                file_put_contents($this->confFilePath,$lines);
        }

}

