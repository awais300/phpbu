<?php
namespace phpbu\Backup\Sync;

use phpbu\App\Result;
use phpbu\Backup\Sync;
use phpbu\Backup\Target;
use phpbu\Util\Arr;
use phpbu\Util\String;
use ObjectStorage_Http_Client;
use ObjectStorage;

/**
 * SoftLayer  ObjectStorage Sync
 *
 * @package    phpbu
 * @subpackage Backup
 * @author     Petr Cervenka <petr@nanosolutions.io>
 * @author     Sebastian Feldmann <sebastian@phpbu.de>
 * @copyright  Sebastian Feldmann <sebastian@phpbu.de>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link       http://phpbu.de/
 * @since      Class available since Release 1.1.6
 */
class SoftLayer implements Sync
{
    /**
     * SoftLayer username
     *
     * @var  string
     */
    protected $username;

    /**
     * SoftLayer secret
     *
     * @var  string
     */
    protected $secret;

    /**
     * SoftLayer container
     *
     * @var string
     */
    protected $container;

    /**
     * SoftLayer host
     *
     * @var string
     */
    protected $host;

    /**
     * SoftLayer remote path
     *
     * @var string
     */
    protected $path;

    /**
     * (non-PHPDoc)
     *
     * @see    \phpbu\Backup\Sync::setup()
     * @param  array $config
     * @throws \phpbu\Backup\Sync\Exception
     */
    public function setup(array $config)
    {
        if (!class_exists('\\ObjectStorage')) {
            throw new Exception('SoftLayer SDK not loaded: use composer "softlayer/objectstorage": "dev-master" to install');
        }
        if (!Arr::isSetAndNotEmptyString($config, 'username')) {
            throw new Exception('SoftLayer username is mandatory');
        }
        if (!Arr::isSetAndNotEmptyString($config, 'secret')) {
            throw new Exception('SoftLayer password is mandatory');
        }
        if (!Arr::isSetAndNotEmptyString($config, 'container')) {
            throw new Exception('SoftLayer container name is mandatory');
        }
        if (!Arr::isSetAndNotEmptyString($config, 'host')) {
            throw new Exception('SoftLayer host is mandatory');
        }
        if (!Arr::isSetAndNotEmptyString($config, 'path')) {
            throw new Exception('SoftLayer path is mandatory');
        }
        $this->username  = $config['username'];
        $this->secret    = $config['secret'];
        $this->container = $config['container'];
        $this->host      = $config['host'];
        $this->path      = String::withTrailingSlash(String::replaceDatePlaceholders($config['path']));
    }

    /**
     * Execute the sync.
     *
     * @see    \phpbu\Backup\Sync::sync()
     * @param  \phpbu\backup\Target $target
     * @param  \phpbu\App\Result    $result
     * @throws \phpbu\Backup\Sync\Exception
     */
    public function sync(Target $target, Result $result)
    {
        $sourcePath = $target->getPathnameCompressed();
        $targetPath = $this->path . $target->getFilenameCompressed();

        $options       = array('adapter' => ObjectStorage_Http_Client::SOCKET, 'timeout' => 20);
        $objectStorage = new ObjectStorage($this->host, $this->username, $this->secret, $options);

        try {
            /** @var \ObjectStorage_Container $object */
            $container = $objectStorage->with($this->container . '/' . $targetPath)
                                       ->setLocalFile($sourcePath)
                                       ->setMeta('description', 'PHPBU Backup: ' . date('r',time()))
                                       ->setHeader('Content-Type', $target->getMimeType());
            $container->create();
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }

        $result->debug('upload: done');
    }
}
