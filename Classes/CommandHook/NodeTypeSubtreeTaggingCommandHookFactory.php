<?php

declare(strict_types=1);

namespace FormatD\NodeTypeSubtreeTag\CommandHook;

use FormatD\NodeTypeSubtreeTag\Service\NodeTypeSubtreeTagResolver;
use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\Factory\CommandHookFactoryInterface;
use Neos\ContentRepository\Core\Factory\CommandHooksFactoryDependencies;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;

class NodeTypeSubtreeTaggingCommandHookFactory implements CommandHookFactoryInterface
{
    #[Flow\Inject(name: 'Neos.Flow:SystemLogger')]
    protected LoggerInterface $logger;

    public function build(CommandHooksFactoryDependencies $dependencies): CommandHookInterface
    {
        return new NodeTypeSubtreeTaggingCommandHook(
            new NodeTypeSubtreeTagResolver($dependencies->nodeTypeManager, $this->logger),
            $dependencies->contentGraphReadModel
        );
    }
}
