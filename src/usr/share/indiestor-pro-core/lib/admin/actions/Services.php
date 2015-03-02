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
                sleep(1);
                /* incron may refuse to stop on ubuntu precise */
                $pid=ShellCommand::query("ps ax | grep incron | head -n1 | awk '{ print $1}'");
                if($pid!='') ShellCommand::exec("kill -9 $pid");
        }

        static function startNetatalk($commandAction)
        {
                self::upstartServiceAction('netatalk','start');
                sleep(4);
        }

        static function stopNetatalk($commandAction)
        {
                self::upstartServiceAction('netatalk','stop');
                sleep(4);
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
                ShellCommand::exec("service $serviceName $action");
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
                $stdout=ShellCommand::query("ps aux | grep afp");
                if(preg_match("/afpd/",$stdout)) return true;
                else return false;
        }

        static function zfsServiceStatus($serviceName)
        {
                $zfsinstalled = shell_exec("dpkg-query -l | grep zfs-dkms | wc -l");
                $zpoolhealth = shell_exec("zpool status -x");
                if($zfsinstalled == 0) return "Not Installed";
                elseif (preg_match("/healthy/",$zpoolhealth)) return "Healthy";
                else return "ERROR!";
        }

        static function status()
        {
                {
                $status=array();
                $status['samba']=self::upstartServiceStatus('samba');
                $status['incron']=self::upstartServiceStatus('incron');
                $status['netatalk']=self::netatalkServiceStatus('netatalk');
                $status['zfs']=self::zfsServiceStatus('zfs');
                $countPids=InotifyWait::statusWatchingAll();
                if($countPids>0)
                        $status['watching']=true;
                else    $status['watching']=false;
                return $status;
                }

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
                        if ($service == "zfs")
                        {
                                echo "$service $serviceStatus\n";
                        }
                        elseif ($serviceStatus == 1)
                        {
                                echo "$service running\n";
                        }
                        else
                                echo "$service not running\n";
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

	static function startWatching($commandAction)
	{
        	ShellCommand::exec_fail_if_error("incrontab -u indienotify --remove");
        	ShellCommand::exec_fail_if_error("echo /var/spool/indiestor-pro ".
                        "IN_CREATE /usr/bin/indiestor-pro-inotify | incrontab -u indienotify -");
		InotifyWait::startWatchingAll();
	}

	static function stopWatching($commandAction)
	{
        	ShellCommand::exec_fail_if_error("incrontab -u indienotify --remove");
		InotifyWait::stopWatchingAll();
	}

}
