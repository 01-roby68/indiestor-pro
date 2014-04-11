<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

class Services extends EntityType
{
        static function startSamba($commandAction)
        {
                self::upstartServiceAction('samba','start');
        }

        static function stopSamba($commandAction)
        {
                self::upstartServiceAction('samba','stop');
        }

        static function startIncron($commandAction)
        {
                self::upstartServiceAction('incron','start');
        }

        static function stopIncron($commandAction)
        {
                self::upstartServiceAction('incron','stop');
        }

        static function startNetatalk($commandAction)
        {
                self::upstartServiceAction('netatalk','start');
        }

        static function stopNetatalk($commandAction)
        {
                self::upstartServiceAction('netatalk','stop');
        }

        static function findSambaServiceName()
        {
                $stdout=ShellCommand::query("uname -a");
                if(preg_match('/Ubuntu/',$stdout)) return 'smbd';
                else return 'samba';
        }

        static function upstartServiceAction($serviceName,$action)
        {
                if($serviceName=='samba')
                        $serviceName=self::findSambaServiceName();
                ShellCommand::exec_fail_if_error("service $serviceName $action");
        }

        static function upstartServiceStatus($serviceName)
        {
                if($serviceName=='samba')
                        $serviceName=self::findSambaServiceName();
                $stdout=ShellCommand::query("service $serviceName status");
                if(preg_match('/not|stop/',$stdout)) return false;
                else return true;
        }

        static function netatalkServiceStatus($serviceName)
        {
                $stdout=ShellCommand::query("ps aux | grep afpd");
                if(preg_match("afpd",$stdout)) return true;
                else return false;
        }

        static function status()
        {
                $status=array();
                $status['samba']=self::upstartServiceStatus('samba');
                $status['incron']=self::upstartServiceStatus('incron');
                $status['incron']=self::netatalkServiceStatus('incron');
                $countPids=InotifyWait::statusWatchingAll();
                if($countPids>0)
                        $status['watching']=true;
                else    $status['watching']=false;
                return $status;
        }

        static function show($commandAction)
        {
                if(ProgramActions::actionExists('json'))
                        self::showJSON();
                else
                        self::showCLI();
        }

        static function showCLI()
        {
                $status=self::status();
                foreach($status as $service=>$serviceStatus) 
                {
                        if($serviceStatus) $situation='running';
                        else $situation='not running';
                        echo "$service $situation\n";
                }                                
        }

        static function showJSON()
        {
                echo json_encode_legacy(self::status())."\n";
        }

        static function json($commandAction)
        {
                //handled by show command
                return;
        }
}
