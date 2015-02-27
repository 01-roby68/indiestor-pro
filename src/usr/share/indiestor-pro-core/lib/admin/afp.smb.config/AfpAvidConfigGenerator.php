<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class AfpAvidConfigGenerator
{
        function generate() 
        {
                $template=file_get_contents(dirname(__FILE__).'/afp.avid');
                $buffer='';
                $wsconf=new EtcWorkspaces('avid');
                foreach($wsconf->workspaces as $workspace=>$path) {
                        //group name
                        $groupName='avid_'.$workspace;

                        //absolute path
                        if(substr($path,0,1)!=='/')
                                $pathAbs="/$path";
                        else $pathAbs=$path;

                        //replace data in template
                        $patterns=[];
                        $patterns[]='/\{workspace\}/';
                        $patterns[]='/\{path\}/';
                        $patterns[]='/\{group\}/';
                        $replacements=[];
                        $replacements[]=$workspace;
                        $replacements[]=$pathAbs;
                        $replacements[]=$groupName;
                        $detailedConfig=preg_replace($patterns,$replacements,$template);
                        $buffer.=$detailedConfig;
                }
                return $buffer;
        }
}

