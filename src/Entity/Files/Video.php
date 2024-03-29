<?php

namespace CarrionGrow\Uploader\Entity\Files;

use CarrionGrow\Uploader\Entity\Configs\VideoConfig;
use CarrionGrow\Uploader\Exception\Code;
use CarrionGrow\Uploader\Exception\Exception;
use CarrionGrow\Uploader\Exception\VideoException;
use getID3;

class Video extends File
{
    /** @var int */
    protected $width;
    /** @var int */
    protected $height;
    /** @var int */
    protected $duration;
    /** @var int */
    protected $bitrate;
    /** @var string */
    protected $videoCodec;
    /** @var string */
    protected $audioCodec;
    /** @var VideoConfig */
    protected $config;

#region getter

    /**
     * @return int
     * @psalm-api
     */
    public function getWidth(): int
    {
        return $this->width ?? 0;
    }

    /**
     * @return int
     * @psalm-api
     */
    public function getHeight(): int
    {
        return $this->height ?? 0;
    }

    /**
     * @return float
     * @psalm-api
     */
    public function getDuration(): float
    {
        return $this->duration ?? 0;
    }

    /**
     * @return float
     * @psalm-api
     */
    public function getBitrate(): float
    {
        return $this->bitrate ?? 0;
    }

    /**
     * @return string
     * @psalm-api
     */
    public function getVideoCodec(): string
    {
        return $this->videoCodec ?? '';
    }

    /**
     * @return string
     * @psalm-api
     */
    public function getAudioCodec(): string
    {
        return $this->audioCodec ?? '';
    }

#endregion

    public function __construct(VideoConfig $config)
    {
        parent::__construct($config);
    }

    /**
     * @param array $file
     * @return void
     * @throws Exception
     */
    public function behave(array $file)
    {
        parent::behave($file);

        $meta = (new GetID3())->analyze($this->getTempPath());
        $this->duration = $meta['playtime_seconds'] ?? 0;
        $this->bitrate = ($meta['bitrate'] ?? 0) / 1000;
        $this->videoCodec = $meta['video']['fourcc_lookup'] ?? '';
        $this->audioCodec = $meta['audio']['codec'] ?? '';
        $this->width = (int)($meta['video']['resolution_x'] ?? 0);
        $this->height = (int)($meta['video']['resolution_y'] ?? 0);

        if ($this->config->getMaxDuration() > 0 && $this->duration > $this->config->getMaxDuration())
            throw VideoException::durationLarge($this->config->getMaxWidth());

        if ($this->config->getMinDuration() > 0 && $this->duration < $this->config->getMinDuration())
            throw VideoException::durationLess($this->config->getMinDuration());

        if ($this->config->getMaxBitrate() > 0 && $this->bitrate > $this->config->getMaxBitrate())
            throw VideoException::bitrateLarge($this->config->getMaxBitrate());

        if ($this->config->getMinBitrate() > 0 && $this->bitrate < $this->config->getMinBitrate())
            throw VideoException::bitrateLess($this->config->getMinBitrate());

        if ($this->config->getMaxWidth() > 0 && $this->width > $this->config->getMaxWidth())
            throw VideoException::widthLarger($this->config->getMaxWidth());

        if ($this->config->getMaxHeight() > 0 && $this->height > $this->config->getMaxHeight())
            throw VideoException::heightLarger($this->config->getMaxHeight());

        if ($this->config->getMinWidth() > 0 && $this->width < $this->config->getMinWidth())
            throw VideoException::widthLess($this->config->getMinWidth());

        if ($this->config->getMinHeight() > 0 && $this->height < $this->config->getMinHeight())
            throw VideoException::heightLess($this->config->getMinHeight());

        $this->validateVideoCodec();
        $this->validateAudioCodec();
    }

    /**
     * @throws Exception
     * @return void
     */
    private function validateVideoCodec()
    {
        $allowedCodec = $this->config->getVideoCodec();

        if ($allowedCodec === '*') {
            return;
        }

        foreach ((array)$allowedCodec as $item) {
            if (strpos(strtolower($this->videoCodec), strtolower($item)) !== false) {
                return;
            }
        }

        throw new Exception(Code::VIDEO_CODEC);
    }

    /**
     * @throws Exception
     * @return void
     */
    private function validateAudioCodec()
    {
        $allowedCodec = $this->config->getAudioCodec();

        if ($allowedCodec === '*') {
            return;
        }

        foreach ((array)$allowedCodec as $item) {
            if (strpos(strtolower($this->audioCodec), strtolower($item)) !== false) {
                return;
            }
        }

        throw new Exception(Code::AUDIO_CODEC);
    }
}