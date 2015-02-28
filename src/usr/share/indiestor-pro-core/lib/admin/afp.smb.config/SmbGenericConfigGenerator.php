<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class SmbGenericConfigGenerator
{
        function generate() 
        {
                $template=file_get_contents(dirname(__FILE__).'/smb.generic');
                $buffer='';
                $wsconf=new EtcWorkspaces('generic');
                foreach($wsconf->workspaces as $workspace=>$path) {
                        //group names
                        $rwGroupName='generic_rw_'.$workspace;
                        $roGroupName='generic_ro_'.$workspace;

                        //absolute path
                        if(substr($path,0,1)!=='/')
                                $pathAbs="/$path";
                        else $pathAbs=$path;

                        $etcGroup=EtcGroup::instance();

                        $rwGroup=$etcGroup->findGroup($rwGroupName);    
                        if($rwGroup===null ) $rwList='';
                        else if($rwGroup->members===null) $rwList='';
                        else $rwList=join(',',$rwGroup->members);           

                        $roGroup=$etcGroup->findGroup($roGroupName); 
                        if($roGroup===null) $roList='';
                        else if($roGroup->members===null) $roList='';
                        else $roList=join(',',$roGroup->members);           

                        //replace data in template
                        $patterns=[];
                        $patterns[]='/\{workspace\}/';
                        $patterns[]='/\{path\}/';
                        $patterns[]='/\{rolist\}/';
                        $patterns[]='/\{rwlist\}/';
                        $patterns[]='/\{rwgroup\}/';
                        $replacements=[];
                        $replacements[]=$workspace;
                        $replacements[]=$pathAbs;
                        $replacements[]=$roList;
                        $replacements[]=$rwList;
                        $replacements[]='@'.$rwGroupName;
                        $detailedConfig=preg_replace($patterns,$replacements,$template);
                        $buffer.=$detailedConfig;
                }
                return $buffer;
        }
}

