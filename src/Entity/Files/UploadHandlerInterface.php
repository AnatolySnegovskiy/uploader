<?php

namespace CarrionGrow\Uploader\Entity\Files;

use CarrionGrow\Uploader\Entity\ToArrayInterface;

interface UploadHandlerInterface extends ToArrayInterface
{
    public function behave(array $file): void;

    public function getTempPath(): string;

    public function getFilePath(): string;
}
