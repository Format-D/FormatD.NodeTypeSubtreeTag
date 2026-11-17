<?php

declare(strict_types=1);

namespace FormatD\NodeTypeSubtreeTag\CommandHook;

use FormatD\NodeTypeSubtreeTag\Service\NodeTypeSubtreeTagResolver;
use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\CommandHandler\Commands;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\EventStore\PublishedEvents;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeVariation\Command\CreateNodeVariant;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\TagSubtree;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class NodeTypeSubtreeTaggingCommandHook implements CommandHookInterface
{
    public function __construct(
        private NodeTypeSubtreeTagResolver $tagResolver,
        private ContentGraphReadModelInterface $contentGraphReadModel
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
        return match (true) {
            $command instanceof CreateNodeAggregateWithNode => $this->createTaggingCommands(
                $command->workspaceName,
                $command->nodeAggregateId,
                $command->originDimensionSpacePoint->toDimensionSpacePoint()
            ),
            $command instanceof CreateNodeVariant => $this->createTaggingCommands(
                $command->workspaceName,
                $command->nodeAggregateId,
                $command->targetOrigin->toDimensionSpacePoint()
            ),
            default => Commands::createEmpty(),
        };
    }

    protected function createTaggingCommands(
        WorkspaceName $workspaceName,
        NodeAggregateId $nodeAggregateId,
        DimensionSpacePoint $dimensionSpacePoint
    ): Commands {
        $nodeAggregate = $this->contentGraphReadModel
            ->getContentGraph($workspaceName)
            ->findNodeAggregateById($nodeAggregateId);
        if ($nodeAggregate === null) {
            return Commands::createEmpty();
        }

        $tags = $this->tagResolver->resolveTagsForNodeType($nodeAggregate->nodeTypeName);
        if ($tags === []) {
            return Commands::createEmpty();
        }

        $taggingCommands = [];
        foreach ($tags as $tagConfig) {
            if (!$this->tagResolver->shouldApplyTagInWorkspace($tagConfig, $workspaceName->value)) {
                continue;
            }

            $tag = SubtreeTag::fromString($tagConfig['name']);
            if ($nodeAggregate->getCoveredDimensionsTaggedBy($tag, withoutInherited: true)->contains($dimensionSpacePoint)) {
                continue;
            }

            $taggingCommands[] = TagSubtree::create(
                $workspaceName,
                $nodeAggregateId,
                $dimensionSpacePoint,
                NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS,
                $tag
            );
        }

        return Commands::create(...$taggingCommands);
    }
}
