<?php
namespace Ttree\CrossReference\Domain\Model;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;

final class Preset
{
    /**
     * @var array
     * @Flow\InjectConfiguration(path="presets")
     */
    protected $configuration;

    /**
     * @var array
     */
    protected $mapping = [];

    public function __construct(array $configuration)
    {
        $this->mapping = $configuration['mapping'];
    }

    public function match(string $nodeType, string $propertyName): bool
    {
        foreach ($this->mapping as $mapping) {
            if (isset($mapping[$nodeType]) && $mapping[$nodeType] === $propertyName) {
                return true;
            }
        }
        return false;
    }

    public function mapping(string $nodeType, string $propertyName): array
    {
        if (!$this->match($nodeType, $propertyName)) {
            throw new Exception(sprintf('No mapping found for the give parameter "%s@%s"', $nodeType, $propertyName), 1506528477);
        }
        $filteredMapping = [];
        foreach ($this->mapping as $mapping) {
            if (isset($mapping[$nodeType]) && $mapping[$nodeType] === $propertyName) {
                continue;
            }
            $filteredMapping[] = $mapping;
        }
        return $filteredMapping;
    }
}
