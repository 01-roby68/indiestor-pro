<?php
/*
        Indiestor program
        Concept, requirements, specifications, and unit testing
        By Alex Gardiner, alex@indiestor.com
        Written by Erik Poupaert, erik@sankuru.biz
        Commissioned at peopleperhour.com 
        Licensed under the GPL
*/

requireLibFile('admin/args/ProgramOptions.php');

class ProcessInput
{
	var $command=null;
	var $stdin=null;
	var $workingDir=null;
	var $env=null;
}

class ProcessOutput
{
	var $stdout=null;
	var $stderr=null;
	var $returnCode=null;
}

class ShellCommand
{

	static function warnLongTime($command)
	{
		echo "running the '$command' command\n";
		echo "this may take a long time ...\n";
	}

	static function printCommand($command)
	{
		if(ProgramOptions::$verbose || ProgramOptions::$simulation)
		{
			echo "# $command\n";
		}
	}

	static function printProcessOutput($processOutput)
	{
		if(ProgramOptions::$verbose)
		{
			echo self::processOutputString($processOutput);
		}
	}

	static function processOutputString($processOutput)
	{
		$buffer=self::fieldString('>stdout',$processOutput->stdout);
		$buffer.=self::fieldString('>stderr',$processOutput->stderr);
		$buffer.=self::fieldString('return',$processOutput->returnCode);
		return $buffer;
	}

	static function fieldString($label,$value)
	{
		return $label.':'.$value."\n";
	}

	static function printStdErr($msg)
	{
		file_put_contents('php://stderr',$msg);
	}

	static function fail($command,$processOutput,$commandType)
	{
		self::printStdErr("ERR-$commandType: the following command failed\n");
		self::printStdErr("# $command\n");
		self::printStdErr(self::processOutputString($processOutput));
		exit(1);
	}

	static function exec_fail_if_error($command)
	{
		$processOutput=self::exec($command);
		if($processOutput==null) return; //simulation mode
		if($processOutput->returnCode!=0) self::fail($command,$processOutput,'EXEC');
	}

	static function exec_fail_with_message($command,$message)
	{
		$processOutput=self::exec($command);
		if($processOutput==null) return; //simulation mode
		if($processOutput->returnCode!=0) {
                        self::printStdErr($message);
        		exit(1);
                }
	}

	static function exec($command)
	{
		self::printCommand($command);
		if(!ProgramOptions::$simulation)
		{
			$processOutput=self::exec_default($command);
			return $processOutput;
		}
		//in case of simulation, nothing was actually done
		return null;
	}

	static function query_fail_if_error($command,$returnProcessOutputObject=false)
	{
		
		self::printCommand($command);
		$processOutput=self::exec_default($command);
		if($processOutput->returnCode!=0) self::fail($command,$processOutput,'QUERY');
		if($returnProcessOutputObject) return $processOutput;
		else return $processOutput->stdout;
	}

	static function query($command,$returnProcessOutputObject=false)
	{
		self::printCommand($command);
		$processOutput=self::exec_default($command);
		if($returnProcessOutputObject) return $processOutput;
		else return $processOutput->stdout;
	}

	static function exec_default($command)
	{

		$processInput=new ProcessInput();
		$processInput->command=$command;
		$processOutput=self::process($processInput);
		self::printProcessOutput($processOutput);
		return $processOutput;
	}

	static function process($processInput)
	{
		$descriptorSpec=array
				(
					0=>array('pipe', 'r'),
					1=>array('pipe', 'w'),
					2=>array('pipe', 'w')
				);

		$processOutput=new ProcessOutput();

                //a previous command may have deleted the user's own working directory
                if(getcwd()===FALSE)
                {
                        chdir(getenv('HOME'));
                        if(getcwd()===FALSE)
                                chdir('/');
                }

		//open process
		$process=proc_open($processInput->command, $descriptorSpec, $pipes,
					$processInput->workingDir,$processInput->env);

		//push to stdin
		if($processInput->stdin!=null) fwrite($pipes[0], $processInput->stdin);
		fclose($pipes[0]);

		//read from stdout and stderr
		$processOutput->stdout=stream_get_contents($pipes[1]);fclose($pipes[1]); 
		$processOutput->stderr=stream_get_contents($pipes[2]);fclose($pipes[2]); 

		//get return code
	        $processOutput->returnCode=proc_close($process); 

		return $processOutput;
	}
}

class ShellCommandCached
{
	const CACHE_FOLDER="/var/cache/indiestor-pro";

	static function execute($command,$hashFile) {
		$result=ShellCommand::query_fail_if_error($command);
		file_put_contents($hashFile,$result);
		return $result;
	}

	static function query_fail_if_error($command)
	{
		
		//make sure the cache folder exists
		ShellCommand::exec("mkdir -p ".self::CACHE_FOLDER);

		//commands are represented by their hash
		$hash=hash('sha256',$command);
		$hashFile=self::CACHE_FOLDER."/$hash";

		if(file_exists($hashFile)) {
			//hash file exists
			if(filemtime($hashFile)<time()-4*60*60) {

				//the hash file is stale, delete it
				unlink($hashFile); 
				//execute command
				return self::execute($command,$hashFile);

			} else {

				//the hash file is not yet stale, use its result
				return file_get_contents($hashFile);

			}
		} else {
			//hash file does not exist
			return self::execute($command,$hashFile);
		}
	}

}

