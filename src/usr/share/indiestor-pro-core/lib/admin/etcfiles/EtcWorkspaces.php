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
                $this->workspaces=[];
                $this->confFilePath="/etc/indiestor-pro/$workspaceType-workspaces.conf";
		$conf=file_get_contents($this->confFilePath);
		$this->parse($conf);
	}

	//----------------------------------------------
	// ADD
	//----------------------------------------------
        function add($workspace,$path) {
                $path = rtrim($path, '/');
                $this->workspaces[$workspace]=$path;
        }

	//----------------------------------------------
	// REMOVE
	//----------------------------------------------
        function remove($workspace) {
                unset($this->workspaces[$workspace]);
        }

	//----------------------------------------------
	// PATH EXISTS
	//----------------------------------------------
        function pathExists($path) {
                foreach($this->workspaces as $workspace=>$existingPath) {
                        if($path==$existingPath) {
                                return true;
                        }
                }
                return false;
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
                                $fields=explode(":",$line);
                                $workspace=$fields[0];
                                $path=$fields[1];
                                $this->workspaces[$workspace]=$path;
			}
		}
                
        } 

	//----------------------------------------------
	// SAVE
	//----------------------------------------------
        function save() 
        {
                $lines=array();
                foreach($this->workspaces as $key=>$value) {
                        $lines[]="$key:$value";
                }
                $lines=join("\n",$lines);
                file_put_contents($this->confFilePath,$lines."\n");
        }

}

