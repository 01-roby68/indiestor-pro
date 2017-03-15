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
                        $cachePath="/var/cache/indiestor-pro/".$workspace;

                        // trigger background stat refresh
                        ActionEngine::forkStatsChildProgram();

                        // quota eval
                        $row['zfs-quota']='n/a';
                        if(substr($path,0,1)!=='/') {
                        $getZQuota=trim(ShellCommand::query("zfs get quota -Hp  -o value $path "));
                        
                                if ($getZQuota > 0){
                                $quota=($getZQuota / 1073741824)."G";
                                }else{
                                $quota='none';
                                }
                                $row['zfs-quota']=$quota;
                        }

                        // get used space from record cache
                        if (file_exists($cachePath."-used")) {
                        $spaceUsed=trim(file_get_contents($cachePath."-used"));
                        }
                        else{
                        $spaceUsed="-";
                        }

                        $row['space-used']=$spaceUsed;

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
                                $format1="%-20s %-30s %-20s %-15s %-30s %-10s\n";
                                $format2="%-20s %-30s %-20s %-15s %-30s %-10s\n";
                                printf($format1,'workspace','path','used','zfs-quota','members','watching');
                                foreach(self::avidWorkspaceData() as $row) {
                                        printf($format2,$row['workspace'],$row['path'],$row['space-used'],$row['zfs-quota'],$row['members'],$row['watching']);
                                }
                        }
                }                        
        }
}

