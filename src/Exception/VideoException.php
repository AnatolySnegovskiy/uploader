<?php

namespace CarrionGrow\Uploader\Exception;

class VideoException
{

    static public function widthLarger(int $width): Exception
    {
        return self::exception(sprintf('The video width value is larger than the permitted size: %d px', $width));
    }

    static public function heightLarger(int $height): Exception
    {
        return self::exception(sprintf('The video height value is larger than the permitted size: %d px', $height));
    }

    static public function widthLess(int $width): Exception
    {
        return self::exception(sprintf('The video width value is less than the permitted size: %d px', $width));
    }

    static public function heightLess(int $height): Exception
    {
        return self::exception(sprintf('The video height value is less than the permitted size: %d px', $height));
    }

    static public function durationLarge(float $duration): Exception
    {
        return new Exception(Code::VIDEO_DURATION, sprintf('The video duration value is larger than the permitted: %d', $duration));
    }

    static public function durationLess(float $duration): Exception
    {
        return new Exception(Code::VIDEO_DURATION, sprintf('The video duration value is less than the permitted: %d', $duration));
    }

    static public function bitrateLarge(float $bitrate): Exception
    {
        return new Exception(Code::VIDEO_BITRATE, sprintf('The video bitrate value is larger than the permitted: %d', $bitrate));
    }

    static public function bitrateLess(float $bitrate): Exception
    {
        return new Exception(Code::VIDEO_BITRATE, sprintf('The video bitrate value is less than the permitted: %d', $bitrate));
    }

    static private function exception(string $message): Exception
    {
        return new Exception(Code::RESOLUTION, $message);
    }

}