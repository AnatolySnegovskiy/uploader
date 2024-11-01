<?php

namespace CarrionGrow\Uploader;

use CarrionGrow\Uploader\Collections\ConfigCollection;
use CarrionGrow\Uploader\Collections\FileCollection;
use CarrionGrow\Uploader\Entity\Configs\Config;
use CarrionGrow\Uploader\Entity\Files\UploadHandlerInterface;
use CarrionGrow\Uploader\Exception\Code;
use CarrionGrow\Uploader\Exception\Exception;
use CarrionGrow\Uploader\Exception\FilesException;
use CarrionGrow\Uploader\Factories\FileFactories;
use CarrionGrow\Uploader\Utilities\UrlHelper;

/**
 * @psalm-api
 */
class Upload
{
    /** @var array */
    private array $temp = [];
    /** @var ConfigCollection */
    private ConfigCollection $configs;

    public function __construct()
    {
        $this->configs = new ConfigCollection();
    }

    /**
     * @return ConfigCollection
     * @psalm-api
     */
    public function getConfigs(): ConfigCollection
    {
        return $this->configs;
    }

    /**
     * @param ConfigCollection $configs
     * @return $this
     * @psalm-api
     */
    public function setConfigs(ConfigCollection $configs): Upload
    {
        $this->configs = $configs;
        return $this;
    }

    /**
     * @return FileCollection
     * @throws Exception
     * @throws FilesException
     * @psalm-api
     */
    public function uploadAll(): FileCollection
    {
        return $this->upload(array_merge($this->reArrayFiles(), $this->reArrayPost(), $this->reArrayGet()));
    }

    /**
     * @return FileCollection
     * @throws Exception
     * @throws FilesException
     * @psalm-api
     */
    public function uploadFiles(): FileCollection
    {
        return $this->upload($this->reArrayFiles());
    }

    /**
     * @return FileCollection
     * @throws Exception
     * @throws FilesException
     * @psalm-api
     */
    public function uploadPost(): FileCollection
    {
        return $this->upload($this->reArrayPost());
    }

    /**
     * @return FileCollection
     * @throws Exception
     * @throws FilesException
     * @psalm-api
     */
    public function uploadGet(): FileCollection
    {
        return $this->upload($this->reArrayGet());
    }

    /**
     * @param array $listFiles
     * @return FileCollection
     * @throws Exception
     * @throws FilesException
     */
    private function upload(array $listFiles): FileCollection
    {
        $array = new FileCollection();

        /**
         * @var string $key
         * @var array<string, mixed> $item
         */
        foreach ($listFiles as $key => $item) {
            $config = $this->getConfig($key);

            try {
                $file = $this->doUpload($config, $item);
                $this->moveUploadedFile($file);
                $array->setFile($key, $file);
            } catch (Exception $exception) {
                if ($config->isSkipError()) {
                    $array->setFile($key, $exception);
                } else {
                    throw $exception;
                }
            }
        }

        if (empty($listFiles)) {
            throw new FilesException(Code::NOT_FILE);
        }

        return $array;
    }

    /**
     * @param string $key
     * @return Config
     */
    private function getConfig(string $key): Config
    {
        $key = current(explode('||', $key));
        return $this->configs->get($key) ?? $this->configs->first();
    }

    /**
     * @param Config $config
     * @param array<string, mixed> $file
     * @return UploadHandlerInterface
     * @throws FilesException|Exception
     */
    private function doUpload(Config $config, array $file): UploadHandlerInterface
    {
        if (!empty($file['error'])) {
            throw new FilesException((int)$file['error']);
        }

        return (new FileFactories($this->validateUploadPath($config)))->init($file);
    }

    /**
     * @throws Exception
     */
    private function moveUploadedFile(UploadHandlerInterface $file): void
    {
        if (@copy($file->getTempPath(), $file->getFilePath()) === false) {
            if (@move_uploaded_file($file->getTempPath(), $file->getFilePath()) === false) {
                throw new Exception(Code::FILE_COPYING, 'A problem was encountered while attempting to move the uploaded file to the final destination');
            }
        }
    }

