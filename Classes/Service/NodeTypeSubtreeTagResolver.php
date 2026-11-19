<?php

declare(strict_types=1);

namespace FormatD\NodeTypeSubtreeTag\Service;

use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Psr\Log\LoggerInterface;

class NodeTypeSubtreeTagResolver
{
    public const CONFIGURATION_PATH = 'options.subtree.tags';

    /**
     * @deprecated top-level "subtree" is rejected by the NodeTypes schema, use {@see self::CONFIGURATION_PATH}
     */
    public const LEGACY_CONFIGURATION_PATH = 'subtree.tags';

    /**
     * @var array<string, true>
     */
    protected array $reportedLegacyNodeTypes = [];

    public function __construct(
        protected readonly NodeTypeManager $nodeTypeManager,
        protected readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Get subtree tags configured for a node type
     * Supports multiple formats:
     * - Simple string: 'tag-name'
     * - Object with tag and optional workspaces: ['tag' => 'name', 'workspaces' => ['live']]
     *
     * @return array<array{name: string, workspaces: array<string>|null}> Array of tag configurations
     */
    public function resolveTagsForNodeType(NodeTypeName $nodeTypeName): array
    {
        $nodeType = $this->nodeTypeManager->getNodeType($nodeTypeName);

        if ($nodeType === null) {
            return [];
        }

        $tagsConfig = $nodeType->getConfiguration(self::CONFIGURATION_PATH);
        $legacyTagsConfig = $nodeType->getConfiguration(self::LEGACY_CONFIGURATION_PATH);

        if ($legacyTagsConfig !== null) {
            $this->reportLegacyConfiguration($nodeTypeName);
        }

        // keyed by tag name, so a tag configured in both places is only applied once (new config wins)
        $normalizedTags = [];
        foreach ([$legacyTagsConfig, $tagsConfig] as $config) {
            if (!\is_array($config)) {
                continue;
            }
            foreach ($config as $key => $tag) {
                $normalized = $this->normalizeTagConfig($key, $tag);
                if ($normalized !== null) {
                    $normalizedTags[$normalized['name']] = $normalized;
                }
            }
        }

        return array_values($normalizedTags);
    }

    public function usesLegacyConfiguration(NodeTypeName $nodeTypeName): bool
    {
        return $this->nodeTypeManager->getNodeType($nodeTypeName)?->getConfiguration(self::LEGACY_CONFIGURATION_PATH) !== null;
    }

    public static function legacyConfigurationMessage(NodeTypeName $nodeTypeName): string
    {
        return \sprintf(
            'NodeType "%s" (or one of its supertypes) configures subtree tags via the deprecated top-level "%s". Move it to "%s", the legacy path will be removed in the next major version.',
            $nodeTypeName->value,
            self::LEGACY_CONFIGURATION_PATH,
            self::CONFIGURATION_PATH
        );
    }

    protected function reportLegacyConfiguration(NodeTypeName $nodeTypeName): void
    {
        if ($this->logger === null || isset($this->reportedLegacyNodeTypes[$nodeTypeName->value])) {
            return;
        }
        $this->reportedLegacyNodeTypes[$nodeTypeName->value] = true;
        $this->logger->warning(self::legacyConfigurationMessage($nodeTypeName));
    }

    /**
     * Validate that all tags match the required pattern
     *
     * @param array<array{name: string, workspaces: array<string>|null}> $tags
     * @throws \InvalidArgumentException
     */
    public function validateTags(array $tags): void
    {
        $pattern = '/^[a-z0-9_.-]{1,36}$/';

        foreach ($tags as $tagConfig) {
            $tagName = $tagConfig['name'];

            if (!\is_string($tagName)) {
                throw new \InvalidArgumentException('Tag name must be a string', 1738075200);
            }

            if (preg_match($pattern, $tagName) !== 1) {
                throw new \InvalidArgumentException(
                    \sprintf('Tag "%s" does not match pattern "%s"', $tagName, $pattern),
                    1738075201
                );
            }

            if ($tagConfig['workspaces'] !== null && !\is_array($tagConfig['workspaces'])) {
                throw new \InvalidArgumentException(
                    \sprintf('Workspaces for tag "%s" must be an array or null', $tagName),
                    1738075202
                );
            }
        }
    }

    /**
     * Normalize a single tag configuration to standard format
     *
     * Supports:
     * - String value: 'tag-name' -> {name: 'tag-name', workspaces: null}
     * - Array with 'tag' key: {tag: 'name', workspaces: ['live']} -> {name: 'name', workspaces: ['live']}
     * - Key-value pair: 'tagKey' => 'tag-name' -> {name: 'tag-name', workspaces: null}
     *
     * @param string|int $key
     * @param mixed $value
     * @return array{name: string, workspaces: array<string>|null}|null
     */
    private function normalizeTagConfig(string|int $key, mixed $value): ?array
    {
        if (\is_string($value)) {
            return [
                'name' => $value,
                'workspaces' => null,
            ];
        }

        if (\is_array($value) && isset($value['tag'])) {
            $workspaces = null;
            if (isset($value['workspaces']) && \is_array($value['workspaces'])) {
                $workspaces = array_filter($value['workspaces'], static fn ($w) => \is_string($w));
                $workspaces = $workspaces ?: null;
            }

            return [
                'name' => $value['tag'],
                'workspaces' => $workspaces,
            ];
        }

        return null;
    }

    /**
     * Check if a tag should be applied in the given workspace
     *
     * @param array{name: string, workspaces: array<string>|null} $tagConfig
     * @param string $workspaceName
     */
    public function shouldApplyTagInWorkspace(array $tagConfig, string $workspaceName): bool
    {
        if ($tagConfig['workspaces'] === null) {
            return true;
        }

        return \in_array($workspaceName, $tagConfig['workspaces'], true);
    }
}
