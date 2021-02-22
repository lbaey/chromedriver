<?php
namespace Lbaey\Composer;
/*
 * Compatibility helper for downloading files with composer plugin API v1 & v2
 * 
 * This file is part of chromedriver composer plugin.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Composer\Config;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Util\HttpDownloader;
use Composer\Util\RemoteFilesystem;

class Downloader 
{
    /**
     * @var RemoteFilesystem
     */
    private $remoteFileSystem;
    /**
     * @var HttpDownloader
     */
    private $httpDownloader;
    
    public function __construct(IOInterface $io, Config $config)
    {
        if(is_callable(array(Factory::class, 'createRemoteFilesystem'))) {
            $this->remoteFileSystem = Factory::createRemoteFilesystem($io, $config);
        } else {
            $this->httpDownloader = Factory::createHttpDownloader($io,$config);
        }
    }
    /**
     * @see HttpDownloader::get()
     * @param  string   $url     URL to download
     * @param  array    $options Stream context options e.g. https://www.php.net/manual/en/context.http.php
     *                           although not all options are supported when using the default curl downloader
     * @return string
     */
    public function get($url, $options = array()) {
        if($this->remoteFileSystem) {
            return $this->remoteFileSystem->getContents($url, $url, true, $options); 
        } else {
            $response = $this->httpDownloader->get($url,$options);
            return $response->getBody();
        }
    }
    /**
     * Copy a file synchronously
     * @see HttpDownloader::copy()
     * @param  string   $url     URL to download
     * @param  string   $to      Path to copy to
     * @param  array    $options Stream context options e.g. https://www.php.net/manual/en/context.http.php
     *                           although not all options are supported when using the default curl downloader
     * @return void
     */
    public function copy($url, $to, $options = array())
    {
        if($this->remoteFileSystem) {
            $this->remoteFileSystem->copy($url, $url, $to, true, $options);
        } else {
            $this->httpDownloader->copy($url, $to, $options);
        }
    }
}