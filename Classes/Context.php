<?php

declare(strict_types=1);

namespace Pixelant\Interest;

class Context
{
    /**
     * @var bool
     */
    protected static bool $disableReferenceIndex = false;

    /**
     * @return bool
     */
    public static function isDisableReferenceIndex(): bool
    {
        return self::$disableReferenceIndex;
    }

    /**
     * @param bool $updateReferenceIndex
     */
    public static function setDisableReferenceIndex(bool $updateReferenceIndex)
    {
        self::$disableReferenceIndex = $updateReferenceIndex;
    }
}
