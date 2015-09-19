<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class AvidWorkspaces extends EntityType
{

        static function json($commandAction)
        {
                //handled by show command
                return;
        }

        static function avidWorkspaceData()
        {
                $conf=new EtcWorkspaces('avid');
                $rows=[];
                foreach($conf->workspaces as $workspace=>$path) {
                        $row=[];
                        $row['workspace']=$workspace;
                        $row['path']=$path;

                        //quota
                        $row['zfs-quota']='-';
                        if(substr($path,0,1)!=='/') {
                                $row['zfs-quota']=trim(ShellCommand::query("zfs get quota -H  -o value $path"));
                        }

                        //space used
                        if(substr($path,0,1)!=='/')
                                $pathAbs="/$path";
                        else $pathAbs=$path;
                        $row['space-used']=trim(ShellCommandCached::query("du -h --max-depth=0 $pathAbs | awk '{print $1}'"));

                        $row['avail']=trim(ShellCommandCached::query_fail_if_error(
				"df -h $pathAbs | tail -n +2 | awk '{ print  $2 }' "));	

                        //group members
                        $groupName='avid_'.$workspace;                        
                        $etcGroup=EtcGroup::instance();
                        $group=$etcGroup->findGroup($groupName);                
                        if($group!==null) {
                                $row['members']=join(",",$group->members);
                        }

                        //watching
                        if(InotifyWait::statusWatching($workspace)) $watching='Y';
                        else $watching='N';
                        $row['watching']=$watching;

                        //add record
                        $rows[]=$row;
                }
                return $rows;
        }

        static function show($commandAction)
        {
                $conf=new EtcWorkspaces('avid');
                if(ProgramActions::actionExists('json')) {
                        echo json_encode_legacy(self::avidWorkspaceData())."\n";
                } else {
                        if(count($conf->workspaces)===0) {
                                echo "no avid workspaces.\n";
                        } else {
                                $format1="%-20s %-30s %10s %10s %10s %-30s %10s\n";
                                $format2="%-20s %-30s %10s %10s %10s %-30s %10s\n";
                                printf($format1,'workspace','path','zfs-quota','used','avail','members','watching');
                                foreach(self::avidWorkspaceData() as $row) {
                                        printf($format2,$row['workspace'],$row['path'],$row['zfs-quota'],
                                                $row['space-used'],$row['avail'],$row['members'],$row['watching']);
                                }
                        }
                }                        
        }
}

