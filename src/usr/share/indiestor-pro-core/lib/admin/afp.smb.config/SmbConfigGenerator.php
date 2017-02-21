<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class SmbConfigGenerator
{

        function generateSmbConfigForUser($user) {
                $avidConfig=self::generateSmbAvidConfigForUser($user);
                $genericConfig=self::generateSmbGenericConfigForUser($user);
                if($avidConfig!='') {
                        $headerAvid ="# ==========================\n";
                        $headerAvid.="# Avid workspaces\n";
                        $headerAvid.="# ==========================\n";
                        $avidConfig=$headerAvid.$avidConfig;
                }
                if($genericConfig!='') {
                        $headerGeneric ="# ==========================\n";
                        $headerGeneric.="# Generic workspaces\n";
                        $headerGeneric.="# ==========================\n";
                        $genericConfig=$headerGeneric.$genericConfig;
                }
                $buffer=$avidConfig.$genericConfig;
                if($buffer!='') {
                        file_put_contents("/etc/indiestor-pro/samba/smb.$user.conf",$buffer);                
                }
        }

        function generateSmbAvidConfigForUser($user) {
                $template=file_get_contents(dirname(__FILE__).'/smb.avid');
                $buffer='';
                $wsconf=new EtcWorkspaces('avid');
                foreach($wsconf->workspaces as $workspace=>$path) {
                        //group name
                        $groupName='avid_'.$workspace;
                        if(EtcGroup::instance()->findGroup($groupName)->isMember($user))
                        {
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
                                $replacements[]='@'.$groupName;
                                $detailedConfig=preg_replace($patterns,$replacements,$template);
                                $buffer.=$detailedConfig;
                        } 
                
                }
                return $buffer;
        }

        function generateSmbGenericConfigForUser($user) {
                $template=file_get_contents(dirname(__FILE__).'/smb.generic');
                $buffer='';
                $wsconf=new EtcWorkspaces('generic');
                foreach($wsconf->workspaces as $workspace=>$path) {
                        //group names
                        $rwGroupName='generic_rw_'.$workspace;
                        $roGroupName='generic_ro_'.$workspace;
                        if(EtcGroup::instance()->findGroup($rwGroupName)->isMember($user) ||
                           EtcGroup::instance()->findGroup($roGroupName)->isMember($user) )
                        {
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
                                $replacements[]='@'.$rwGroupName. ", ".'@'.$roGroupName;
                                $detailedConfig=preg_replace($patterns,$replacements,$template);
                                $buffer.=$detailedConfig;
                        }

                }
                return $buffer;
        }


        function generate() 
        {
                shell_exec("mkdir -p /etc/indiestor-pro/samba; rm -f /etc/indiestor-pro/samba/*");
                $users=EtcGroup::instance()->findGroup(ActionEngine::indiestorUserGroup)->members;
                foreach($users as $user) {
                        self::generateSmbConfigForUser($user);
                }

        }
}

