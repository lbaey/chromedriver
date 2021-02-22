<?php

namespace Lbaey\Composer;

/*
 * This file is part of chromedriver composer plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Composer\Cache;
use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;
use Composer\Util\ProcessExecutor;

/**
 * @author Laurent Baey <laurent.baey@gmail.com>
 */
class ChromeDriverPlugin implements PluginInterface, EventSubscriberInterface
{
    const LINUX32 = 'linux32';
    const LINUX64 = 'linux64';
    const MAC64 = 'mac64';
    const WIN32 = 'win32';

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var string
     */
    protected $platform;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var Config\
     */
    protected $config;

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
        $this->config = $this->composer->getConfig();
        $this->cache = new Cache(
            $this->io,
            implode(DIRECTORY_SEPARATOR, [
                $this->config->get('cache-dir'),
                'files',
                'lbaey-chromedriver',
                'downloaded-bin'
            ])
        );
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
        $extra = null;
        $extra = $this->composer->getPackage()->getExtra();

        $chromeDriverOriginUrl = "https://chromedriver.storage.googleapis.com";
        
        /** @var RemoteFilesystem $remoteFileSystem */
        $remoteFileSystem = Factory::createRemoteFilesystem($this->io, $this->config);
        
        if (empty($extra['lbaey/chromedriver']['chromedriver-version'])) {
            $version = $remoteFileSystem->getContents(
            	$chromeDriverOriginUrl,
            	$chromeDriverOriginUrl . '/LATEST_RELEASE' 
            );
        } else {
            $version = $extra['lbaey/chromedriver']['chromedriver-version'];
        }

        $this->guessPlatform();

        $chromeDriverPath = $this->config->get('bin-dir') . DIRECTORY_SEPARATOR . $this->getExecutableFileName();
        $output = '';

        if (file_exists($chromeDriverPath) && is_executable($chromeDriverPath)) {
            $processExecutor = new ProcessExecutor($this->io);

            // Temporarily reduce (?) the timeout to 10 seconds.
            $originalTimeout = $processExecutor::getTimeout();
            $processExecutor::setTimeout(10);

            $processExecutor->execute($chromeDriverPath . ' --version', $output);

            // Restore the timeout.
            $processExecutor::setTimeout($originalTimeout);

            // right version? => nothing to do
            if (strpos($output, 'ChromeDriver ' . $version) === 0) {
                $this->io->write(sprintf('The right version %s of ChromeDriver is already installed', $version));
                return;
            }
        }

        $fs = new Filesystem();
        $fs->ensureDirectoryExists($this->cache->getRoot() . $version);
        $fs->ensureDirectoryExists($this->config->get('bin-dir'));

        $chromeDriverArchiveCacheFileName = $this->cache->getRoot() . $version . DIRECTORY_SEPARATOR . $this->getRemoteFileName();

        if (!$this->cache->isEnabled() || !file_exists($chromeDriverArchiveCacheFileName)) {
            $this->io->write(sprintf(
                "Downloading ChromeDriver version %s for %s",
                $version,
                $this->getPlatformNames()[$this->platform]
            ));
            

            
            $remoteFileSystem->copy(
                $chromeDriverOriginUrl,
                $chromeDriverOriginUrl . '/' . $version . '/' . $this->getRemoteFileName(),
                $chromeDriverArchiveCacheFileName
            );
        } else {
            $this->io->write(sprintf(
                'Using cached version of %s',
                $this->getRemoteFileName()
            ));
        }

        $archive = new \ZipArchive();
        $archive->open($chromeDriverArchiveCacheFileName);
        $archive->extractTo($this->config->get('bin-dir'));

        if ($this->platform !== self::WIN32) {
            chmod($this->config->get('bin-dir') . DIRECTORY_SEPARATOR . $this->getExecutableFileName(), 0755);
        }

    }

    /**
     *
     */
    protected function guessPlatform()
    {
        if (stripos(PHP_OS, 'win') === 0) {
            $this->platform = self::WIN32;
        } elseif (stripos(PHP_OS, 'darwin') === 0) {
            $this->platform = self::MAC64;
        } elseif (stripos(PHP_OS, 'linux') === 0) {

            if (PHP_INT_SIZE === 8) {
                $this->platform = self::LINUX64;
            } else {
                $this->platform = self::LINUX32;
            }

        } else {
            $this->io->writeError('Could not guess your platform, download chromedriver manually.');

            return;
        }

        $extra = $this->composer->getPackage()->getExtra();

        if (empty($extra['lbaey/chromedriver']['bypass-select'])) {
            $this->platform = $this->io->select('Please select the platform :', $this->getPlatformNames(), $this->platform);
        }
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function getRemoteFileName()
    {
        switch ($this->platform) {
            case self::LINUX32:
                return "chromedriver_linux32.zip";
            case self::LINUX64:
                return "chromedriver_linux64.zip";
            case self::MAC64:
                return "chromedriver_mac64.zip";
            case self::WIN32:
                return "chromedriver_win32.zip";
            default:
                throw new \Exception('Platform is not set.');
        }
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function getExecutableFileName()
    {
        switch ($this->platform) {
            case self::LINUX32:
            case self::LINUX64:
            case self::MAC64:
                return 'chromedriver';
            case self::WIN32:
                return 'chromedriver.exe';
            default:
                throw new \Exception('Platform is not set.');
        }
    }

    /**
     * @return array
     */
    protected function getPlatformNames()
    {
        return [
            self::LINUX32 => 'Linux 32Bits',
            self::LINUX64 => 'Linux 64Bits',
            self::MAC64 => 'Mac OS X',
            self::WIN32 => 'Windows'
        ];
    }
}
