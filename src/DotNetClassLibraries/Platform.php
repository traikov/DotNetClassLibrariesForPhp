<?php

namespace DotNetClassLibraries;

class Platform
{
    const IsWindows = (PHP_OS == "Windows" || PHP_OS == "WINNT");
}