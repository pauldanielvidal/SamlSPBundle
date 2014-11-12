<?php

namespace AerialShip\SamlSPBundle\Config;

use AerialShip\LightSaml\Model\Metadata\EntitiesDescriptor;
use AerialShip\LightSaml\Model\Metadata\EntityDescriptor;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Filesystem\Filesystem;


class EntityDescriptorFileProvider implements EntityDescriptorProviderInterface
{
    /** @var  KernelInterface */
    protected $kernel;

    /** @var  string */
    protected $filename;

    /** @var  string|null */
    protected $entityId;

    /** @var  EntityDescriptor|null */
    private $entityDescriptor;



    function __construct(KernelInterface $kernel) {
        $this->kernel = $kernel;
    }


    /**
     * @param string $filename
     * @throws \InvalidArgumentException
     */
    public function setFilename($filename) {
        if (stripos($filename, 'http') !== false) {
            $filename = $this->getSavedFileFromUrl($filename);
        }
        if ($filename && $filename[0] == '@') {
            $filename = $this->kernel->locateResource($filename);
        }
        if (!is_file($filename)) {
            throw new \InvalidArgumentException('Specified file does not exist: '.$filename);
        }
        $this->filename = $filename;
    }

    /**
     * @return string
     */
    public function getFilename() {
        return $this->filename;
    }

    /**
     * @param null|string $entityId
     */
    public function setEntityId($entityId)
    {
        $this->entityId = $entityId;
    }

    /**
     * @return null|string
     */
    public function getEntityId()
    {
        return $this->entityId;
    }




    /**
     * @return EntityDescriptor
     */
    public function getEntityDescriptor() {
        if ($this->entityDescriptor === null) {
            $this->load();
        }
        return $this->entityDescriptor;
    }


    protected function getSavedFileFromUrl($url)
    {
        $fs = new Filesystem();
        $cacheDir = $this->kernel->getCacheDir();

        // cache the file for one day
        $filename = sprintf('%s/%s.file', $cacheDir, preg_replace("/((\W+)|\W)/", "-", $url));
        $isFileValid = false;
        $minTimestamp = new \DateTime();
        $minTimestamp->modify('-1 day');

        if ($fs->exists($filename)) {
            $fileTimestamp = new \DateTime();
            $fileTimestamp->setTimestamp(filemtime($filename));
            // set isFileValid = true if one day has passed
            if ($minTimestamp <= $fileTimestamp) {
                $isFileValid = true;
            }
        }

        // get and dump file if not exists or one day passed
        if (!$isFileValid) {
            $temp = $this->getFileFromUrl($url);
            $fs->dumpFile($filename, $temp);
        }

        return $filename;
    }

    protected function getFileFromUrl($url)
    {
        // Create a cURL handle
        $ch = curl_init($url);

        // set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSLVERSION, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Execute the handle and also collect if any error
        $result    = curl_exec($ch);
        $curlErrno = curl_errno($ch);

        // close cURL handle
        curl_close($ch);

        if ($curlErrno > 0) {
            throw new \InvalidArgumentException('Specified file does not exist at url: '.$url);
        }

        return $result;
    }


    protected function load() {
        $doc = new \DOMDocument();
        $doc->load($this->filename);
        if ($this->entityId) {
            $entitiesDescriptor = new EntitiesDescriptor();
            $entitiesDescriptor->loadFromXml($doc->firstChild);
            $this->entityDescriptor = $entitiesDescriptor->getByEntityId($this->entityId);
        } else {
            $this->entityDescriptor = new EntityDescriptor();
            $this->entityDescriptor->loadFromXml($doc->firstChild);
        }
    }

} 