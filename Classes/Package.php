<?php
namespace Ttree\CrossReference;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;

class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $dispatcher->connect(Node::class, 'nodePropertyChanged', MappingService::class, 'process');
    }
}
