<?php

declare(strict_types=1);

namespace FormatD\NodeTypeSubtreeTag\Service;

use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;

class NodeTypeSubtreeTagResolver
{
    public function __construct(
        private readonly NodeTypeManager $nodeTypeManager
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

        $tagsConfig = $nodeType->getConfiguration('subtree.tags');

        if (!\is_array($tagsConfig)) {
            return [];
        }

        $normalizedTags = [];

        foreach ($tagsConfig as $key => $tag) {
            $normalized = $this->normalizeTagConfig($key, $tag);
            if ($normalized !== null) {
                $normalizedTags[] = $normalized;
            }
        }

        return $normalizedTags;
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
