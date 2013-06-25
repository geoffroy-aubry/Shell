<?php

namespace GAubry\Shell;

/**
 * All possible status for a file system path.
 */
final class PathStatus
{
    /**
     * Path doesn't exist.
     * @var int
     */
    const STATUS_NOT_EXISTS = 0;

    /**
     * Path is a file.
     * @var int
     */
    const STATUS_FILE = 1;

    /**
     * Path is a directory.
     * @var int
     */
    const STATUS_DIR = 2;

    /**
     * Path is a broken symbolic link.
     * @var int
     */
    const STATUS_BROKEN_SYMLINK = 10;

    /**
     * Path is a symbolic link to a true file.
     * @var int
     */
    const STATUS_SYMLINKED_FILE = 11;

    /**
     * Path is a symbolic link to a directory.
     * @var int
     */
    const STATUS_SYMLINKED_DIR = 12;

    /**
     * Constructor.
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
}
