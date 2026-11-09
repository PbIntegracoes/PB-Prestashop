<?php

use PrestaShop\CodingStandards\CsFixer\Config as PsConfig;

/**
 * PrestaShop standards + Validator license header compatibility.
 * (blank_line_after_opening_tag would insert a line between <?php and the file comment.)
 */
final class PbConnectCsFixerConfig extends PsConfig
{
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'blank_line_after_opening_tag' => false,
        ]);
    }
}

$config = new PbConnectCsFixerConfig();

/** @var \Symfony\Component\Finder\Finder $finder */
$finder = $config->setUsingCache(true)->getFinder();
$finder->in(__DIR__)->exclude('vendor');

return $config;
