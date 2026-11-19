<?php

declare(strict_types=1);

namespace FormatD\NodeTypeSubtreeTag\Command;

use FormatD\NodeTypeSubtreeTag\Service\NodeTypeSubtreeTagResolver;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\TagSubtree;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Security\Context as SecurityContext;
use Psr\Log\LoggerInterface;

class SubtreeTagCommandController extends CommandController
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected SecurityContext $securityContext;

    #[Flow\Inject(name: 'Neos.Flow:SystemLogger')]
    protected LoggerInterface $logger;

    /**
     * Apply the configured subtree tags to already existing nodes
     *
     * @param string $workspace
     * @param string $contentRepository
     * @param bool $dryRun
     */
    public function applyCommand(string $workspace = 'live', string $contentRepository = 'default', bool $dryRun = false): void
    {
        $contentRepositoryInstance = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString($contentRepository));
        $workspaceName = WorkspaceName::fromString($workspace);
        $tagResolver = new NodeTypeSubtreeTagResolver($contentRepositoryInstance->getNodeTypeManager(), $this->logger);

        $taggedCount = 0;
        foreach ($contentRepositoryInstance->getNodeTypeManager()->getNodeTypes(false) as $nodeType) {
            $tags = $tagResolver->resolveTagsForNodeType($nodeType->name);
            if ($tags === []) {
                continue;
            }
            if ($tagResolver->usesLegacyConfiguration($nodeType->name)) {
                $this->outputLine('<comment>DEPRECATED: %s</comment>', [NodeTypeSubtreeTagResolver::legacyConfigurationMessage($nodeType->name)]);
            }
            $tagResolver->validateTags($tags);

            $nodeAggregates = $contentRepositoryInstance->getContentGraph($workspaceName)->findNodeAggregatesByType($nodeType->name);
            foreach ($nodeAggregates as $nodeAggregate) {
                foreach ($tags as $tagConfig) {
                    if (!$tagResolver->shouldApplyTagInWorkspace($tagConfig, $workspaceName->value)) {
                        continue;
                    }
                    $taggedCount += $this->tagNodeAggregate(
                        $contentRepositoryInstance,
                        $workspaceName,
                        $nodeAggregate->nodeAggregateId,
                        SubtreeTag::fromString($tagConfig['name']),
                        $dryRun
                    );
                }
            }
        }

        $this->outputLine('%s %d node variant(s) in workspace "%s".', [$dryRun ? 'Would tag' : 'Tagged', $taggedCount, $workspaceName->value]);
    }

    protected function tagNodeAggregate(
        ContentRepository $contentRepository,
        WorkspaceName $workspaceName,
        NodeAggregateId $nodeAggregateId,
        SubtreeTag $tag,
        bool $dryRun
    ): int {
        $taggedCount = 0;
        $nodeAggregate = $contentRepository->getContentGraph($workspaceName)->findNodeAggregateById($nodeAggregateId);
        if ($nodeAggregate === null) {
            return 0;
        }

        foreach ($nodeAggregate->occupiedDimensionSpacePoints as $originDimensionSpacePoint) {
            // reload, as tagging one variant with "allSpecializations" might already have tagged the next one
            $nodeAggregate = $contentRepository->getContentGraph($workspaceName)->findNodeAggregateById($nodeAggregateId);
            $dimensionSpacePoint = $originDimensionSpacePoint->toDimensionSpacePoint();
            if ($nodeAggregate === null || $nodeAggregate->getCoveredDimensionsTaggedBy($tag, withoutInherited: true)->contains($dimensionSpacePoint)) {
                continue;
            }

            $this->outputLine('%s "%s" (%s) with "%s" in %s', [$dryRun ? 'Would tag' : 'Tagging', $nodeAggregateId->value, $nodeAggregate->nodeTypeName->value, $tag->value, $dimensionSpacePoint->toJson()]);
            $taggedCount++;
            if ($dryRun) {
                continue;
            }

            $this->securityContext->withoutAuthorizationChecks(static fn () => $contentRepository->handle(TagSubtree::create(
                $workspaceName,
                $nodeAggregateId,
                $dimensionSpacePoint,
                NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS,
                $tag
            )));
        }

        return $taggedCount;
    }
}
