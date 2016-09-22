<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class Users extends EntityType
{

        static function getProcessData($executable)
        {
                return explode("\n",trim(ShellCommand::query('ps aux | awk \'{print $1,$11}\' | grep '.
                                        $executable.' | awk \'{print $1}\' | uniq')));
        }

        static function isUserActiveInProcess($userName,$activeUserArray)
        {
                $isActive=false;
                foreach($activeUserArray as $activeUserName) {
                        if($userName===$activeUserName) return true;
                }
                return false;
        }

        static function isUserActive($userName,$activeUserArrays)
        {
                $isActive='N';
                foreach($activeUserArrays as $activeUserArray) {
                        if(self::isUserActiveInProcess($userName,$activeUserArray))
                                $isActive='Y';
                }
                return $isActive;
        }

        static function getUserData() 
        {

                $etcGroup=EtcGroup::instance();
		$indiestorGroupMembers=$etcGroup->indiestorGroup->members;
                $smbdUsers=self::getProcessData('smbd');
                $sambaUsers=self::getProcessData('samba');
                $afpdUsers=self::getProcessData('afpd');

                $report=[];
                foreach($indiestorGroupMembers as $member) {
                        $reportLine=[];
                        $reportLine['username']=$member;
                        $reportLine['active']=self::isUserActive($member,[$smbdUsers,$sambaUsers,$afpdUsers]);
                        $report[]=$reportLine;
                }
                return $report;
        }

        static function json($commandAction)
        {
                //handled by show command
                return;
        }

        static function show($commandAction)
        {
                $report=self::getUserData();
                if(ProgramActions::actionExists('json')) {
                        echo json_encode_legacy($report)."\n";
                } else {
                        if(count($report)===0) echo "no users\n";
                        else {
                                $format1="%-20s %-10s\n";
                                $format2="%-20s %-10s\n";
                                printf($format1,'user','active');
                                foreach($report as $row) {
                                        printf($format2,$row['username'],$row['active']);
                                }

                        }
                }

        }

}

