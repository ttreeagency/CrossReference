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
     * @var Preset[]
     */
    private $presets;

    /**
     * @var bool
     */
    private $enabled = true;

    public function initializeObject()
    {
        $this->presets = \array_map(function (array $preset) {
            return new Preset($preset);
        }, $this->configuration);
    }

    public function process(NodeInterface $node, string $propertyName, $oldValue, $newValue): void
    {
        if ($this->skip($node, $propertyName, $oldValue, $newValue)) {
            return;
        }

        $oldValue = $this->normalizeValue($oldValue);
        $newValue = $this->normalizeValue($newValue);
        $removedValue = \array_filter($oldValue, function (NodeInterface $node) use ($newValue) {
            $newValueIdentifiers = array_map(function (NodeInterface $node) { return $node->getIdentifier(); }, $newValue);
            return !\in_array($node->getIdentifier(), $newValueIdentifiers);
        });

        /** @var NodeInterface $targetNode */
        foreach ($newValue as $targetNode) {
            $this->systemLogger->log(vsprintf('Cross reference started in node "%s" in %s (%s)', [
                $node->getLabel(),
                $node->getIdentifier(),
                $node->getNodeType()
            ]), \LOG_DEBUG, null, 'Ttree.CrossReference');

            $mappings = $this->mapping($node->getNodeType()->getName(), $propertyName);

            $this->crossReference($mappings, $node, $targetNode);
        }

        foreach ($removedValue as $targetNode) {
            $this->systemLogger->log(vsprintf('Cross unreference started in node "%s" in %s (%s)', [
                $node->getLabel(),
                $node->getIdentifier(),
                $node->getNodeType()
            ]), \LOG_DEBUG, null, 'Ttree.CrossReference');

            $mappings = $this->mapping($node->getNodeType()->getName(), $propertyName);

            $this->crossReference($mappings, $node, $targetNode, true);
        }
    }

    protected function skip(NodeInterface $node, string $propertyName, $oldValue, $newValue) :bool
    {
        $message = function(string $message) use ($propertyName, $node) {
            return \sprintf($message, $propertyName, $node->getIdentifier(), $node->getNodeType()->getName());
        };

        if (!$this->enabled) {
            $this->systemLogger->log($message('Cross Reference skipped, feature disabled for property "%s" in "%s" (%s)'), \LOG_DEBUG, null, 'Ttree.CrossReference');
            return true;
        }
        if (!$this->isOfReferenceTypes($node, $propertyName)) {
            $this->systemLogger->log($message('Cross Reference skipped, property "%s" in "%s" (%s) is not a reference(s)'), \LOG_DEBUG, null, 'Ttree.CrossReference');
            return true;
        }
        if (!$this->isValidSourceValue($newValue)) {
            $this->systemLogger->log($message('Cross Reference skipped, property "%s" in "%s" (%s) is not a valid source'), \LOG_DEBUG, null, 'Ttree.CrossReference');
            return true;
        }
        if (!$this->match($node->getNodeType()->getName(), $propertyName)) {
            $this->systemLogger->log($message('Cross Reference skipped, property "%s" in "%s" (%s) is not covered by a preset'), \LOG_DEBUG, null, 'Ttree.CrossReference');
            return true;
        }

        return false;
    }

    protected function normalizeValue($value): array
    {
        return \array_filter(\is_array($value) ? $value : [$value], function ($value) { return $value instanceof NodeInterface; });
    }

    protected function isValidSourceValue($value)
    {
        return \is_array($value) && !$value instanceof NodeInterface;
    }

    protected function isOfReferenceTypes(NodeInterface $node, string $propertyName)
    {
        $propertyType = $node->getNodeType()->getConfiguration('properties.' . $propertyName . '.type');
        return \in_array($propertyType, ['reference', 'references'], true);
    }

    protected function crossReference(array $mappings, NodeInterface $sourceNode, NodeInterface $targetNode, $unreference = false)
    {
        foreach ($mappings as $mapping) {
            foreach ($mapping as $targetNodeType => $targetPropertyName) {
                if (!$targetNode->getNodeType()->isOfType($targetNodeType)) {
                    $this->systemLogger->log(vsprintf('Cross reference to "%s" of type %s (%s) skipped, wrong Node Type "%s"', [
                        $targetNode->getLabel(),
                        $targetNode->getNodeType(),
                        $targetNode->getIdentifier(),
                        $targetNodeType
                    ]), \LOG_DEBUG, null, 'Ttree.CrossReference');
                    continue;
                }

                $targetPropertyType = $targetNode->getNodeType()->getConfiguration('properties.' . $targetPropertyName . '.type');
                if ($targetPropertyType !== 'references') {
                    $this->systemLogger->log(vsprintf('Cross reference to "%s" of type %s (%s) skipped, property "%s" is not of type "references"', [
                        $targetNode->getLabel(),
                        $targetNode->getNodeType(),
                        $targetNode->getIdentifier(),
                        $targetPropertyName
                    ]), \LOG_DEBUG, null, 'Ttree.CrossReference');
                    continue;
                }

                $values = $targetNode->getProperty($targetPropertyName, true) ?: [];
                if ($unreference) {
                    $values = \array_map(function ($node) use ($sourceNode) {
                        if ($node instanceof NodeInterface) {
                            return $node->getIdentifier() !== $sourceNode->getIdentifier();
                        }
                        return $node !== $sourceNode->getIdentifier();
                    }, $values);
                    $this->withoutCrossReferenceMapping(function () use ($targetNode, $targetPropertyName, $values) {
                        $targetNode->setProperty($targetPropertyName, \array_unique($values));
                    });
                    $this->systemLogger->log(vsprintf('Cross reference unsetted from node "%s" (%s) to "%s" (%s)', [
                        $sourceNode->getLabel(),
                        $sourceNode->getIdentifier(),
                        $targetNode->getLabel(),
                        $targetNode->getIdentifier()
                    ]), \LOG_DEBUG, null, 'Ttree.CrossReference');
                } else {
                    $values[] = $sourceNode->getIdentifier();
                    $this->withoutCrossReferenceMapping(function () use ($targetNode, $targetPropertyName, $values) {
                        $targetNode->setProperty($targetPropertyName, \array_unique($values));
                    });
                    $this->systemLogger->log(vsprintf('Cross reference setted from node "%s" (%s) to "%s" (%s)', [
                        $sourceNode->getLabel(),
                        $sourceNode->getIdentifier(),
                        $targetNode->getLabel(),
                        $targetNode->getIdentifier()
                    ]), \LOG_DEBUG, null, 'Ttree.CrossReference');
                }
            }
        }
    }

    protected function match(string $nodeType, string $propertyName): bool
    {
        foreach ($this->presets as $preset) {
            if ($preset->match($nodeType, $propertyName)) {
                return true;
            }
        }
        return false;
    }

    protected function mapping(string $nodeType, string $propertyName): array
    {
        $mapping = [];
        foreach ($this->presets as $preset) {
            if ($preset->match($nodeType, $propertyName)) {
                $mapping = \array_merge($mapping, $preset->mapping($nodeType, $propertyName));
            }
        }
        return $mapping;
    }

    public function withoutCrossReferenceMapping(\Closure $callback)
    {
        $enabled = $this->enabled;
        $this->enabled = false;
        try {
            /** @noinspection PhpUndefinedMethodInspection */
            $callback->__invoke();
        } catch (\Exception $exception) {
            $this->enabled = true;
            throw $exception;
        }
        $this->enabled = $enabled;
    }
}
