<?php

namespace Ensphere\ModuleComposer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Package\Link;
use Composer\Semver\VersionParser;
use STDclass;

class Plugin implements PluginInterface, EventSubscriberInterface
{

    protected $composer;
    protected $io;
    protected $base_path;
    protected $modules_file;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'pre-install-cmd'   => [ [ 'beforeComposerUpdate', 0 ] ],
            'pre-update-cmd'    => [ [ 'beforeComposerUpdate', 0 ] ]
        ];
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate( Composer $composer, IOInterface $io )
    {
        $this->composer         = $composer;
        $this->io               = $io;
        $this->base_path        = $this->composer->getConfig()->get( 'vendor-dir' ) . '/../';
        $this->modules_file     = $this->base_path . 'modules.json';
    }

    /**
     * @param Event $event
     */
    public function beforeComposerUpdate( Event $event )
    {

        $this->checkGitIgnorefile();

        $package        = $this->composer->getPackage();
        $modules        = $this->getModules();
        $requires       = $package->getRequires();
        $packageName    = $package->getPrettyName();

        foreach( $modules as $name => $version ) {
            $constraint = $this->getConstraintFromVersion( $version );
            $link = new Link( $packageName, $name, $constraint );
            $requires = array_merge( $requires, [ $name => $link ] );
        }

        $this->composer->getPackage()->setRequires( $requires );
    }

    /**
     * @param $version
     * @return \Composer\Semver\Constraint\MultiConstraint|mixed
     */
    protected function getConstraintFromVersion( $version )
    {
        $parser = new VersionParser;
        return $parser->parseConstraints( $version );
    }

    /**
     * @return mixed
     */
    protected function getModules()
    {
        if( ! file_exists( $this->modules_file ) ) {
            file_put_contents( $this->modules_file, json_encode( new STDclass, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES ) );
        }
        return json_decode( file_get_contents( $this->modules_file ) );
    }

    /**
     * @return bool
     */
    protected function checkGitIgnorefile()
    {
        $dataLines = explode( "\n", file_get_contents( $this->base_path . '.gitignore' ) );
        foreach( $dataLines as $line ) {
            $line = trim( $line );
            if( $line === 'modules.json' ) return true;
        }
        $dataLines[] = 'modules.json';
        file_put_contents( $this->base_path . '.gitignore', implode( "\n", $dataLines ) );
    }

}