    /**
     * @return array
     */
    private function reArrayPost(): array
    {
        return $this->linksToFilesObject($_POST);
    }

    /**
     * @return array
     */
    private function reArrayGet(): array
    {
        return $this->linksToFilesObject($_GET);
    }

    /**
     * @param array $linkList
     * @return array
     */
    private function linksToFilesObject(array $linkList): array
    {
        $result = [];

        /**
         * @var string $key
         * @var string $link
         */
        foreach ($linkList as $key => $link) {
            $name = urldecode(basename($link));
            $link = UrlHelper::toUrl($link);

            if (!filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }

            $codeError = 0;
            $path = '';
            $headers = $this->getHeaders($link);

            if (empty($headers['content-length'])) {
                $codeError = 4;
            }

            if (empty($codeError)) {
                $this->temp[$name] = tmpfile();
                $path = stream_get_meta_data($this->temp[$name])['uri'];
                $data = @file_get_contents($link);

                if ($data !== '' && $data !== false) {
                    fwrite($this->temp[$name], $data);
                } else {
                    $codeError = 4;
                }
            }

            $result[$key] =
                [
                    'name' => $name,
                    'type' => $headers['content-type'] ?? '',
                    'tmp_name' => $path,
                    'error' => $codeError,
                    'size' => $headers['content-length'] ?? '',
                ];
        }

        return $result;
    }

    /**
     * @return array
     */
    private function reArrayFiles(): array
    {
        /** @var array<string, array<string, string|array<string>>> $_FILES */
        $result = [];
        /**
         * @var string $postKey
         * @var array<string, string|array<string>> $item
         */
        foreach ($_FILES ?? [] as $postKey => $item) {
            if (!is_array($item['name'])) {
                $result[$postKey] = $item;
                continue;
            }

            $fileCount = count($item['name']);
            $fileKeys = array_keys($item);

            /** @var int $i */
            for ($i = 0; $i < $fileCount; $i++) {
                /** @var string $key */
                foreach ($fileKeys as $key) {
                    $result[implode('||', [$postKey, $i])][$key] = $item[$key][$i];
                }
            }
        }

        return $result;
    }

    /**
     * @param Config $config
     * @return Config
     * @throws Exception
     */
    private function validateUploadPath(Config $config): Config
    {
        $uploadPath = $config->getUploadPath();

        if (is_dir($uploadPath) === false) {
            mkdir($uploadPath, 0777, true);
        }

        if ($uploadPath === '') {
            throw new Exception(Code::REMOTE_URI);
        }

        if (realpath($uploadPath) !== false) {
            $uploadPath = str_replace('\\', '/', realpath($uploadPath));
        }

        if (is_dir($uploadPath) === false) {
            throw new Exception(Code::REMOTE_URI);
        }

        if ($this->isReallyWritable($uploadPath) === false) {
            throw new Exception(Code::READING_DIRECTORY);
        }

        $config->setUploadPath(preg_replace('/(.+?)\/*$/', '\\1/', $uploadPath));

        return $config;
    }

    /**
     * @param string $file
     * @return bool
     */
    private function isReallyWritable(string $file): bool
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return is_writable($file);
        }

        if (is_dir($file)) {
            $file = rtrim($file, '/') . '/' . md5((string)mt_rand());
            $fp = @fopen($file, 'ab');

            if ($fp === false) {
                return false;
            }

            fclose($fp);
            @chmod($file, 0777);
            @unlink($file);

            return true;
        } elseif (!is_file($file)) {
            return false;
        }

        $fp = @fopen($file, 'ab');

        if ($fp === false) {
            return false;
        }

        fclose($fp);

        return true;
    }

    /**
     * @param string $link
     * @return array
     */
    private function getHeaders(string $link): array
    {
        $headers = [];

        $sourceHeader = get_headers($link, true);

        if ($sourceHeader === false) {
            return $headers;
        }

        foreach ($sourceHeader as $key => $item) {
            $headers[strtolower($key)] = is_array($item) ? end($item) : $item;
        }

        return $headers;
    }
}
