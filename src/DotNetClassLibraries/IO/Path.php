<?php

namespace DotNetClassLibraries\IO;

use DotNetClassLibraries\Platform;
use Exception\NotImplementedException;

/**
 * Class Path
 *
 * A collection of path manipulation methods.
 *
 * @package DotNetClassLibraries\IO
 */
class Path
{
    /**
     * Platform specific directory separator character.  This is backslash
     * ('\') on Windows, slash ('/') on Unix, and colon (':') on Mac.
     */
    const DirectorySeparatorChar = DIRECTORY_SEPARATOR;

    /**
     * Platform specific alternate directory separator character.
     * This is backslash ('\') on Unix, and slash ('/') on Windows and MacOS.
     */
    const AltDirectorySeparatorChar = (PHP_OS == "Windows" || PHP_OS == "WINNT" ? "/" : "\\");

    /**
     * Platform specific volume separator character.  This is colon (':')
     * on Windows and MacOS, and slash ('/') on Unix.  This is mostly
     * useful for parsing paths like "c:\windows" or "MacVolume:System Folder".
     */
    const VolumeSeparatorChar = (PHP_OS == "Windows" || PHP_OS == "WINNT" ? ":" : "/");

    /**
     * Trim trailing white spaces, tabs etc but don't be aggressive in removing everything
     * that has UnicodeCategory of trailing space.
     * String.WhitespaceChars will trim aggressively than what the underlying FS does (for ex, NTFS, FAT).
     */
    private static function getTrimEndChars()
    {
        return [chr(0x9), chr(0xA), chr(0xB), chr(0xC), chr(0xD), chr(0x20), chr(0x85), chr(0xA0)];
    }

    private static function getRealInvalidPathChars()
    {
        return ['\"', '<', '>', '|', '\0', chr(1), chr(2), chr(3), chr(4), chr(5), chr(6), chr(7), chr(8), chr(9),
            chr(10), chr(11), chr(12), chr(13), chr(14), chr(15), chr(16), chr(17), chr(18), chr(19), chr(20), chr(21),
            chr(22), chr(23), chr(24), chr(25), chr(26), chr(27), chr(28), chr(29), chr(30), chr(31)
        ];
    }

    /**
     * This is used by HasIllegalCharacters
     */
    private static function getInvalidPathCharsWithAdditionalChecks()
    {
        return ['\"', '<', '>', '|', '\0', chr(1), chr(2), chr(3), chr(4), chr(5), chr(6), chr(7), chr(8), chr(9),
            chr(10), chr(11), chr(12), chr(13), chr(14), chr(15), chr(16), chr(17), chr(18), chr(19), chr(20), chr(21),
            chr(22), chr(23), chr(24), chr(25), chr(26), chr(27), chr(28), chr(29), chr(30), chr(31), '*', '?'
        ];
    }

    private static function getInvalidFileNameChars()
    {
        return [
            '\"', '<', '>', '|', '\0', chr(1), chr(2), chr(3), chr(4), chr(5), chr(6), chr(7), chr(8), chr(9), chr(10),
            chr(11), chr(12), chr(13), chr(14), chr(15), chr(16), chr(17), chr(18), chr(19), chr(20), chr(21), chr(22),
            chr(23), chr(24), chr(25), chr(26), chr(27), chr(28), chr(29), chr(30), chr(31), ':', '*', '?', '\\', '/'
        ];
    }

    const PathSeparator = PATH_SEPARATOR;

    const MaxPath = PHP_MAXPATHLEN;

    const MaxDirectoryLength = PHP_MAXPATHLEN;

    /**
     * Changes the extension of a file path. The path parameter
     * specifies a file path, and the extension parameter
     * specifies a file extension (with a leading period, such as
     * ".exe" or ".cs").
     *
     * The function returns a file path with the same root, directory, and base
     * name parts as path, but with the file extension changed to
     * the specified extension. If path is null, the function
     * returns null. If path does not contain a file extension,
     * the new file extension is appended to the path. If extension
     * is null, any existing extension is removed from path.
     *
     * @param string $path
     * @param string $extension
     * @return string
     */
    public static function changeExtension(string $path, string $extension) : string
    {
        if ($path != null) {
            self::checkInvalidPathChars($path);

            $s = $path;
            $pathLength = strlen($path);
            for ($i = $pathLength; --$i >= 0;) {
                $ch = $path[$i];
                if ($ch == '.') {
                    $s = substr($path, 0, $i);
                    break;
                }
                if ($ch == self::DirectorySeparatorChar || $ch == self::AltDirectorySeparatorChar || $ch == self::VolumeSeparatorChar) {
                    break;
                }
            }
            if ($extension != null && $pathLength != 0) {
                if (strlen($extension) == 0 || $extension[0] != '.') {
                    $s = $s . ".";
                }
                $s = $s . $extension;
            }
            return $s;
        }
        return null;
    }

