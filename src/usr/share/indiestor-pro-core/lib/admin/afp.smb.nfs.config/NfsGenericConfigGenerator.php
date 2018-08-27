<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class NfsGenericConfigGenerator
{
        function generate() 
        {
                $template=file_get_contents(dirname(__FILE__).'/nfs.generic');
                $buffer='';
                $wsconf=new EtcWorkspaces('generic');
                foreach($wsconf->workspaces as $workspace=>$path) {

                        //absolute path
                        if(substr($path,0,1)!=='/')
                                $pathAbs="/$path";
                        else $pathAbs=$path;      

                        //replace data in template
                        $patterns=[];
                        $patterns[]='/\{path\}/';
                        $replacements=[];
                        $replacements[]=$pathAbs;
                        $detailedConfig=preg_replace($patterns,$replacements,$template);
                        $buffer.=$detailedConfig;
                }
                return $buffer;
        }
}
