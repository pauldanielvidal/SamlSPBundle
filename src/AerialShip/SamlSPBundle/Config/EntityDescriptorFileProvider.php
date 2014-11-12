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
        $hasCachedFile = false;
        $isValidCached = false;
        $maxTimestamp = new \DateTime();
        $maxTimestamp->modify('-1 day');

        if ($fs->exists($filename)) {
            $hasCachedFile = true;
            $isValidCached = true;
            $fileTimestamp = new \DateTime();
            $fileTimestamp->setTimestamp(filemtime($filename));
            // set isCachedValid = false if one day has passed
            if ($maxTimestamp > $fileTimestamp) {
                $isValidCached = false;
            }
        }

        // trying to get and dump file if not exists or one day passed
        if (!$isValidCached) {
            try {
                $temp = $this->getFileFromUrl($url);

                // check if file is valid xml else throw error
                $isValidXml = @simplexml_load_string($temp);
                if ($isValidXml === false) {
                    throw new \InvalidArgumentException('Specified file is not valid xml at url: '.$url);
                }

                // dump fresh file
                $fs->dumpFile($filename, $temp);

            } catch (\InvalidArgumentException $e) {
                // return cached file in case of error else throw final error
                if ($hasCachedFile) {
                    return $filename;
                } else {
                    throw new \InvalidArgumentException('Cached file does not exist for specified url: '.$url, 0, $e);
                }
            }
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