    static function normalizePath($path, $unknown) : string
    {

    }

    /**
     * Returns the directory path of a file path. This method effectively
     * removes the last element of the given file path, i.e. it returns a
     * string consisting of all characters up to but not including the last
     * backslash ("\") in the file path. The returned value is null if the file
     * path is null or if the file path denotes a root (such as "\", "C:", or "\\server\share").
     *
     * @param string $path
     * @return string
     */
    public static function getDirectoryName(string $path) : string
    {
        if ($path != null) {
            self::checkInvalidPathChars($path);
            $path = self::normalizePath($path, false);
            $root = self::getRootLength($path);
            $pathLength = strlen($path);
            $i = $pathLength;
            if ($i > $root) {
                $i = $pathLength;

                while ($i > $root && $path[--$i] != self::DirectorySeparatorChar && $path[$i] != self::AltDirectorySeparatorChar) ;
                return substr($path, 0, $i);
            } else if ($i == $root) {
                return null;
            }
        }
        return null;
    }

    /**
     * Gets the length of the root DirectoryInfo or whatever DirectoryInfo markers
     * are specified for the first part of the DirectoryInfo name.
     *
     * @param string $path
     * @return int
     */
    private static function getRootLength(string $path) : int
    {
        self::checkInvalidPathChars($path);

        $i = 0;
        $length = strlen($path);

        if (Platform::IsWindows) {
            if ($length >= 1 && (self::isDirectorySeparator($path[0]))) {
                // handles UNC names and directories off current drive's root.
                $i = 1;
                if ($length >= 2 && (self::isDirectorySeparator($path[1]))) {
                    $i = 2;
                    $n = 2;
                    while ($i < $length && (($path[$i] != self::DirectorySeparatorChar && $path[$i] != self::AltDirectorySeparatorChar) || --$n > 0)) $i++;
                }
            } else if ($length >= 2 && $path[1] == self::VolumeSeparatorChar) {
                // handles A:\foo.
                $i = 2;
                if ($length >= 3 && (self::isDirectorySeparator($path[2]))) $i++;
            }
            return $i;
        } else {
            if ($length >= 1 && (self::isDirectorySeparator($path[0]))) {
                $i = 1;
            }
            return $i;
        }
    }

    /**
     * @param string $c
     * @return bool
     */
    private static function isDirectorySeparator(string $c) : bool
    {
        return ($c === self::DirectorySeparatorChar || $c === self::AltDirectorySeparatorChar);
    }

    /**
     * Returns the extension of the given path. The returned value includes the
     * period (".") character of the extension except when you have a terminal period
     * when you get String.Empty, such as ".exe" or ".cpp". The returned value is null if the given path is
     * null or if the given path does not include an extension.
     *
     * @param string $path
     * @return string
     */
    public static function getExtension(string $path) : string
    {
        if ($path == null) {
            return null;
        }

        self::checkInvalidPathChars($path);
        $length = strlen($path);
        for ($i = $length; --$i >= 0;) {
            $ch = $path[$i];
            if ($ch == '.') {
                if ($i != $length - 1) {
                    return substr($path, $i, $length - $i);
                } else {
                    return '';
                }
            }
            if ($ch == self::DirectorySeparatorChar
                || $ch == self::AltDirectorySeparatorChar
                || $ch == self::VolumeSeparatorChar
            ) {
                break;
            }
        }
        return '';
    }

    /**
     * Expands the given path to a fully qualified path. The resulting string
     * consists of a drive letter, a colon, and a root relative path. This
     * function does not verify that the resulting path
     * refers to an existing file or directory on the associated volume.
     *
     * @param string $path
     * @return string
     * @throws NotImplementedException
     */
    public static function getFullPath(string $path) : string
    {
        throw new NotImplementedException('Not implemented yet.');
        //$fullPath = self::getFullPathInternal($path);
        //return fullPath;
    }

    private static function hasIllegalCharacters(string $path, bool $checkAdditional) : bool
    {
        //Contract.Requires(path != null);

        if ($checkAdditional) {
            //return path.IndexOfAny(InvalidPathCharsWithAdditionalChecks) >= 0;
        }
        //return path.IndexOfAny(RealInvalidPathChars) >= 0;
    }

    private static function checkInvalidPathChars(string $path, bool $checkAdditional = false) /* : void */
    {
        if ($path == null) {
            //throw new \InvalidArgumentNullException("path");
        }

        if (self::HasIllegalCharacters($path, $checkAdditional)) {
            //throw new ArgumentException(Environment.GetResourceString("Argument_InvalidPathChars"));
        }
    }

}