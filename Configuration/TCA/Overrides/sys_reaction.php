<?php

defined('TYPO3') or die();

if (
    \Pixelant\Interest\Utility\CompatibilityUtility::typo3VersionIsGreaterThanOrEqualTo('12.0')
    && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('reactions')
) {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
        'sys_reaction',
        'reaction_type',
        [
            'label' => \Pixelant\Interest\Reaction\CreateUpdateDeleteReaction::getDescription(),
            'value' => \Pixelant\Interest\Reaction\CreateUpdateDeleteReaction::getType(),
            'icon' => \Pixelant\Interest\Reaction\CreateUpdateDeleteReaction::getIconIdentifier(),
        ]
    );
}
