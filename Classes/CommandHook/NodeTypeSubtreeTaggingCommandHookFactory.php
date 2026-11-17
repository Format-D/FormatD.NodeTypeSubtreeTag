<?php

declare(strict_types=1);

namespace FormatD\NodeTypeSubtreeTag\CommandHook;

use FormatD\NodeTypeSubtreeTag\Service\NodeTypeSubtreeTagResolver;
use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\Factory\CommandHookFactoryInterface;
use Neos\ContentRepository\Core\Factory\CommandHooksFactoryDependencies;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class NodeTypeSubtreeTaggingCommandHookFactory implements CommandHookFactoryInterface
{
    public function build(CommandHooksFactoryDependencies $dependencies): CommandHookInterface
    {
        return new NodeTypeSubtreeTaggingCommandHook(
            new NodeTypeSubtreeTagResolver($dependencies->nodeTypeManager),
            $dependencies->contentGraphReadModel
        );
    }
}
