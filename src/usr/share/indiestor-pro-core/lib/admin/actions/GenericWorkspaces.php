<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class GenericWorkspaces extends EntityType
{
        static function json($commandAction)
        {
                //handled by show command
                return;
        }

        static function genericWorkspaceData()
        {
                $conf=new EtcWorkspaces('generic');
                $rows=[];
                foreach($conf->workspaces as $workspace=>$path) {
                        $row=[];
                        $row['workspace']=$workspace;
                        $row['path']=$path;

                        //quota
                        $row['zfs-quota']='';
                        if(substr($path,0,1)!=='/') {
                                $row['zfs-quota']=trim(ShellCommand::query("zfs get quota -H  -o value $path"));
                        }

                        //space used
                        if(substr($path,0,1)!=='/')
                                $pathAbs="/$path";
                        else $pathAbs=$path;
                        $row['space-used']=trim(ShellCommand::query("du -h --max-depth=0 $pathAbs | awk '{print $1}'"));

                        //avail
                        $row['avail']='';
                        if(substr($path,0,1)!=='/') {
                                $row['avail']=trim(ShellCommand::query("zfs get avail -H  -o value $path"));
                                if($row['avail']=='') $row['avail']='-';
                        } else {
	                        $row['avail']=trim(ShellCommand::query_fail_if_error("df -h $pathAbs | tail -n +2 | awk '{ print  $2 }' "));	
                        }

                        //read/write group members
                        $rwGroupName='generic_rw_'.$workspace;
                        $roGroupName='generic_ro_'.$workspace;

                        $etcGroup=EtcGroup::instance();
                        $rwGroup=$etcGroup->findGroup($rwGroupName);                
                        if($rwGroup!==null) {
                                $row['write-members']=join(",",$rwGroup->members);
                        } else {
                                $row['write-members']='';
                        }
                        $roGroup=$etcGroup->findGroup($roGroupName);                
                        if($roGroup!==null) {
                                $row['read-members']=join(",",$roGroup->members);
                        } else {
                                $row['read-members']='';
                        }

                        //add record
                        $rows[]=$row;
                }
                return $rows;
        }


        static function show($commandAction)
        {
                $conf=new EtcWorkspaces('generic');
                if(ProgramActions::actionExists('json')) {
                        echo json_encode_legacy(self::genericWorkspaceData())."\n";
                } else {
                        if(count($conf->workspaces)===0) {
                                echo "no generic workspaces.\n";
                        } else {
                                $format1="%-20s %-30s %10s %10s %10s %-30s %30s\n";
                                $format2="%-20s %-30s %10s %10s %10s %-30s %30s\n";
                                printf($format1,'workspace','path','zfs-quota','space','avail','write-members','read-members');
                                foreach(self::genericWorkspaceData() as $row) {
                                        printf($format2,$row['workspace'],$row['path'],$row['zfs-quota'],
                                                $row['space-used'],$row['avail'],$row['write-members'],$row['read-members']);
                                }
                        }
                }                        
        }
}

