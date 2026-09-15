<?php

DG\BypassFinals::enable();
require (\Composer\InstalledVersions::getInstallPath('typo3/testing-framework') ?? '../../.Build/vendor/typo3/testing-framework')
    . '/Resources/Core/Build/FunctionalTestsBootstrap.php';
