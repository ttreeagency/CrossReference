<?php
namespace Ttree\CrossReference;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\SystemLoggerInterface;
use Ttree\CrossReference\Domain\Model\Preset;

/**
 * @Flow\Scope("singleton")
 */
final class MappingService
{
    /**
     * @var SystemLoggerInterface
     * @Flow\Inject
     */
    protected $systemLogger;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="presets")
     */
    protected $configuration;

    /**
     * @var array
     */
    private $presets;

    public function initializeObject()
    {
        $this->presets = \array_map(function (array $preset) {
            return new Preset($preset);
        }, $this->configuration);
    }

    public function process(NodeInterface $node, string $propertyName, $oldValue, $newValue): void
    {
        $this->systemLogger->log(sprintf('Cross reference Node %s (%s)', $node->getContextPath(), $node->getNodeType()), \LOG_DEBUG, null, 'Ttree.CrossReference');
    }
}
