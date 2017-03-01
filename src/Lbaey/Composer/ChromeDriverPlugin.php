<?php

namespace Lbaey\Composer;

/*
 * This file is part of chromedriver composer plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\Package;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;

/**
 * @author Laurent Baey <laurent.baey@gmail.com>
 */
class ChromeDriverPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallCmd',
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdateCmd',
        );
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Handle post install command events.
     *
     * @param Event $event The event to handle.
     *
     */
    public function onPostInstallCmd(Event $event)
    {
        $this->installDriver($event);
    }

    /**
     * Handle post update command events.
     *
     * @param Event $event The event to handle.
     *
     */
    public function onPostUpdateCmd(Event $event)
    {
        $this->installDriver($event);
    }

    /**
     * @param Event $event
     */
    protected function installDriver(Event $event)
    {
        if (stripos(PHP_OS, 'win') === 0) {
            $platform = 'Windows';
            $chromeDriverExecutableFileName = 'chromedriver.exe';
            $chromeDriverFileVersion = "win32";
        } elseif (stripos(PHP_OS, 'darwin') === 0) {
            $platform = 'Mac OS X';
            $chromeDriverExecutableFileName = 'chromedriver';
            $chromeDriverFileVersion = "mac64";
        } elseif (stripos(PHP_OS, 'linux') === 0) {
            $platform = 'Linux';
            $chromeDriverExecutableFileName = 'chromedriver';
            if (PHP_INT_SIZE === 8) {
                $chromeDriverFileVersion = "linux64";
            } else {
                $chromeDriverFileVersion = "linux32";
            }

        } else {
            $event->getIO()->writeError('Could not guess your platform, download chromedriver manually.');

            return;
        }

        /** @var Config $config */
        $config = $this->composer->getConfig();

        $extra = null;
        foreach($event->getComposer()->getRepositoryManager()->getLocalRepository()->findPackages('lbaey/chromedriver') as $package){
            if ($package instanceof CompletePackage){
                $extra = $package->getExtra();
                break;
            }
        }
        if ($extra) {
            $version = $extra['chromedriver_version'];
        } else {
            $version = '2.28';
        }
        $this->io->write(sprintf(
            "Downloading Chromedriver version %s for %s",
            $version,
            $platform
        ));
        $chromeDriverOriginUrl = "https://chromedriver.storage.googleapis.com";
        $chromeDriverRemoteFile = $version . "/chromedriver_" . $chromeDriverFileVersion . ".zip";
        $event->getIO()->write($chromeDriverOriginUrl . $chromeDriverRemoteFile);

        /** @var RemoteFilesystem $remoteFileSystem */
        $remoteFileSystem = Factory::createRemoteFilesystem($this->io, $config);

        $fs = new Filesystem();
        $fs->ensureDirectoryExists($config->get('bin-dir'));

        $chromeDriverArchiveFileName = $config->get('bin-dir') . DIRECTORY_SEPARATOR . 'chromedriver_' . $chromeDriverFileVersion . ".zip";
        $remoteFileSystem->copy($chromeDriverOriginUrl, $chromeDriverOriginUrl . '/' . $chromeDriverRemoteFile, $chromeDriverArchiveFileName);

        $archive = new \ZipArchive();
        $archive->open($chromeDriverArchiveFileName);
        $archive->extractTo($config->get('bin-dir'));

        if ($platform !== 'Windows') {
            chmod($config->get('bin-dir') . DIRECTORY_SEPARATOR . $chromeDriverExecutableFileName, 0755);
        }

        $fs->unlink($chromeDriverArchiveFileName);
    }
}
