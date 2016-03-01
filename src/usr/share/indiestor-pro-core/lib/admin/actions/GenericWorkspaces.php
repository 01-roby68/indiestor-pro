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


                        // get free space from record cache
                        if (file_exists($cachePath."-free")) {
                        $spaceFree=trim(file_get_contents($cachePath."-free"));
                        }
                        else{
                        $spaceFree="-";
                        }

                        $row['free']=$spaceFree;


                        // get total space from record cache
                        if (file_exists($cachePath."-total")) {
                        $spaceTotal=trim(file_get_contents($cachePath."-total"));
                        }
                        else{
                        $spaceTotal="-";
                        }

                        $row['total']=$spaceTotal;


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
                                $format1="%-20s %-30s %10s %10s %10s %10s %-30s %30s\n";
                                $format2="%-20s %-30s %10s %10s %10s %10s %-30s %30s\n";
                                printf($format1,'workspace','path','zfs-quota','used','free', 'total','write-members','read-members');
                                foreach(self::genericWorkspaceData() as $row) {
                                        printf($format2,$row['workspace'],$row['path'],$row['zfs-quota'],
                                                $row['space-used'],$row['free'],$row['total'],$row['write-members'],$row['read-members']);
                                }
                        }
                }                        
        }
}

