<?php

declare(strict_types=1);

namespace FormatD\NodeTypeSubtreeTag\CommandHook;

use FormatD\NodeTypeSubtreeTag\Service\NodeTypeSubtreeTagResolver;
use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\CommandHandler\Commands;
use Neos\ContentRepository\Core\EventStore\PublishedEvents;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\TagSubtree;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class NodeTypeSubtreeTaggingCommandHook implements CommandHookInterface
{
    public function __construct(
        private NodeTypeSubtreeTagResolver $tagResolver
    ) {
    }

    public function onBeforeHandle(CommandInterface $command): CommandInterface
    {
        if (!($command instanceof CreateNodeAggregateWithNode)) {
            return $command;
        }

        $tags = $this->tagResolver->resolveTagsForNodeType($command->nodeTypeName);
        $this->tagResolver->validateTags($tags);

        return $command;
    }

    public function onAfterHandle(CommandInterface $command, PublishedEvents $events): Commands
    {
        if (!($command instanceof CreateNodeAggregateWithNode)) {
            return Commands::createEmpty();
        }

        $tags = $this->tagResolver->resolveTagsForNodeType($command->nodeTypeName);
        if ($tags === []) {
            return Commands::createEmpty();
        }

        $taggingCommands = [];
        foreach ($tags as $tagConfig) {
            if (!$this->tagResolver->shouldApplyTagInWorkspace($tagConfig, $command->workspaceName->value)) {
                continue;
            }

            $taggingCommands[] = TagSubtree::create(
                $command->workspaceName,
                $command->nodeAggregateId,
                $command->originDimensionSpacePoint->toDimensionSpacePoint(),
                NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS,
                SubtreeTag::fromString($tagConfig['name'])
            );
        }

        return Commands::create(...$taggingCommands);
    }
}
