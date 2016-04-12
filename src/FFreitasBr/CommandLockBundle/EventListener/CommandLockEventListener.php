<?php

namespace FFreitasBr\CommandLockBundle\EventListener;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;
use FFreitasBr\CommandLockBundle\Exception\CommandAlreadyRunningException;
use FFreitasBr\CommandLockBundle\Traits\NamesDefinitionsTrait;

/**
 * Class CommandLockEventListener
 *
 * @package FFreitasBr\CommandLockBundle\EventListener
 */
class CommandLockEventListener extends ContainerAware
{
    use NamesDefinitionsTrait;

    /**
     * @var null|string
     */
    protected $pidDirectory = null;

    /**
     * @var array
     */
    protected $exceptionsList = array();

    /**
     * @var null|string
     */
    protected $pidFile = null;

    /**
     * @param ContainerInterface $container
     *
     * @return self
     */
    public function __construct(ContainerInterface $container)
    {
        // set container
        $this->setContainer($container);
        // get the pid directory and store in self
        $this->pidDirectory = $container->getParameter($this->configurationsParameterKey)[$this->pidDirectorySetting];
        // get the configured exceptions list
        $this->exceptionsList = $container->getParameter(
            $this->configurationsParameterKey
        )[$this->exceptionsListSetting];
    }

    /**
     * @param ConsoleCommandEvent $event
     *
     * @return void
     * @throws CommandAlreadyRunningException
     */
    public function onConsoleCommand(ConsoleCommandEvent $event)
    {
        // generate pid file name
        $commandName = $event->getCommand()->getName();
        // check for exceptions
        if (in_array($commandName, $this->exceptionsList)) {
            return;
        }
        $clearedCommandName = $this->cleanString($commandName);
        $pidFile = $this->pidFile = $this->pidDirectory . "/{$clearedCommandName}.pid";
        // check if command is already executing
        if (file_exists($pidFile)) {
            $pidOfRunningCommand = file_get_contents($pidFile);
            $elements = explode(":", $pidOfRunningCommand);
            
            if ($elements[0] == gethostname())
            {
                if (posix_getpgid($elements[1]) !== false) {
                    throw (new CommandAlreadyRunningException)
                        ->setCommandName($commandName)
                        ->setPidNumber($pidOfRunningCommand);
                }else{
                    // pid file exist but the process is not running anymore
                    unlink($pidFile);
                }
            }else{
                throw (new CommandAlreadyRunningException)
                    ->setCommandName($commandName)
                    ->setPidNumber($pidOfRunningCommand);
            }

        }
        // if is not already executing create pid file
        //file_put_contents($pidFile, getmypid());

        // Añadimos hostname para verificar desde que frontal se estan ejecutando
        $string = gethostname().":".getmypid();
        file_put_contents($pidFile, $string);
        // register shutdown function to remove pid file in case of unexpected exit
        register_shutdown_function(array($this, 'shutDown'), null, $pidFile);
    }

    /**
     * @param ConsoleTerminateEvent $event
     *
     * @return void
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event)
    {
        if (isset($this->pidFile) && file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
    }

    /**
     * @param $string
     *
     * @return mixed
     */
    public function cleanString($string)
    {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
    }

    /**
     * @param null|int $pidFile
     */
    public function shutDown($pidFile = null)
    {
        if (!isset($pidFile) && isset($this->pidFile)) {
            $pidFile = $this->pidFile;
        }
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
    }
}
